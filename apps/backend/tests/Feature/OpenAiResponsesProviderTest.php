<?php

namespace Tests\Feature;

use App\AI\Data\LlmRequest;
use App\AI\Data\ModelPolicy;
use App\AI\Data\ResolvedModel;
use App\AI\Exceptions\LlmProviderException;
use App\AI\Providers\OpenAiResponsesProvider;
use App\Settings\SettingsResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiResponsesProviderTest extends TestCase
{
    public function test_http_status_maps_to_provider_failure_retryability(): void
    {
        config([
            'ai.providers.openai.api_key' => 'test-key',
            'ai.providers.openai.base_url' => 'https://api.openai.com/v1',
            'ai.providers.openai.timeout_seconds' => 2,
        ]);
        $request = new LlmRequest('trusted', 'untrusted', ['type' => 'object'], new ModelPolicy('test', true));
        $model = new ResolvedModel('test', 'openai', 'test-model');
        $provider = app(OpenAiResponsesProvider::class);
        Http::fakeSequence('*/responses')
            ->push(['error' => ['message' => 'private provider response']], 429, ['Retry-After' => '37'])
            ->push(['error' => ['message' => 'private provider response']], 503)
            ->push(['error' => ['message' => 'private provider response']], 400);

        foreach ([
            [429, LlmProviderException::RATE_LIMITED, true],
            [503, LlmProviderException::TEMPORARY_UNAVAILABLE, true],
            [400, LlmProviderException::INVALID_CONFIGURATION, false],
        ] as [$status, $category, $retryable]) {
            try {
                $provider->generateResolved($request, $model);
                $this->fail('An unsuccessful provider response must throw a controlled exception.');
            } catch (LlmProviderException $exception) {
                $this->assertSame($category, $exception->category);
                $this->assertSame($retryable, $exception->isRetryable());
                $this->assertSame($category === LlmProviderException::INVALID_CONFIGURATION, $exception->requiresConfiguration());
                $this->assertSame($status === 429 ? 37 : null, $exception->retryAfterSeconds);
            }
        }
    }

    public function test_retry_after_header_is_numeric_bounded_or_absent(): void
    {
        config([
            'ai.providers.openai.api_key' => 'test-key',
            'ai.providers.openai.base_url' => 'https://api.openai.com/v1',
            'ai.providers.openai.timeout_seconds' => 2,
        ]);
        $request = new LlmRequest('trusted', 'untrusted', ['type' => 'object'], new ModelPolicy('test', true));
        $model = new ResolvedModel('test', 'openai', 'test-model');
        $provider = app(OpenAiResponsesProvider::class);
        Http::fakeSequence('*/responses')
            ->push([], 429, ['Retry-After' => '42'])
            ->push([], 429, ['Retry-After' => '999999'])
            ->push([], 429, ['Retry-After' => 'later'])
            ->push([], 429);

        foreach ([42, 86400, null, null] as $expected) {
            try {
                $provider->generateResolved($request, $model);
                $this->fail('A rate-limited response must throw.');
            } catch (LlmProviderException $exception) {
                $this->assertSame(LlmProviderException::RATE_LIMITED, $exception->category);
                $this->assertSame($expected, $exception->retryAfterSeconds);
            }
        }
    }

    public function test_temporary_provider_responses_preserve_bounded_retry_after(): void
    {
        config([
            'ai.providers.openai.api_key' => 'test-key',
            'ai.providers.openai.base_url' => 'https://api.openai.com/v1',
            'ai.providers.openai.timeout_seconds' => 2,
        ]);
        $request = new LlmRequest('trusted', 'untrusted', ['type' => 'object'], new ModelPolicy('test', true));
        $model = new ResolvedModel('test', 'openai', 'test-model');
        $provider = app(OpenAiResponsesProvider::class);
        Http::fakeSequence('*/responses')
            ->push([], 408, ['Retry-After' => '41'])
            ->push([], 425, ['Retry-After' => '37'])
            ->push([], 503, ['Retry-After' => '999999'])
            ->push([], 503, ['Retry-After' => 'later'])
            ->push([], 400, ['Retry-After' => '42']);

        foreach ([
            [LlmProviderException::TEMPORARY_UNAVAILABLE, 41],
            [LlmProviderException::TEMPORARY_UNAVAILABLE, 37],
            [LlmProviderException::TEMPORARY_UNAVAILABLE, 86400],
            [LlmProviderException::TEMPORARY_UNAVAILABLE, null],
            [LlmProviderException::INVALID_CONFIGURATION, null],
        ] as [$category, $expectedDelay]) {
            try {
                $provider->generateResolved($request, $model);
                $this->fail('An unsuccessful provider response must throw.');
            } catch (LlmProviderException $exception) {
                $this->assertSame($category, $exception->category);
                $this->assertSame($expectedDelay, $exception->retryAfterSeconds);
            }
        }
    }

    public function test_test_connection_returns_structured_array_on_success(): void
    {
        Http::fake([
            'https://api.openai.com/v1/models/gpt-4o-mini' => Http::response(['id' => 'gpt-4o-mini'], 200),
        ]);

        $provider = app(OpenAiResponsesProvider::class);
        $result = $provider->testConnection('test-key', 'gpt-4o-mini');

        $this->assertTrue($result['success']);
        $this->assertSame('healthy', $result['status']);
        $this->assertSame('openai', $result['provider']);
        $this->assertSame('gpt-4o-mini', $result['model']);
        $this->assertIsInt($result['latency_ms']);
    }

    public function test_test_connection_returns_structured_error_array_on_auth_failure_without_leaking_raw_body(): void
    {
        Http::fake([
            'https://api.openai.com/v1/models/gpt-4o-mini' => Http::response(['error' => ['message' => 'Sensitive internal backend message']], 401),
        ]);

        $provider = app(OpenAiResponsesProvider::class);
        $result = $provider->testConnection('invalid-key', 'gpt-4o-mini');

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('openai', $result['provider']);
        $this->assertSame('INVALID_CREDENTIALS', $result['error_code']);
        $this->assertStringNotContainsString('Sensitive internal backend message', $result['message']);
    }

    public function test_test_connection_returns_structured_error_array_when_key_is_missing(): void
    {
        config(['ai.providers.openai.api_key' => '']);
        $provider = app(OpenAiResponsesProvider::class);
        $result = $provider->testConnection('', 'gpt-4o-mini');

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('NOT_CONFIGURED', $result['error_code']);
    }

    public function test_dynamic_settings_overrides_are_consumed(): void
    {
        $settings = $this->createMock(SettingsResolver::class);
        $settings->method('get')->willReturnCallback(function (string $key, mixed $default = null) {
            return match ($key) {
                'ai.providers.openai.api_key' => 'dynamic-secret-key',
                'ai.providers.openai.base_url' => 'https://custom-gateway.local/v1',
                'ai.providers.openai.timeout_seconds' => 99,
                default => $default,
            };
        });
        $this->app->instance(SettingsResolver::class, $settings);

        Http::fake([
            'https://custom-gateway.local/v1/responses' => Http::response([
                'id' => 'resp_123',
                'status' => 'completed',
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            ['type' => 'output_text', 'text' => '{"ok": true}'],
                        ],
                    ],
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
            ], 200),
        ]);

        $request = new LlmRequest('trusted', 'untrusted', ['type' => 'object'], new ModelPolicy('test', true));
        $model = new ResolvedModel('test', 'openai', 'gpt-4o-mini');
        $provider = app(OpenAiResponsesProvider::class);

        $response = $provider->generateResolved($request, $model);
        $this->assertSame(['ok' => true], $response->output);

        Http::assertSent(function (Request $req) {
            return $req->url() === 'https://custom-gateway.local/v1/responses'
                && $req->hasHeader('Authorization', 'Bearer dynamic-secret-key');
        });
    }
}
