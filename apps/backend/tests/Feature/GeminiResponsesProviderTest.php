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
                'additionalProperties' => false,
                'properties' => [
                    'facts' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
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
            $hasSchemaWithoutAdditionalProperties = ! isset($data['generationConfig']['responseSchema']['additionalProperties'])
                && ! isset($data['generationConfig']['responseSchema']['properties']['facts']['items']['additionalProperties']);

            return $hasHeader && $hasSystemInstruction && $hasSchemaWithoutDollar && $hasSchemaWithoutAdditionalProperties;
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

    public function test_list_models_returns_only_generate_content_models_sorted(): void
    {
        $provider = app(GeminiResponsesProvider::class);

        Http::fake([
            '*/models*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/text-embedding-004',
                        'displayName' => 'Text Embedding 004',
                        'supportedGenerationMethods' => ['embedContent'],
                    ],
                    [
                        'name' => 'models/gemini-1.5-pro',
                        'displayName' => 'Gemini 1.5 Pro',
                        'supportedGenerationMethods' => ['generateContent', 'countTokens'],
                    ],
                    [
                        'name' => 'models/gemini-2.0-flash',
                        'displayName' => 'Gemini 2.0 Flash',
                        'supportedGenerationMethods' => ['generateContent'],
                    ],
                    [
                        'name' => 'models/gemini-1.5-flash',
                        'displayName' => 'Gemini 1.5 Flash',
                        'supportedGenerationMethods' => ['generateContent'],
                    ],
                ],
            ], 200),
        ]);

        $models = $provider->listModels('valid-key');

        $this->assertCount(3, $models);
        // Assert embedding model was filtered out
        $this->assertNotContains('text-embedding-004', array_column($models, 'id'));
        // Assert priority sorting: 2.0-flash first, then 1.5-flash, then 1.5-pro
        $this->assertSame('gemini-2.0-flash', $models[0]['id']);
        $this->assertSame('gemini-1.5-flash', $models[1]['id']);
        $this->assertSame('gemini-1.5-pro', $models[2]['id']);
        $this->assertSame('Gemini 2.0 Flash (gemini-2.0-flash)', $models[0]['name']);
    }

    public function test_connection_test_falls_back_to_available_model_when_target_model_returns_404(): void
    {
        $provider = app(GeminiResponsesProvider::class);

        Http::fake([
            '*/models?*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/gemini-2.0-flash',
                        'displayName' => 'Gemini 2.0 Flash',
                        'supportedGenerationMethods' => ['generateContent'],
                    ],
                ],
            ], 200),
            '*/models/gemini-1.5-flash:generateContent*' => Http::response([
                'error' => ['message' => 'models/gemini-1.5-flash is not found.'],
            ], 404),
            '*/models/gemini-2.0-flash:generateContent*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'pong']]], 'finishReason' => 'STOP'],
                ],
            ], 200),
        ]);

        $result = $provider->testConnection('custom-key', 'gemini-1.5-flash');

        $this->assertTrue($result['success']);
        $this->assertSame('healthy', $result['status']);
        $this->assertSame('gemini-2.0-flash', $result['model']);
        $this->assertCount(1, $result['available_models']);
        $this->assertStringContainsString('gemini-2.0-flash', $result['message']);
    }

    public function test_sanitize_schema_inlines_defs_and_refs(): void
    {
        $provider = app(GeminiResponsesProvider::class);

        $schema = [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['drafts'],
            'properties' => [
                'drafts' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/$defs/draft_item'],
                ],
            ],
            '$defs' => [
                'draft_item' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['content', 'tags'],
                    'properties' => [
                        'content' => ['type' => 'string', 'minLength' => 10],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $sanitized = $provider->sanitizeSchema($schema);

        $this->assertArrayNotHasKey('$schema', $sanitized);
        $this->assertArrayNotHasKey('$defs', $sanitized);
        $this->assertArrayNotHasKey('additionalProperties', $sanitized);

        $itemSchema = $sanitized['properties']['drafts']['items'];
        $this->assertSame('object', $itemSchema['type']);
        $this->assertArrayNotHasKey('$ref', $itemSchema);
        $this->assertArrayNotHasKey('additionalProperties', $itemSchema);
        $this->assertSame(['content', 'tags'], $itemSchema['required']);
        $this->assertSame('string', $itemSchema['properties']['content']['type']);
        $this->assertArrayNotHasKey('minLength', $itemSchema['properties']['content']);
    }

    public function test_sanitize_schema_converts_nullable_union_to_openapi_nullable(): void
    {
        $provider = app(GeminiResponsesProvider::class);

        $schema = [
            'type' => 'object',
            'properties' => [
                'normalized_value' => [
                    'type' => ['string', 'null'],
                    'description' => 'A string or null value',
                ],
            ],
        ];

        $sanitized = $provider->sanitizeSchema($schema);

        $this->assertSame('string', $sanitized['properties']['normalized_value']['type']);
        $this->assertTrue($sanitized['properties']['normalized_value']['nullable']);
        $this->assertSame('A string or null value', $sanitized['properties']['normalized_value']['description']);
    }

    public function test_sanitize_schema_preserves_additional_properties_field_name(): void
    {
        $provider = app(GeminiResponsesProvider::class);

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['additionalProperties'],
            'properties' => [
                'additionalProperties' => [
                    'type' => 'string',
                    'description' => 'A user field explicitly named additionalProperties',
                ],
            ],
        ];

        $sanitized = $provider->sanitizeSchema($schema);

        // Directive at object level must be stripped
        $this->assertArrayNotHasKey('additionalProperties', $sanitized);

        // But property with the name 'additionalProperties' MUST NOT be deleted
        $this->assertArrayHasKey('additionalProperties', $sanitized['properties']);
        $this->assertSame('string', $sanitized['properties']['additionalProperties']['type']);
        $this->assertSame(['additionalProperties'], $sanitized['required']);
    }

    public function test_sanitize_schema_strips_unsupported_validation_keywords_while_preserving_enums(): void
    {
        $provider = app(GeminiResponsesProvider::class);

        $schema = [
            'type' => 'object',
            'required' => ['score', 'category'],
            'properties' => [
                'score' => [
                    'type' => 'number',
                    'minimum' => 0.0,
                    'maximum' => 1.0,
                ],
                'category' => [
                    'type' => 'string',
                    'enum' => ['A', 'B', 'C'],
                ],
                'tags' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 5,
                    'items' => [
                        'type' => 'string',
                        'minLength' => 2,
                        'maxLength' => 20,
                    ],
                ],
            ],
        ];

        $sanitized = $provider->sanitizeSchema($schema);

        $this->assertSame(['score', 'category'], $sanitized['required']);
        $this->assertArrayNotHasKey('minimum', $sanitized['properties']['score']);
        $this->assertArrayNotHasKey('maximum', $sanitized['properties']['score']);
        $this->assertSame(['A', 'B', 'C'], $sanitized['properties']['category']['enum']);
        $this->assertArrayNotHasKey('minItems', $sanitized['properties']['tags']);
        $this->assertArrayNotHasKey('maxItems', $sanitized['properties']['tags']);
        $this->assertArrayNotHasKey('minLength', $sanitized['properties']['tags']['items']);
        $this->assertArrayNotHasKey('maxLength', $sanitized['properties']['tags']['items']);
    }

    public function test_sanitize_schema_detects_circular_ref_and_throws(): void
    {
        $provider = app(GeminiResponsesProvider::class);

        $schema = [
            'type' => 'object',
            'properties' => [
                'self' => ['$ref' => '#/$defs/node'],
            ],
            '$defs' => [
                'node' => [
                    'type' => 'object',
                    'properties' => [
                        'parent' => ['$ref' => '#/$defs/node'],
                    ],
                ],
            ],
        ];

        $this->expectException(LlmProviderException::class);
        $this->expectExceptionMessage('Circular schema reference detected');

        $provider->sanitizeSchema($schema);
    }

    public function test_sanitize_schema_throws_on_unresolvable_ref(): void
    {
        $provider = app(GeminiResponsesProvider::class);

        $schema = [
            'type' => 'object',
            'properties' => [
                'item' => ['$ref' => '#/$defs/nonexistent'],
            ],
            '$defs' => [],
        ];

        $this->expectException(LlmProviderException::class);
        $this->expectExceptionMessage('Unresolvable schema reference');

        $provider->sanitizeSchema($schema);
    }
}
