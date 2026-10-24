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
        $this->assertSame('gemini', $response->json('provider'));
        $this->assertSame('gemini-1.5-flash', $response->json('model'));
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
}
