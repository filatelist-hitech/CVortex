<?php

namespace Tests\Feature;

use App\AI\Data\LlmRequest;
use App\AI\Data\ModelPolicy;
use App\AI\Data\ResolvedModel;
use App\AI\Exceptions\LlmProviderException;
use App\AI\Providers\OpenAiResponsesProvider;
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
}
