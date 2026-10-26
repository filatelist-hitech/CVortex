<?php

namespace Tests\Feature;

use App\AI\Data\LlmRequest;
use App\AI\Data\ModelPolicy;
use App\AI\Exceptions\LlmProviderException;
use App\AI\ModelPolicyResolver;
use App\AI\Providers\ConfiguredLlmProvider;
use App\Models\CareerFact;
use App\Models\LlmRun;
use App\Models\User;
use App\Models\UserAiSetting;
use App\Services\CareerExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::query()->create([
            'email' => 'usera@example.com',
            'password' => 'secret123',
            'role' => 'user',
            'status' => 'ACTIVE',
            'auth_generation' => 0,
        ]);

        $this->userB = User::query()->create([
            'email' => 'userb@example.com',
            'password' => 'secret123',
            'role' => 'user',
            'status' => 'ACTIVE',
            'auth_generation' => 0,
        ]);
    }

    private function asUser(User $user): self
    {
        app(\App\Services\DatabaseOwnerContext::class)->set((string) $user->id);

        return $this->actingAs($user->fresh());
    }

    public function test_unauthenticated_cannot_access_ai_settings(): void
    {
        $this->getJson('/api/v1/ai/settings')->assertStatus(401);
        $this->patchJson('/api/v1/ai/settings', ['selected_provider' => 'gemini'])->assertStatus(401);
        $this->postJson('/api/v1/ai/test-connection', ['provider' => 'gemini'])->assertStatus(401);
    }

    public function test_authenticated_user_can_read_settings_and_secrets_are_never_exposed(): void
    {
        $this->asUser($this->userA);

        UserAiSetting::query()->create([
            'owner_id' => $this->userA->id,
            'selected_provider' => 'gemini',
            'gemini_api_key' => 'super-secret-gemini-key-999',
            'gemini_model' => 'gemini-1.5-pro',
        ]);

        $response = $this->getJson('/api/v1/ai/settings');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertSame('gemini', $data['selected_provider']);
        $this->assertSame('gemini', $data['active_provider']);
        $this->assertTrue($data['providers']['gemini']['has_user_key']);
        $this->assertTrue($data['providers']['gemini']['configured']);
        $this->assertSame('gemini-1.5-pro', $data['providers']['gemini']['model']);

        // Assert secret is NEVER exposed anywhere in raw response
        $rawContent = $response->getContent();
        $this->assertStringNotContainsString('super-secret-gemini-key-999', (string) $rawContent);
    }

    public function test_authenticated_user_can_update_provider_and_keys(): void
    {
        $this->asUser($this->userA);

        $patchResponse = $this->patchJson('/api/v1/ai/settings', [
            'selected_provider' => 'gemini',
            'gemini_api_key' => 'ai-studio-key-45678',
            'gemini_model' => 'gemini-2.0-flash',
        ]);

        $patchResponse->assertOk();
        $patchResponse->assertJsonPath('data.selected_provider', 'gemini');
        $patchResponse->assertJsonPath('data.providers.gemini.has_user_key', true);
        $this->assertStringNotContainsString('ai-studio-key-45678', (string) $patchResponse->getContent());

        // Check encryption at rest in DB
        $rawDbRow = DB::table('user_ai_settings')->where('owner_id', $this->userA->id)->first();
        $this->assertNotNull($rawDbRow);
        $this->assertNotSame('ai-studio-key-45678', $rawDbRow->gemini_api_key);

        // Check model cast decrypts it properly
        $model = UserAiSetting::query()->where('owner_id', $this->userA->id)->first();
        $this->assertSame('ai-studio-key-45678', $model->gemini_api_key);

        // Clear key
        $clearResponse = $this->patchJson('/api/v1/ai/settings', [
            'clear_gemini_key' => true,
        ]);
        $clearResponse->assertOk();
        $clearResponse->assertJsonPath('data.providers.gemini.has_user_key', false);

        $model->refresh();
        $this->assertNull($model->gemini_api_key);

        // Set key again and clear via empty string
        $this->patchJson('/api/v1/ai/settings', ['gemini_api_key' => 'another-key'])->assertOk();
        $model->refresh();
        $this->assertSame('another-key', $model->gemini_api_key);

        $this->patchJson('/api/v1/ai/settings', ['gemini_api_key' => '   '])->assertOk();
        $model->refresh();
        $this->assertNull($model->gemini_api_key);
    }

    public function test_cross_user_isolation(): void
    {
        // User A configures BYOK
        UserAiSetting::query()->create([
            'owner_id' => $this->userA->id,
            'selected_provider' => 'gemini',
            'gemini_api_key' => 'secret-of-user-a',
        ]);

        // User B logs in
        $this->asUser($this->userB);

        $responseB = $this->getJson('/api/v1/ai/settings');
        $responseB->assertOk();
        $this->assertFalse($responseB->json('data.providers.gemini.has_user_key'));
        $this->assertSame('system_default', $responseB->json('data.selected_provider'));

        // User B attempts to tamper with User A by injecting user_id/owner_id in body
        $tamper = $this->patchJson('/api/v1/ai/settings', [
            'owner_id' => $this->userA->id,
            'user_id' => $this->userA->id,
            'selected_provider' => 'openai',
        ]);
        $tamper->assertOk();

        // User A's setting must remain untouched
        $settingA = UserAiSetting::query()->where('owner_id', $this->userA->id)->first();
        $this->assertSame('gemini', $settingA->selected_provider);
        $this->assertSame('secret-of-user-a', $settingA->gemini_api_key);

        // User B's setting was created for User B
        $settingB = UserAiSetting::query()->where('owner_id', $this->userB->id)->first();
        $this->assertSame($this->userB->id, $settingB->owner_id);
    }

    public function test_model_policy_resolver_routes_to_user_selected_provider(): void
    {
        $this->asUser($this->userA);

        UserAiSetting::query()->create([
            'owner_id' => $this->userA->id,
            'selected_provider' => 'gemini',
            'gemini_model' => 'gemini-1.5-pro',
        ]);

        $resolver = app(ModelPolicyResolver::class);
        $resolved = $resolver->resolve(new ModelPolicy('low_cost_structured_extraction', true), $this->userA);

        $this->assertSame('gemini', $resolved->provider);
        $this->assertSame('gemini-1.5-pro', $resolved->model);
    }

    public function test_no_silent_fallback_on_gemini_error(): void
    {
        $this->asUser($this->userA);

        UserAiSetting::query()->create([
            'owner_id' => $this->userA->id,
            'selected_provider' => 'gemini',
            'gemini_api_key' => 'valid-gemini-key',
        ]);

        config([
            'ai.providers.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);

        Http::fake([
            '*/models/*:generateContent*' => Http::response(['error' => ['message' => 'Rate limit exceeded']], 429),
            'https://api.openai.com/*' => Http::response(['output' => []], 200),
        ]);

        $provider = app(ConfiguredLlmProvider::class);

        $this->expectException(LlmProviderException::class);
        $this->expectExceptionMessage('The Gemini provider returned an unsuccessful response.');

        try {
            $provider->generateStructured(new LlmRequest(
                'trusted',
                'untrusted',
                ['type' => 'object'],
                new ModelPolicy('low_cost_structured_extraction', true),
            ));
        } finally {
            // Verify NO requests were sent to OpenAI as a silent fallback!
            Http::assertNotSent(fn ($req) => str_contains($req->url(), 'openai.com'));
        }
    }

    public function test_test_connection_endpoint_with_gemini_and_openai(): void
    {
        $this->asUser($this->userA);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'pong']]], 'finishReason' => 'STOP'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/ai/test-connection', [
            'provider' => 'gemini',
            'api_key' => 'test-probe-key',
            'model' => 'gemini-1.5-flash',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertSame('healthy', $response->json('status'));
        $this->assertSame('gemini', $response->json('provider'));
        $this->assertSame('gemini-1.5-flash', $response->json('model'));

        // Test OpenAI connection success
        Http::fake([
            'https://api.openai.com/v1/models/gpt-4o-mini' => Http::response(['id' => 'gpt-4o-mini'], 200),
        ]);

        $openAiResponse = $this->postJson('/api/v1/ai/test-connection', [
            'provider' => 'openai',
            'api_key' => 'test-openai-probe-key',
            'model' => 'gpt-4o-mini',
        ]);

        $openAiResponse->assertOk();
        $this->assertTrue($openAiResponse->json('success'));
        $this->assertSame('healthy', $openAiResponse->json('status'));
        $this->assertSame('openai', $openAiResponse->json('provider'));
    }

    public function test_test_connection_endpoint_openai_error_returns_structured_error(): void
    {
        $this->asUser($this->userA);

        Http::fake([
            'https://api.openai.com/v1/models/gpt-4o-mini' => Http::response(['error' => ['message' => 'Invalid key']], 401),
        ]);

        $openAiErrorResponse = $this->postJson('/api/v1/ai/test-connection', [
            'provider' => 'openai',
            'api_key' => 'bad-key',
            'model' => 'gpt-4o-mini',
        ]);

        $openAiErrorResponse->assertOk();
        $this->assertFalse($openAiErrorResponse->json('success'));
        $this->assertSame('failed', $openAiErrorResponse->json('status'));
        $this->assertSame('INVALID_CREDENTIALS', $openAiErrorResponse->json('error_code'));
    }

    public function test_end_to_end_career_extraction_with_gemini_provider_preserves_truth_guard(): void
    {
        $this->asUser($this->userA);

        UserAiSetting::query()->create([
            'owner_id' => $this->userA->id,
            'selected_provider' => 'gemini',
            'gemini_api_key' => 'byok-gemini-key',
            'gemini_model' => 'gemini-1.5-flash',
        ]);

        $sourceText = 'I am familiar with Laravel.';

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
                                            [
                                                'fact_type' => 'skill',
                                                'assertion' => 'I am familiar with Laravel.',
                                                'source_excerpt' => 'I am familiar with Laravel.',
                                                'confidence' => 0.95,
                                            ],
                                        ],
                                    ]),
                                ],
                            ],
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 200,
                    'candidatesTokenCount' => 50,
                ],
                'modelVersion' => 'gemini-1.5-flash-latest',
            ], 200, ['x-goog-request-id' => 'req-e2e-gemini']),
        ]);

        $service = app(CareerExtractionService::class);
        $source = $service->extract($this->userA, $sourceText);

        $this->assertNotNull($source);

        // Check LLM Run recorded Gemini provider and model
        $run = LlmRun::query()->where('career_source_id', $source->id)->latest()->first();
        $this->assertNotNull($run);
        $this->assertSame('gemini', $run->provider);
        $this->assertSame('gemini-1.5-flash-latest', $run->model);
        $this->assertSame(200, $run->input_tokens);
        $this->assertSame(50, $run->output_tokens);

        // Truth Guard Check: extracted career fact must remain strictly PENDING
        $fact = CareerFact::query()->where('career_source_id', $source->id)->first();
        $this->assertNotNull($fact);
        $this->assertSame(CareerFact::STATUS_PENDING, $fact->status);
        $this->assertSame('I am familiar with Laravel.', $fact->assertion_original);
        $this->assertNull($fact->assertion); // Human approval required before assertion is active
    }

    public function test_openai_continues_to_work_without_regression(): void
    {
        $this->asUser($this->userA);

        UserAiSetting::query()->create([
            'owner_id' => $this->userA->id,
            'selected_provider' => 'openai',
            'openai_api_key' => 'byok-openai-key',
        ]);

        config([
            'ai.model_policies.low_cost_structured_extraction.model' => 'gpt-4o-mini',
            'ai.providers.openai.base_url' => 'https://api.openai.com/v1',
        ]);

        $sourceText = 'I am familiar with Laravel.';

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'status' => 'completed',
                'model' => 'gpt-4o-mini',
                'output' => [
                    [
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode([
                                    'facts' => [
                                        [
                                            'fact_type' => 'skill',
                                            'assertion' => 'I am familiar with Laravel.',
                                            'source_excerpt' => 'I am familiar with Laravel.',
                                            'confidence' => 0.90,
                                        ],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
                'usage' => [
                    'input_tokens' => 150,
                    'output_tokens' => 35,
                ],
            ], 200, ['x-request-id' => 'req-openai-e2e']),
        ]);

        $service = app(CareerExtractionService::class);
        $source = $service->extract($this->userA, $sourceText);

        $this->assertNotNull($source);

        $run = LlmRun::query()->where('career_source_id', $source->id)->latest()->first();
        $this->assertNotNull($run);
        $this->assertSame('openai', $run->provider);
        $this->assertSame('gpt-4o-mini', $run->model);

        $fact = CareerFact::query()->where('career_source_id', $source->id)->first();
        $this->assertNotNull($fact);
        $this->assertSame(CareerFact::STATUS_PENDING, $fact->status);
        $this->assertSame('I am familiar with Laravel.', $fact->assertion_original);
        $this->assertNull($fact->assertion);
    }

    public function test_model_policy_resolves_costs_correctly_for_selected_provider(): void
    {
        config([
            'ai.default_provider' => 'openai',
            'ai.providers.gemini.input_cost_micros_per_million_tokens' => 75,
            'ai.providers.gemini.output_cost_micros_per_million_tokens' => 300,
            'ai.model_policies.low_cost_structured_extraction' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'input_cost_micros_per_million_tokens' => 150,
                'output_cost_micros_per_million_tokens' => 600,
            ],
        ]);

        UserAiSetting::query()->create([
            'owner_id' => $this->userA->id,
            'selected_provider' => 'gemini',
            'gemini_api_key' => 'byok-gemini-key',
        ]);

        $resolver = app(ModelPolicyResolver::class);
        $resolved = $resolver->resolve(new ModelPolicy('low_cost_structured_extraction', true), $this->userA);

        $this->assertSame('gemini', $resolved->provider);
        $this->assertSame(75, $resolved->inputCostMicrosPerMillionTokens);
        $this->assertSame(300, $resolved->outputCostMicrosPerMillionTokens);
        $this->assertSame('byok-gemini-key', $resolved->apiKey);
    }

    public function test_conflicting_parameters_rejects_with_validation_error(): void
    {
        $this->asUser($this->userA);

        // Pre-seed an existing key
        UserAiSetting::query()->create([
            'owner_id' => $this->userA->id,
            'gemini_api_key' => 'initial-gemini-key',
            'openai_api_key' => 'initial-openai-key',
        ]);

        // Attempt conflicting update: both clear and new key for Gemini
        $resGemini = $this->patchJson('/api/v1/ai/settings', [
            'gemini_api_key' => 'some-new-key',
            'clear_gemini_key' => true,
        ]);
        $resGemini->assertStatus(422);
        $resGemini->assertJsonValidationErrors(['clear_gemini_key']);

        // Assert existing key was NOT lost or corrupted
        $setting = UserAiSetting::query()->where('owner_id', $this->userA->id)->first();
        $this->assertSame('initial-gemini-key', $setting->gemini_api_key);

        // Attempt conflicting update for OpenAI
        $resOpenAi = $this->patchJson('/api/v1/ai/settings', [
            'openai_api_key' => 'some-new-openai-key',
            'clear_openai_key' => true,
        ]);
        $resOpenAi->assertStatus(422);
        $resOpenAi->assertJsonValidationErrors(['clear_openai_key']);

        $setting->refresh();
        $this->assertSame('initial-openai-key', $setting->openai_api_key);
    }

    public function test_clear_flag_false_preserves_or_updates_key_properly(): void
    {
        $this->asUser($this->userA);

        UserAiSetting::query()->create([
            'owner_id' => $this->userA->id,
            'gemini_api_key' => 'existing-key',
        ]);

        // 1. clear_gemini_key: false with no new key -> key is preserved
        $res1 = $this->patchJson('/api/v1/ai/settings', [
            'clear_gemini_key' => false,
        ]);
        $res1->assertOk();
        $setting = UserAiSetting::query()->where('owner_id', $this->userA->id)->first();
        $this->assertSame('existing-key', $setting->gemini_api_key);

        // 2. clear_gemini_key: false with valid new key -> key is updated
        $res2 = $this->patchJson('/api/v1/ai/settings', [
            'clear_gemini_key' => false,
            'gemini_api_key' => 'updated-key',
        ]);
        $res2->assertOk();
        $setting->refresh();
        $this->assertSame('updated-key', $setting->gemini_api_key);
    }

    public function test_user_clears_key_and_subsequent_request_fails_closed_without_secret_leakage(): void
    {
        $this->asUser($this->userA);

        // User had a BYOK key
        UserAiSetting::query()->create([
            'owner_id' => $this->userA->id,
            'selected_provider' => 'gemini',
            'gemini_api_key' => 'super-secret-byok-key-xyz',
        ]);

        // Clear key
        $this->patchJson('/api/v1/ai/settings', ['clear_gemini_key' => true])->assertOk();

        // Ensure system key is also empty
        config(['ai.providers.gemini.api_key' => '']);

        $provider = app(ConfiguredLlmProvider::class);

        try {
            $provider->generateStructured(new LlmRequest(
                'trusted',
                'untrusted',
                ['type' => 'object'],
                new ModelPolicy('low_cost_structured_extraction', true),
            ));
            $this->fail('Call without credentials must throw NOT_CONFIGURED.');
        } catch (LlmProviderException $e) {
            $this->assertSame(LlmProviderException::NOT_CONFIGURED, $e->category);
            $this->assertSame('gemini', $e->providerName);
            $this->assertStringNotContainsString('super-secret-byok-key-xyz', $e->getMessage());
        }
    }

    public function test_pricing_isolation_between_models_and_providers(): void
    {
        $this->asUser($this->userA);

        config([
            'ai.default_provider' => 'openai',
            'ai.providers.gemini.extraction_model' => 'gemini-1.5-flash',
            'ai.providers.gemini.input_cost_micros_per_million_tokens' => 75,
            'ai.providers.gemini.output_cost_micros_per_million_tokens' => 300,
            'ai.providers.openai.default_model' => 'gpt-4o-mini',
            'ai.providers.openai.extraction_input_cost_micros' => 150,
            'ai.providers.openai.extraction_output_cost_micros' => 600,
            'ai.model_policies.low_cost_structured_extraction' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'input_cost_micros_per_million_tokens' => 150,
                'output_cost_micros_per_million_tokens' => 600,
            ],
        ]);

        $resolver = app(ModelPolicyResolver::class);
        $policy = new ModelPolicy('low_cost_structured_extraction', true);

        // 1. User with Gemini provider and default model -> gets Gemini flash prices
        $setting = UserAiSetting::query()->create([
            'owner_id' => $this->userA->id,
            'selected_provider' => 'gemini',
        ]);

        $resolved = $resolver->resolve($policy, $this->userA);
        $this->assertSame('gemini', $resolved->provider);
        $this->assertSame('gemini-1.5-flash', $resolved->model);
        $this->assertSame(75, $resolved->inputCostMicrosPerMillionTokens);
        $this->assertSame(300, $resolved->outputCostMicrosPerMillionTokens);

        // 2. User selects custom/different model without configured price (e.g. gemini-1.5-pro)
        // Must NOT silently apply the gemini-1.5-flash price!
        $setting->update(['gemini_model' => 'gemini-1.5-pro']);
        $resolvedPro = $resolver->resolve($policy, $this->userA);
        $this->assertSame('gemini-1.5-pro', $resolvedPro->model);
        $this->assertNull($resolvedPro->inputCostMicrosPerMillionTokens);
        $this->assertNull($resolvedPro->outputCostMicrosPerMillionTokens);

        // 3. User selects unknown model -> costs remain null
        $setting->update(['gemini_model' => 'custom-unlisted-model']);
        $resolvedUnknown = $resolver->resolve($policy, $this->userA);
        $this->assertSame('custom-unlisted-model', $resolvedUnknown->model);
        $this->assertNull($resolvedUnknown->inputCostMicrosPerMillionTokens);
        $this->assertNull($resolvedUnknown->outputCostMicrosPerMillionTokens);

        // 4. Free model with cost = 0 is distinctly preserved (not turned into null)
        config([
            'ai.providers.gemini.models.gemini-experimental.input_cost_micros_per_million_tokens' => 0,
            'ai.providers.gemini.models.gemini-experimental.output_cost_micros_per_million_tokens' => 0,
        ]);
        $setting->update(['gemini_model' => 'gemini-experimental']);
        $resolvedFree = $resolver->resolve($policy, $this->userA);
        $this->assertSame('gemini-experimental', $resolvedFree->model);
        $this->assertSame(0, $resolvedFree->inputCostMicrosPerMillionTokens);
        $this->assertSame(0, $resolvedFree->outputCostMicrosPerMillionTokens);

        // 5. Switch to OpenAI -> uses OpenAI prices, completely isolated from Gemini
        $setting->update([
            'selected_provider' => 'openai',
            'openai_model' => null, // default gpt-4o-mini
        ]);
        $resolvedOpenAi = $resolver->resolve($policy, $this->userA);
        $this->assertSame('openai', $resolvedOpenAi->provider);
        $this->assertSame('gpt-4o-mini', $resolvedOpenAi->model);
        $this->assertSame(150, $resolvedOpenAi->inputCostMicrosPerMillionTokens);
        $this->assertSame(600, $resolvedOpenAi->outputCostMicrosPerMillionTokens);

        // 6. OpenAI with custom model without explicit pricing -> costs must be null (not gpt-4o-mini costs)
        $setting->update(['openai_model' => 'gpt-4o']);
        $resolvedGpt4o = $resolver->resolve($policy, $this->userA);
        $this->assertSame('gpt-4o', $resolvedGpt4o->model);
        $this->assertNull($resolvedGpt4o->inputCostMicrosPerMillionTokens);
        $this->assertNull($resolvedGpt4o->outputCostMicrosPerMillionTokens);
    }

    public function test_user_can_fetch_dynamic_models_by_key(): void
    {
        $this->asUser($this->userA);

        Http::fake([
            '*/models*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/gemini-2.0-flash',
                        'displayName' => 'Gemini 2.0 Flash',
                        'supportedGenerationMethods' => ['generateContent'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/ai/models', [
            'provider' => 'gemini',
            'api_key' => 'custom-key-123',
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame('gemini', $data['provider']);
        $this->assertCount(1, $data['models']);
        $this->assertSame('gemini-2.0-flash', $data['models'][0]['id']);
    }

    public function test_test_connection_returns_available_models_payload(): void
    {
        $this->asUser($this->userA);

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
            '*/models/gemini-2.0-flash:generateContent*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'pong']]], 'finishReason' => 'STOP'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/ai/test-connection', [
            'provider' => 'gemini',
            'api_key' => 'custom-key-123',
            'model' => 'gemini-2.0-flash',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertSame('gemini-2.0-flash', $response->json('model'));
        $this->assertIsArray($response->json('available_models'));
        $this->assertSame('gemini-2.0-flash', $response->json('available_models.0.id'));
    }

    public function test_resolver_resolves_user_settings_from_database_owner_context_in_background_jobs(): void
    {
        auth()->logout();
        $this->assertNull(auth()->user());

        UserAiSetting::query()->updateOrCreate(
            ['owner_id' => $this->userA->id],
            [
                'selected_provider' => 'gemini',
                'gemini_api_key' => 'secret-gemini-key',
                'gemini_model' => 'gemini-2.0-flash',
            ]
        );

        $policy = new ModelPolicy('low_cost_structured_extraction', true);
        $resolver = app(ModelPolicyResolver::class);
        $ownerContext = app(\App\Services\DatabaseOwnerContext::class);

        $resolved = $ownerContext->run($this->userA->id, function () use ($resolver, $policy) {
            return $resolver->resolve($policy);
        });

        $this->assertSame('gemini', $resolved->provider);
        $this->assertSame('gemini-2.0-flash', $resolved->model);
        $this->assertSame('secret-gemini-key', $resolved->apiKey);
    }
}
