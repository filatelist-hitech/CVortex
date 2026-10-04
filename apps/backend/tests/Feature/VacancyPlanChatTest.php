<?php

namespace Tests\Feature;

use App\AI\Contracts\StreamingProvider;
use App\AI\Providers\OpenAiChatGptPlanProvider;
use App\Models\ChatGptConnection;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyAnalysisDraft;
use App\Models\VacancyLlmRun;
use App\Services\CareerFactService;
use App\Services\TrustedCareerQuery;
use App\Services\VacancyAnalysisDraftService;
use App\Services\VacancyChatContextBuilder;
use App\Services\VacancyChatService;
use App\Services\VacancyIngestionService;
use App\Services\VacancyMatchingService;
use Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VacancyPlanChatTest extends TestCase
{
    use RefreshDatabase;

    private ChatGptConnection $connection;

    private VacancyChatFakeProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['chatgpt.enabled' => true, 'app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);
        $this->provider = new VacancyChatFakeProvider;
        $this->app->instance(StreamingProvider::class, $this->provider);
    }

    public function test_stream_persists_history_run_and_saves_source_backed_structured_draft(): void
    {
        [$user, $vacancy, $snapshot] = $this->fixture('chat');
        $fact = app(CareerFactService::class)->createManual($user, 'skill', 'Production PHP development.');
        $analysis = $this->analysis([$fact->id]);
        $this->provider->text = json_encode($analysis);
        $service = app(VacancyChatService::class);
        $turn = $this->beginTurn($user, $vacancy, 'first-turn', null, true);
        $result = iterator_to_array($service->stream($user, $turn));
        $this->assertSame('completed', end($result)['type']);
        $saved = $service->show($user, $vacancy->id);
        $this->assertCount(2, $saved['messages']);
        $this->assertSame('COMPLETED', $saved['messages'][1]['status']);
        $this->assertSame($snapshot->id, $saved['messages'][1]['vacancy_snapshot_id']);
        $run = VacancyLlmRun::query()->findOrFail($saved['messages'][1]['run_id']);
        $this->assertSame('vacancy_plan_chat', $run->workflow);
        $this->assertSame('openai_chatgpt_plan', $run->provider);
        $this->assertNull($run->estimated_cost_micros);
        $this->assertSame('request-fixture', $run->provider_request_id);
        $this->actingAs($user)->postJson('/api/v1/vacancies/'.$vacancy->id.'/analysis-drafts', [
            'message_id' => $saved['messages'][1]['id'], 'client_request_id' => 'saved-message',
        ])->assertOk()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.origin', 'AI_GENERATED');
        $this->assertDatabaseCount('vacancy_analysis_draft_requirements', 1);
        $this->assertSame('Engineer. PHP is required.', $snapshot->fresh()->raw_text);
        $this->assertDatabaseCount('career_facts', 1);
        $this->assertDatabaseCount('vacancy_requirements', 0);
        $this->assertSame('CONFIRMED', $fact->fresh()->status);
        $draft = VacancyAnalysisDraft::query()->firstOrFail();
        $this->actingAs($user)->postJson('/api/v1/vacancy-analysis-drafts/'.$draft->id.'/approve')->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->assertDatabaseCount('vacancy_requirements', 1);
        $this->assertSame('COMPLETED', $vacancy->fresh()->analysis_status);
        $this->assertNotNull($draft->fresh()->approved_analysis_id);
    }

    public function test_handcrafted_api_request_rejects_a_valid_slug_missing_from_the_owned_catalog(): void
    {
        [$user, $vacancy] = $this->fixture('unknown-model');
        $this->provider->catalog = [];
        $preview = app(VacancyChatService::class)->preview($user, $vacancy->id, 'Hello', false);

        $this->actingAs($user)->postJson('/api/v1/vacancies/'.$vacancy->id.'/chat/messages', [
            'connection_id' => $this->connection->id, 'model' => 'valid-looking-model_2026',
            'client_request_id' => 'manual-bypass', 'content' => 'Hello', 'context_preview_hash' => $preview['preview_hash'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'MODEL_NOT_AVAILABLE');

        $this->assertDatabaseCount('vacancy_chat_threads', 0);
        $this->assertDatabaseCount('vacancy_chat_messages', 0);
        $this->assertDatabaseCount('vacancy_llm_runs', 0);
        $this->assertSame([], $this->provider->streamCalls);
    }

    public function test_chat_api_requires_a_context_preview_fingerprint_before_catalog_or_persistence(): void
    {
        [$user, $vacancy] = $this->fixture('missing-context-preview');

        $this->actingAs($user)->postJson('/api/v1/vacancies/'.$vacancy->id.'/chat/messages', [
            'connection_id' => $this->connection->id, 'model' => 'account-model',
            'client_request_id' => 'missing-preview', 'content' => 'Hello',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->assertSame([], $this->provider->catalogCalls);
        $this->assertSame([], $this->provider->streamCalls);
        $this->assertDatabaseCount('vacancy_chat_messages', 0);
        $this->assertDatabaseCount('vacancy_llm_runs', 0);
    }

    public function test_account_catalogs_are_connection_scoped_and_cannot_cross_authorize_a_model(): void
    {
        [$owner] = $this->fixture('catalog-owner');
        $ownerConnection = $this->connection;
        [$other, $vacancy] = $this->fixture('catalog-other');
        $otherConnection = $this->connection;
        $ownerConnection->forceFill(['access_token' => 'owner-account-access'])->save();
        $otherConnection->forceFill(['access_token' => 'other-account-access'])->save();
        $this->app->instance(StreamingProvider::class, app(OpenAiChatGptPlanProvider::class));
        Http::fake([
            'https://api.openai.com/v1/models' => fn ($request) => $request->header('Authorization') === ['Bearer owner-account-access']
                ? Http::response(['models' => [['slug' => 'shared-visible-model', 'display_name' => 'Visible', 'visibility' => 'list']]])
                : Http::response(['models' => []]),
        ]);

        $this->actingAs($owner)->getJson('/api/v1/chatgpt/connections/'.$ownerConnection->id.'/models')
            ->assertOk()->assertJsonPath('data.0.slug', 'shared-visible-model');
        $this->actingAs($other)->postJson('/api/v1/vacancies/'.$vacancy->id.'/chat/messages', [
            'connection_id' => $otherConnection->id, 'model' => 'shared-visible-model',
            'client_request_id' => 'cross-account-catalog', 'content' => 'Hello', 'context_preview_hash' => str_repeat('0', 64),
        ])->assertUnprocessable()->assertJsonPath('error.code', 'MODEL_NOT_AVAILABLE');

        $this->assertDatabaseCount('vacancy_chat_messages', 0);
        $this->assertSame([], $this->provider->streamCalls);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/models')
            && $request->header('Authorization') === ['Bearer other-account-access']);
        Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/responses'));
    }

    public function test_model_policy_rejection_is_distinct_and_does_not_call_any_provider(): void
    {
        [$user, $vacancy] = $this->fixture('policy-rejection');
        config(['ai.model_policies.user_selected_chatgpt_plan.selection' => 'fixed']);
        $preview = app(VacancyChatService::class)->preview($user, $vacancy->id, 'Hello', false);

        $this->actingAs($user)->postJson('/api/v1/vacancies/'.$vacancy->id.'/chat/messages', [
            'connection_id' => $this->connection->id, 'model' => 'account-model',
            'client_request_id' => 'policy-rejection', 'content' => 'Hello', 'context_preview_hash' => $preview['preview_hash'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'MODEL_NOT_ALLOWED');

        $this->assertSame([], $this->provider->catalogCalls);
        $this->assertSame([], $this->provider->streamCalls);
        $this->assertDatabaseCount('vacancy_chat_messages', 0);
    }

    public function test_stale_persisted_model_is_rejected_at_inference_without_provider_or_api_key_fallback(): void
    {
        [$user, $vacancy] = $this->fixture('stale-model');
        $this->app->instance(StreamingProvider::class, app(OpenAiChatGptPlanProvider::class));
        config(['ai.providers.openai.api_key' => 'sk-test-must-not-be-used']);
        Http::fakeSequence('https://api.openai.com/v1/models')
            ->push(['models' => [['slug' => 'account-model', 'display_name' => 'Current', 'visibility' => 'list']]])
            ->push(['models' => []]);

        $service = app(VacancyChatService::class);
        $turn = $this->beginTurn($user, $vacancy, 'stale-model-turn', 'Hello', false);
        $events = iterator_to_array($service->stream($user, $turn));

        $this->assertSame('MODEL_NOT_AVAILABLE', end($events)['code']);
        $this->assertSame('INTERRUPTED', $turn['assistant']->fresh()->status);
        $this->assertSame('MODEL_NOT_AVAILABLE', $turn['assistant']->fresh()->error_code);
        Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/responses'));
    }

    public function test_active_chat_cancellation_persists_interrupted_state_and_skips_inference(): void
    {
        [$user, $vacancy] = $this->fixture('cancel-chat');
        $service = app(VacancyChatService::class);
        $turn = $this->beginTurn($user, $vacancy, 'cancel-request', 'Hello', false);

        $this->actingAs($user)->postJson('/api/v1/vacancies/'.$vacancy->id.'/chat/cancel', [
            'client_request_id' => 'cancel-request',
        ])->assertOk()->assertJsonPath('cancelled', true);

        $events = iterator_to_array($service->stream($user, $turn));
        $this->assertSame('USER_CANCELLED', end($events)['code']);
        $this->assertSame([], $this->provider->streamCalls);
        $this->assertSame('INTERRUPTED', $turn['assistant']->fresh()->status);
        $this->assertSame('USER_CANCELLED', $turn['assistant']->fresh()->error_code);
        $this->assertSame('INTERRUPTED', $turn['thread']->fresh()->status);
        $this->assertSame('INTERRUPTED', VacancyLlmRun::query()->findOrFail($turn['assistant']->run_id)->status);
    }

    public function test_cancelling_during_stream_keeps_partial_output_and_does_not_complete_the_run(): void
    {
        [$user, $vacancy] = $this->fixture('cancel-active-chat');
        $service = app(VacancyChatService::class);
        $turn = $this->beginTurn($user, $vacancy, 'cancel-active-request', 'Hello', false);
        $events = $service->stream($user, $turn);

        $this->assertSame('started', $events->current()['type']);
        $events->next();
        $this->assertSame('delta', $events->current()['type']);
        $this->assertSame('Hello', $turn['assistant']->fresh()->content);
        $this->actingAs($user)->postJson('/api/v1/vacancies/'.$vacancy->id.'/chat/cancel', [
            'client_request_id' => 'cancel-active-request',
        ])->assertOk()->assertJsonPath('cancelled', true);

        $events->next();
        $this->assertSame('error', $events->current()['type']);
        $this->assertSame('USER_CANCELLED', $events->current()['code']);
        $this->assertSame('Hello', $turn['assistant']->fresh()->content);
        $this->assertSame('INTERRUPTED', $turn['assistant']->fresh()->status);
        $this->assertSame('INTERRUPTED', VacancyLlmRun::query()->findOrFail($turn['assistant']->run_id)->status);
    }

    public function test_context_is_confirmed_only_owned_relevant_bounded_and_marks_untrusted_data(): void
    {
        [$user, $vacancy] = $this->fixture('context');
        $confirmed = app(CareerFactService::class)->createManual($user, 'skill', 'PHP production work');
        foreach (['PENDING', 'REJECTED', 'DEPRECATED'] as $index => $status) {
            $fact = app(CareerFactService::class)->createManual($user, 'skill', 'PHP untrusted assertion '.$index);
            $fact->update(['status' => $status]);
        }
        app(CareerFactService::class)->createManual($user, 'skill', 'Underwater basket weaving');
        $other = $this->user('foreign');
        app(CareerFactService::class)->createManual($other, 'skill', 'PHP secret from another user');
        $thread = app(VacancyChatService::class)->open($user, $vacancy->id);
        $context = app(VacancyChatContextBuilder::class)->build($user, $thread, 'Analyze');
        $data = json_decode($context['input'][0]['content'], true);
        $this->assertSame([$confirmed->id], array_column($data['confirmed_facts'], 'id'));
        $this->assertStringContainsString('UNTRUSTED DATA', $data['boundary']);
        $this->assertStringNotContainsString('untrusted assertion', json_encode($context['input']));
        $this->assertStringNotContainsString('secret from another user', json_encode($context['input']));
        $this->assertStringNotContainsString('Underwater', json_encode($context['input']));
        $this->assertLessThan(50000, strlen(json_encode($context['input'])));
    }

    public function test_context_does_not_select_a_confirmed_fact_for_only_common_words(): void
    {
        [$user, $vacancy] = $this->fixture('common-word-fact', 'PHP engineer is required for a role with a growing team.');
        app(CareerFactService::class)->createManual($user, 'experience', 'I worked with teams and managers for many years.');
        $relevant = app(CareerFactService::class)->createManual($user, 'skill', 'Production PHP development.');
        $thread = app(VacancyChatService::class)->open($user, $vacancy->id);

        $context = app(VacancyChatContextBuilder::class)->build($user, $thread, 'Analyze this role');
        $data = json_decode($context['input'][0]['content'], true);

        $this->assertSame([$relevant->id], array_column($data['confirmed_facts'], 'id'));
        $this->assertStringNotContainsString('worked with teams and managers', json_encode($context['input']));
    }

    public function test_context_does_not_select_a_confirmed_fact_for_only_numeric_overlap(): void
    {
        [$user, $vacancy] = $this->fixture('numeric-overlap', 'The platform team expects 10 engineers in 2020, with production PHP.');
        app(CareerFactService::class)->createManual($user, 'experience', 'I supported 10 teams during 2020.');
        $relevant = app(CareerFactService::class)->createManual($user, 'skill', 'Production PHP development.');
        $thread = app(VacancyChatService::class)->open($user, $vacancy->id);

        $context = app(VacancyChatContextBuilder::class)->build($user, $thread, 'Analyze this role');
        $data = json_decode($context['input'][0]['content'], true);

        $this->assertSame([$relevant->id], array_column($data['confirmed_facts'], 'id'));
        $this->assertStringNotContainsString('supported 10 teams', json_encode($context['input']));
    }

    public function test_context_signature_uses_the_same_career_snapshot_as_selected_facts(): void
    {
        [$user, $vacancy] = $this->fixture('career-snapshot-signature', 'Engineer. PHP is required.');
        $fact = app(CareerFactService::class)->createManual($user, 'skill', 'Production PHP development.');
        $thread = app(VacancyChatService::class)->open($user, $vacancy->id);
        $realCareerQuery = app(TrustedCareerQuery::class);
        $matching = app(VacancyMatchingService::class);
        $expectedSignature = null;
        $careerQuery = \Mockery::mock(TrustedCareerQuery::class);
        $careerQuery->shouldReceive('forMatching')->once()->with($user)->andReturnUsing(function () use ($realCareerQuery, $matching, $user, $fact, &$expectedSignature): array {
            $snapshot = $realCareerQuery->forMatching($user);
            $expectedSignature = $matching->careerSignatureForContext($snapshot);
            app(CareerFactService::class)->deprecate($user, $fact);

            return $snapshot;
        });
        $context = (new VacancyChatContextBuilder($careerQuery, $matching))->build($user, $thread, 'Analyze this role');
        $data = json_decode($context['input'][0]['content'], true);

        $this->assertSame([$fact->id], array_column($data['confirmed_facts'], 'id'));
        $this->assertNotNull($expectedSignature);
        $this->assertSame($expectedSignature, $context['career_signature']);
        $this->assertNotSame($matching->careerSignature($user), $context['career_signature']);
        $this->assertSame('DEPRECATED', $fact->fresh()->status);
    }

    public function test_preview_is_the_exact_bounded_provider_input_and_stale_preview_is_rejected(): void
    {
        [$user, $vacancy] = $this->fixture('context-preview', 'Engineer. PHP is required.');
        $fact = app(CareerFactService::class)->createManual($user, 'skill', 'Production PHP development.');
        $response = $this->actingAs($user)->postJson('/api/v1/vacancies/'.$vacancy->id.'/chat/context-preview', ['content' => 'What should I emphasize?'])
            ->assertOk()->assertJsonPath('data.input.0.role', 'user');
        $this->assertStringStartsWith('no-store', (string) $response->headers->get('Cache-Control'));
        $preview = $response->json('data');
        $payload = json_encode($preview['input'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Production PHP development.', $payload);
        $this->assertStringContainsString('UNTRUSTED DATA', $payload);
        $this->assertStringContainsString('What should I emphasize?', $payload);

        $service = app(VacancyChatService::class);
        $turn = $service->begin($user, $vacancy->id, $this->connection->id, 'account-model', 'preview-exact', 'What should I emphasize?', false, $preview['preview_hash']);
        $this->assertSame($preview['input'], $turn['context']['input']);
        iterator_to_array($service->stream($user, $turn));
        $this->assertSame($preview['input'], $this->provider->inputs[0]);

        $stale = $service->preview($user, $vacancy->id, 'Another question', false);
        app(CareerFactService::class)->deprecate($user, $fact);
        $this->invalid(fn () => $service->begin($user, $vacancy->id, $this->connection->id, 'account-model',
            'preview-stale', 'Another question', false, $stale['preview_hash']));
        $this->assertSame([], array_slice($this->provider->streamCalls, 1));
        $this->assertDatabaseMissing('vacancy_chat_messages', ['client_request_id' => 'preview-stale']);
    }

    public function test_interrupted_stream_is_persisted_and_cannot_be_saved(): void
    {
        [$user, $vacancy] = $this->fixture('interrupt');
        $this->provider->interrupt = true;
        $turn = $this->beginTurn($user, $vacancy, 'interrupted-turn', 'hello', false);
        $events = iterator_to_array(app(VacancyChatService::class)->stream($user, $turn));
        $this->assertSame('error', end($events)['type']);
        $this->assertSame('INTERRUPTED', $turn['assistant']->fresh()->status);
        $this->actingAs($user)->postJson('/api/v1/vacancies/'.$vacancy->id.'/analysis-drafts', ['message_id' => $turn['assistant']->id, 'client_request_id' => 'cannot-save'])->assertNotFound();
    }

    public function test_draft_save_is_idempotent_and_schema_snapshot_and_confirmed_fact_checks_fail_closed(): void
    {
        [$user, $vacancy, $snapshot] = $this->fixture('draft');
        $service = app(VacancyAnalysisDraftService::class);
        $first = $service->save($user, $vacancy->id, $snapshot->id, 'same-request', $this->analysis());
        $again = $service->save($user, $vacancy->id, $snapshot->id, 'same-request', $this->analysis());
        $this->assertSame($first['id'], $again['id']);
        $this->assertDatabaseCount('vacancy_analysis_drafts', 1);
        $invalid = $this->analysis();
        $invalid['user_id'] = $user->id;
        $this->invalid(fn () => $service->save($user, $vacancy->id, $snapshot->id, 'unknown-field', $invalid));
        $invalid = $this->analysis();
        $invalid['requirements'][0]['source_excerpt'] = 'Invented requirement';
        $this->invalid(fn () => $service->save($user, $vacancy->id, $snapshot->id, 'invented', $invalid));
        $this->invalid(fn () => $service->save($user, $vacancy->id, $snapshot->id, 'same-request', $invalid));
        $other = $this->user('other-fact');
        $fact = app(CareerFactService::class)->createManual($other, 'skill', 'PHP');
        $this->invalid(fn () => $service->save($user, $vacancy->id, $snapshot->id, 'foreign-fact', $this->analysis([$fact->id])));
        $fact = app(CareerFactService::class)->createManual($user, 'skill', 'PHP');
        $fact->update(['status' => 'PENDING']);
        $this->invalid(fn () => $service->save($user, $vacancy->id, $snapshot->id, 'pending-fact', $this->analysis([$fact->id])));
        app(CareerFactService::class)->createManual($user, 'skill', 'A new confirmed fact');
        $this->invalid(fn () => $service->approve($user, $first['id']));
        $this->invalid(fn () => $service->save($user, $vacancy->id, str_repeat('0', 26), 'stale-source', $this->analysis()));
        $this->assertDatabaseCount('vacancy_analysis_drafts', 1);
    }

    public function test_cross_user_cannot_read_threads_add_messages_save_or_approve_analysis(): void
    {
        [$owner, $vacancy, $snapshot] = $this->fixture('owner');
        app(VacancyChatService::class)->open($owner, $vacancy->id);
        $draft = app(VacancyAnalysisDraftService::class)->save($owner, $vacancy->id, $snapshot->id, 'owner-save', $this->analysis());
        $other = $this->user('other-chat');
        $this->actingAs($other)->getJson('/api/v1/vacancies/'.$vacancy->id.'/chat')->assertNotFound();
        $this->actingAs($other)->getJson('/api/v1/vacancies/'.$vacancy->id.'/analysis-drafts')->assertNotFound();
        $this->actingAs($other)->postJson('/api/v1/vacancies/'.$vacancy->id.'/chat/messages', [
            'connection_id' => $this->connection->id, 'model' => 'model', 'client_request_id' => 'foreign', 'content' => 'hello',
            'context_preview_hash' => str_repeat('0', 64),
        ])->assertNotFound();
        $this->actingAs($other)->postJson('/api/v1/vacancies/'.$vacancy->id.'/chat/cancel', ['client_request_id' => 'owner'])->assertNotFound();
        $this->actingAs($other)->postJson('/api/v1/vacancy-analysis-drafts/'.$draft['id'].'/approve')->assertNotFound();
        $this->assertDatabaseCount('vacancy_chat_messages', 0);
    }

    public function test_chat_run_does_not_mark_requirement_extraction_as_complete_and_duplicate_turn_is_rejected(): void
    {
        [$user, $vacancy] = $this->fixture('runs');
        $service = app(VacancyChatService::class);
        $turn = $this->beginTurn($user, $vacancy, 'one-turn', 'hello', false);
        $this->invalid(fn () => $this->beginTurn($user, $vacancy, 'concurrent-turn', 'hello', false));
        iterator_to_array($service->stream($user, $turn));
        $this->invalid(fn () => $this->beginTurn($user, $vacancy, 'one-turn', 'hello', false));
        $this->assertDatabaseMissing('vacancy_llm_runs', ['workflow' => 'vacancy_requirement_extraction']);
        $this->assertSame('FAILED', $vacancy->fresh()->analysis_status);
    }

    public function test_one_confirmed_fact_can_support_multiple_requirements_but_not_repeat_within_a_match(): void
    {
        [$user, $vacancy, $snapshot] = $this->fixture('shared-fact');
        $fact = app(CareerFactService::class)->createManual($user, 'skill', 'Production PHP development.');
        $analysis = $this->analysis([$fact->id]);
        $second = $analysis['requirements'][0];
        $second['label'] = 'PHP is required';
        $analysis['requirements'][] = $second;
        $analysis['matches'][] = ['requirement_index' => 1, 'career_fact_ids' => [$fact->id]];
        $saved = app(VacancyAnalysisDraftService::class)->save($user, $vacancy->id, $snapshot->id, 'shared-fact', $analysis);
        $this->assertSame('DRAFT', $saved['status']);
        $this->assertCount(2, $saved['proposed_matches']);
        $analysis['matches'][0]['career_fact_ids'][] = $fact->id;
        $this->invalid(fn () => app(VacancyAnalysisDraftService::class)->save($user, $vacancy->id, $snapshot->id, 'duplicate-fact', $analysis));
    }

    public function test_draft_classification_uses_literal_source_section_headings(): void
    {
        [$user, $vacancy, $snapshot] = $this->fixture('headings', "Наши пожелания к кандидату:\nPHP\nБудет плюсом:\nPython\nТвои будущие задачи:\nJava");
        $analysis = $this->analysis();
        $analysis['requirements'] = array_map(fn (string $label): array => ['dimension' => 'TECHNICAL',
            'importance' => 'MANDATORY', 'label' => $label, 'normalized_value' => null,
            'source_excerpt' => $label, 'confidence' => 1], ['PHP', 'Python', 'Java']);
        $saved = app(VacancyAnalysisDraftService::class)->save($user, $vacancy->id, $snapshot->id, 'headings', $analysis);
        $this->assertSame(['MANDATORY', 'PREFERRED', 'UNCERTAIN'], array_column($saved['requirements'], 'importance'));
    }

    private function fixture(string $name, string $rawText = 'Engineer. PHP is required.'): array
    {
        $user = $this->user($name);
        $queued = app(VacancyIngestionService::class)->queue($user, $rawText, null);
        $queued['vacancy']->update(['analysis_status' => 'FAILED']);
        $this->connection = ChatGptConnection::query()->create(['owner_id' => $user->id, 'client_id' => 'oaiapp_'.$name,
            'subject' => 'subject', 'status' => 'CONNECTED', 'scopes' => ['resource.invoke', 'chatgpt.tokens.use.direct'],
            'access_token' => 'test-access', 'refresh_token' => 'test-refresh', 'expires_at' => now()->addHour()]);

        return [$user, $queued['vacancy'], $queued['snapshot']];
    }

    private function beginTurn(User $user, Vacancy $vacancy, string $requestId, ?string $content, bool $analyze): array
    {
        $service = app(VacancyChatService::class);
        $preview = $service->preview($user, $vacancy->id, $content, $analyze);

        return $service->begin($user, $vacancy->id, $this->connection->id, 'account-model', $requestId, $content, $analyze, $preview['preview_hash']);
    }

    private function user(string $name): User
    {
        return User::query()->create(['email' => $name.'@example.test', 'password' => 'synthetic-long-password'])->fresh();
    }

    private function analysis(array $factIds = []): array
    {
        return ['requirements' => [['dimension' => 'TECHNICAL', 'importance' => 'MANDATORY', 'label' => 'PHP',
            'normalized_value' => 'php', 'source_excerpt' => 'PHP is required.', 'confidence' => 0.9]],
            'matches' => $factIds === [] ? [] : [['requirement_index' => 0, 'career_fact_ids' => $factIds]],
            'gaps' => [], 'risks' => [], 'questions' => ['What is the team size?'], 'recommendations' => ['Review the confirmed PHP evidence.']];
    }

    private function invalid(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected validation failure.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }
}

class VacancyChatFakeProvider implements StreamingProvider
{
    public string $text = 'Hello';

    public bool $interrupt = false;

    /** @var list<array{slug: string, display_name: string}> */
    public array $catalog = [['slug' => 'account-model', 'display_name' => 'Account model']];

    /** @var list<array{user_id: string, connection_id: string}> */
    public array $catalogCalls = [];

    /** @var list<array{user_id: string, connection_id: string, model: string}> */
    public array $streamCalls = [];

    /** @var list<list<array{role: string, content: string}>> */
    public array $inputs = [];

    public function models(User $user, string $connectionId): array
    {
        $this->catalogCalls[] = ['user_id' => (string) $user->id, 'connection_id' => $connectionId];

        return $this->catalog;
    }

    public function stream(User $user, string $connectionId, string $model, string $instructions, array $input): Generator
    {
        $this->streamCalls[] = ['user_id' => (string) $user->id, 'connection_id' => $connectionId, 'model' => $model];
        $this->inputs[] = $input;
        yield ['type' => 'delta', 'text' => $this->text];
        if (! $this->interrupt) {
            yield ['type' => 'completed', 'request_id' => 'request-fixture'];
        }
    }
}
