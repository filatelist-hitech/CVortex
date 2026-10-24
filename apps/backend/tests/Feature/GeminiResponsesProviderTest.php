<?php

namespace Tests\Feature;

use App\AI\Data\LlmRequest;
use App\AI\Data\ModelPolicy;
use App\AI\Data\ResolvedModel;
use App\AI\Exceptions\LlmProviderException;
use App\AI\Providers\GeminiResponsesProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiResponsesProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ai.providers.gemini.api_key' => 'test-gemini-key',
            'ai.providers.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'ai.providers.gemini.timeout_seconds' => 5,
        ]);
    }

    public function test_successful_structured_output_generation(): void
    {
        $request = new LlmRequest(
            trustedInstructions: 'You are a career extraction engine.',
            untrustedSourceText: 'Candidate worked as Laravel engineer for 5 years.',
            schema: [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                'type' => 'object',
                'properties' => [
                    'facts' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'assertion' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
            modelPolicy: new ModelPolicy('test_policy', true),
            schemaName: 'career_facts',
            untrustedDataLabel: 'CAREER DATA',
        );

        $resolved = new ResolvedModel(
            policyId: 'test_policy',
            provider: 'gemini',
            model: 'gemini-1.5-flash',
            inputCostMicrosPerMillionTokens: 75,
            outputCostMicrosPerMillionTokens: 300,
        );

        Http::fake([
            '*/models/gemini-1.5-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'role' => 'model',
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'facts' => [
                                            ['assertion' => 'Laravel experience for 5 years'],
                                        ],
                                    ]),
                                ],
                            ],
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 120,
                    'candidatesTokenCount' => 40,
                    'totalTokenCount' => 160,
                ],
                'modelVersion' => 'gemini-1.5-flash-002',
            ], 200, ['x-goog-request-id' => 'req-gemini-123']),
        ]);

        $provider = app(GeminiResponsesProvider::class);
        $response = $provider->generateResolved($request, $resolved);

        $this->assertSame('gemini', $response->provider);
        $this->assertSame('gemini-1.5-flash-002', $response->model);
        $this->assertSame(120, $response->inputTokens);
        $this->assertSame(40, $response->outputTokens);
        $this->assertSame('req-gemini-123', $response->providerRequestId);
        $this->assertIsArray($response->output);
        $this->assertSame('Laravel experience for 5 years', $response->output['facts'][0]['assertion']);
        $this->assertNotNull($response->estimatedCostMicros);

        Http::assertSent(function ($request) {
            $hasHeader = $request->hasHeader('x-goog-api-key', 'test-gemini-key');
            $data = $request->data();
            $hasSystemInstruction = isset($data['systemInstruction']['parts'][0]['text'])
                && $data['systemInstruction']['parts'][0]['text'] === 'You are a career extraction engine.';
            $hasSchemaWithoutDollar = ! isset($data['generationConfig']['responseSchema']['$schema']);

            return $hasHeader && $hasSystemInstruction && $hasSchemaWithoutDollar;
        });
    }

    public function test_http_status_maps_to_provider_failure_retryability(): void
    {
        $request = new LlmRequest('trusted', 'untrusted', ['type' => 'object'], new ModelPolicy('test', true));
        $model = new ResolvedModel('test', 'gemini', 'gemini-1.5-flash');
        $provider = app(GeminiResponsesProvider::class);

        Http::fakeSequence('*/models/gemini-1.5-flash:generateContent*')
            ->push(['error' => ['message' => 'Resource exhausted']], 429, ['Retry-After' => '25'])
            ->push(['error' => ['message' => 'Service Unavailable']], 503)
            ->push(['error' => ['message' => 'Invalid API key']], 401)
            ->push(['error' => ['message' => 'Bad request']], 400);

        foreach ([
            [429, LlmProviderException::RATE_LIMITED, true, 25],
            [503, LlmProviderException::TEMPORARY_UNAVAILABLE, true, null],
            [401, LlmProviderException::INVALID_CONFIGURATION, false, null],
            [400, LlmProviderException::INVALID_CONFIGURATION, false, null],
        ] as [$status, $category, $retryable, $expectedRetryAfter]) {
            try {
                $provider->generateResolved($request, $model);
                $this->fail('Unsuccessful Gemini response must throw.');
            } catch (LlmProviderException $exception) {
                $this->assertSame($category, $exception->category);
                $this->assertSame($retryable, $exception->isRetryable());
                $this->assertSame($expectedRetryAfter, $exception->retryAfterSeconds);
                $this->assertSame('gemini', $exception->providerName);
            }
        }
    }

    public function test_connection_exception_maps_to_transport(): void
    {
        $request = new LlmRequest('trusted', 'untrusted', ['type' => 'object'], new ModelPolicy('test', true));
        $model = new ResolvedModel('test', 'gemini', 'gemini-1.5-flash');
        $provider = app(GeminiResponsesProvider::class);

        Http::fake([
            '*/models/gemini-1.5-flash:generateContent*' => fn () => throw new ConnectionException('Connection timeout'),
        ]);

        try {
            $provider->generateResolved($request, $model);
            $this->fail('Network connection failure must throw TRANSPORT exception.');
        } catch (LlmProviderException $exception) {
            $this->assertSame(LlmProviderException::TRANSPORT, $exception->category);
            $this->assertTrue($exception->isRetryable());
            $this->assertSame('gemini', $exception->providerName);
        }
    }

    public function test_safety_block_maps_to_refusal(): void
    {
        $request = new LlmRequest('trusted', 'untrusted', ['type' => 'object'], new ModelPolicy('test', true));
        $model = new ResolvedModel('test', 'gemini', 'gemini-1.5-flash');
        $provider = app(GeminiResponsesProvider::class);

        Http::fake([
            '*/models/gemini-1.5-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'finishReason' => 'SAFETY',
                        'safetyRatings' => [
                            ['category' => 'HARM_CATEGORY_HARASSMENT', 'probability' => 'HIGH'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        try {
            $provider->generateResolved($request, $model);
            $this->fail('Safety refusal must throw REFUSAL exception.');
        } catch (LlmProviderException $exception) {
            $this->assertSame(LlmProviderException::REFUSAL, $exception->category);
            $this->assertFalse($exception->isRetryable());
        }
    }

    public function test_max_tokens_maps_to_incomplete(): void
    {
        $request = new LlmRequest('trusted', 'untrusted', ['type' => 'object'], new ModelPolicy('test', true));
        $model = new ResolvedModel('test', 'gemini', 'gemini-1.5-flash');
        $provider = app(GeminiResponsesProvider::class);

        Http::fake([
            '*/models/gemini-1.5-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'finishReason' => 'MAX_TOKENS',
                        'content' => [
                            'parts' => [['text' => '{"facts": [']],
                        ],
                    ],
                ],
            ], 200),
        ]);

        try {
            $provider->generateResolved($request, $model);
            $this->fail('MAX_TOKENS must throw INCOMPLETE exception.');
        } catch (LlmProviderException $exception) {
            $this->assertSame(LlmProviderException::INCOMPLETE, $exception->category);
        }
    }

    public function test_malformed_json_structured_output(): void
    {
        $request = new LlmRequest('trusted', 'untrusted', ['type' => 'object'], new ModelPolicy('test', true));
        $model = new ResolvedModel('test', 'gemini', 'gemini-1.5-flash');
        $provider = app(GeminiResponsesProvider::class);

        Http::fake([
            '*/models/gemini-1.5-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'finishReason' => 'STOP',
                        'content' => [
                            'parts' => [['text' => 'not-a-json-payload']],
                        ],
                    ],
                ],
            ], 200),
        ]);

        try {
            $provider->generateResolved($request, $model);
            $this->fail('Malformed JSON must throw MALFORMED_OUTPUT exception.');
        } catch (LlmProviderException $exception) {
            $this->assertSame(LlmProviderException::MALFORMED_OUTPUT, $exception->category);
        }
    }

    public function test_connection_test_success_and_failure_states(): void
    {
        $provider = app(GeminiResponsesProvider::class);

        // Sequence: 1. Success, 2. 401 Invalid Credentials
        Http::fakeSequence('*/models/gemini-1.5-flash:generateContent*')
            ->push([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'pong']]], 'finishReason' => 'STOP'],
                ],
            ], 200)
            ->push([
                'error' => ['message' => 'API_KEY_INVALID'],
            ], 401);

        $success = $provider->testConnection('custom-key', 'gemini-1.5-flash');
        $this->assertTrue($success['success']);
        $this->assertSame('gemini', $success['provider']);
        $this->assertArrayHasKey('latency_ms', $success);

        $invalid = $provider->testConnection('bad-key', 'gemini-1.5-flash');
        $this->assertFalse($invalid['success']);
        $this->assertSame('INVALID_CREDENTIALS', $invalid['error_code']);

        // Missing key doesn't even make an HTTP call
        config(['ai.providers.gemini.api_key' => '']);
        $missing = $provider->testConnection('', 'gemini-1.5-flash');
        $this->assertFalse($missing['success']);
        $this->assertSame('NOT_CONFIGURED', $missing['error_code']);
    }
}
