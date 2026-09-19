<?php

namespace Tests\Feature;

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\AI\Data\ModelPolicy;
use App\AI\Data\ResolvedModel;
use App\AI\Exceptions\LlmProviderException;
use App\AI\ModelPolicyResolver;
use App\AI\Providers\ConfiguredLlmProvider;
use App\AI\Providers\OpenAiResponsesProvider;
use App\Models\CareerFact;
use App\Models\CareerProfile;
use App\Models\CareerSource;
use App\Models\Claim;
use App\Models\ClaimEvidence;
use App\Models\LlmRun;
use App\Models\User;
use App\Services\CareerExtractionService;
use App\Services\CareerFactService;
use App\Services\CareerOwnerChain;
use App\Services\ClaimResolutionService;
use App\Services\TruthGuard;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CareerCoreRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_provider_pricing_is_recorded_as_unknown(): void
    {
        $inputKey = 'OPENAI_CAREER_INPUT_COST_MICROS_PER_MILLION_TOKENS';
        $outputKey = 'OPENAI_CAREER_OUTPUT_COST_MICROS_PER_MILLION_TOKENS';
        $oldInput = getenv($inputKey);
        $oldOutput = getenv($outputKey);
        putenv($inputKey.'=');
        putenv($outputKey.'=');
        try {
            $config = require base_path('config/ai.php');
        } finally {
            $oldInput === false ? putenv($inputKey) : putenv($inputKey.'='.$oldInput);
            $oldOutput === false ? putenv($outputKey) : putenv($outputKey.'='.$oldOutput);
        }

        $policy = $config['model_policies']['low_cost_structured_extraction'];
        $this->assertNull($policy['input_cost_micros_per_million_tokens']);
        $this->assertNull($policy['output_cost_micros_per_million_tokens']);
    }

    public function test_complete_owner_chain_blocks_cross_owner_source_profile_claim_and_nested_idor(): void
    {
        $owner = $this->user('chain-owner@example.test');
        $other = $this->user('chain-other@example.test');
        $ownerProfile = app(CareerFactService::class)->profileFor($owner);
        $otherProfile = app(CareerFactService::class)->profileFor($other);
        $otherSource = $this->source($other, $otherProfile, 'Other private source.');

        $crossSourceFact = null;
        $crossProfileFact = null;
        if (DB::getDriverName() !== 'pgsql') {
            $crossSourceFact = $this->rawFact($owner, $ownerProfile, $otherSource, 'Other private source.');
            $crossProfileFact = $this->rawFact($owner, $otherProfile, null, 'Owner fact on another profile.', CareerFact::PROVENANCE_MANUAL);
        } else {
            // The PostgreSQL composite foreign keys are exercised by scripts/test-career-core-postgres-upgrade.sh.
            $this->addToAssertionCount(2);
        }
        $ownerClaim = Claim::query()->create(['owner_id' => $owner->id, 'statement' => 'Other private source.', 'truth_status' => TruthGuard::BLOCK]);

        foreach (array_filter([$crossSourceFact, $crossProfileFact]) as $invalidFact) {
            try {
                ClaimEvidence::link($ownerClaim, $invalidFact);
                $this->fail('An inconsistent owner chain must not link.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        if ($crossSourceFact !== null) {
            DB::table('claim_evidence')->insert([
                'id' => (string) Str::ulid(), 'owner_id' => $owner->id, 'claim_id' => $ownerClaim->id,
                'career_fact_id' => $crossSourceFact->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->assertSame(TruthGuard::BLOCK, app(TruthGuard::class)->evaluate($ownerClaim));

        $otherFact = app(CareerFactService::class)->createManual($other, 'skill', 'Other owner fact.');
        $otherClaim = Claim::query()->where('owner_id', $other->id)->where('statement', 'Other owner fact.')->sole();
        try {
            ClaimEvidence::link($ownerClaim, $otherFact);
            $this->fail('A claim must not link another owner fact.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->actingAs($owner)->getJson('/api/v1/career/sources/'.$otherSource->id)->assertNotFound();
        $this->actingAs($owner)->patchJson('/api/v1/career/facts/'.$otherFact->id.'/review', ['action' => 'confirm'])->assertNotFound();
        $this->actingAs($owner)->postJson('/api/v1/career/facts/'.$otherFact->id.'/supersede', [
            'fact_type' => 'skill', 'assertion' => 'Stolen replacement.',
        ])->assertNotFound();
        $this->actingAs($owner)->patchJson('/api/v1/career/claims/'.$otherClaim->id.'/resolve')->assertNotFound();
        if ($crossSourceFact !== null) {
            $this->actingAs($owner)->patchJson('/api/v1/career/facts/'.$crossSourceFact->id.'/review', ['action' => 'confirm'])
                ->assertUnprocessable();
        }
    }

    public function test_api_review_flow_records_human_actions_and_keeps_one_candidate_pending(): void
    {
        $user = $this->user('api-review@example.test');
        $provider = new RecordingProvider([['facts' => [
            $this->candidate('Confirmed source wording.'),
            $this->candidate('Editable source wording.'),
            $this->candidate('Rejected source wording.'),
            $this->candidate('Left pending wording.'),
        ]]]);
        $this->app->instance(LlmProvider::class, $provider);

        $this->actingAs($user)->postJson('/api/v1/career/extractions', [
            'source_text' => 'Confirmed source wording. Editable source wording. Rejected source wording. Left pending wording.',
        ])->assertAccepted();

        $facts = CareerFact::query()->where('owner_id', $user->id)->orderBy('assertion_original')->get()->keyBy('assertion_original');
        $this->actingAs($user)->patchJson('/api/v1/career/facts/'.$facts['Confirmed source wording.']->id.'/review', ['action' => 'confirm'])->assertOk();
        $this->actingAs($user)->patchJson('/api/v1/career/facts/'.$facts['Editable source wording.']->id.'/review', [
            'action' => 'edit_confirm', 'assertion' => 'Human-approved separate wording.',
        ])->assertOk();
        $this->actingAs($user)->patchJson('/api/v1/career/facts/'.$facts['Rejected source wording.']->id.'/review', ['action' => 'reject'])->assertOk();

        $edited = $facts['Editable source wording.']->fresh();
        $confirmed = $facts['Confirmed source wording.']->fresh();
        $this->assertSame(CareerFact::STATUS_CONFIRMED, $confirmed->status);
        $this->assertSame(CareerFact::PROVENANCE_EXTRACTION, $confirmed->provenance_type);
        $this->assertSame($user->id, $confirmed->reviewed_by);
        $this->assertNotNull($confirmed->reviewed_at);
        $this->assertSame('Editable source wording.', $edited->assertion_original);
        $this->assertSame('Editable source wording.', $edited->source_excerpt);
        $this->assertSame('Human-approved separate wording.', $edited->assertion_approved);
        $this->assertSame($user->id, $edited->reviewed_by);
        $this->assertNotNull($edited->reviewed_at);
        $this->assertSame(CareerFact::STATUS_REJECTED, $facts['Rejected source wording.']->fresh()->status);
        $this->assertSame($user->id, $facts['Rejected source wording.']->fresh()->reviewed_by);
        $this->assertNotNull($facts['Rejected source wording.']->fresh()->reviewed_at);
        $rejectedClaim = Claim::query()->create([
            'owner_id' => $user->id,
            'statement' => 'Rejected source wording.',
            'truth_status' => TruthGuard::BLOCK,
        ]);
        try {
            ClaimEvidence::link($rejectedClaim, $facts['Rejected source wording.']->fresh());
            $this->fail('Rejected evidence must remain unusable.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(CareerFact::STATUS_PENDING, $facts['Left pending wording.']->fresh()->status);
        $this->assertDatabaseHas('claims', ['statement' => 'Human-approved separate wording.', 'truth_status' => TruthGuard::PASS]);
    }

    public function test_trusted_matching_query_exposes_only_current_owner_valid_confirmed_pass_data(): void
    {
        $owner = $this->user('trusted-owner@example.test');
        $other = $this->user('trusted-other@example.test');
        $confirmed = app(CareerFactService::class)->createManual($owner, 'skill', 'Trusted current fact.');
        $deprecated = app(CareerFactService::class)->createManual($owner, 'skill', 'Deprecated fact.');
        app(CareerFactService::class)->deprecate($owner, $deprecated);
        $pending = $this->pendingFact($owner, 'Pending fact.');
        $rejected = $this->pendingFact($owner, 'Rejected fact.');
        app(CareerFactService::class)->review($owner, $rejected, 'reject');
        app(CareerFactService::class)->createManual($other, 'skill', 'Other private fact.');
        Claim::query()->create(['owner_id' => $owner->id, 'statement' => 'Unsupported claim.', 'truth_status' => TruthGuard::BLOCK]);

        $response = $this->actingAs($owner)->getJson('/api/v1/career/trusted')->assertOk();
        $response->assertJsonPath('data.facts.0.id', $confirmed->id)
            ->assertJsonCount(1, 'data.facts')
            ->assertJsonCount(1, 'data.claims')
            ->assertJsonMissing(['id' => $pending->id])
            ->assertJsonMissing(['id' => $rejected->id])
            ->assertJsonMissing(['id' => $deprecated->id])
            ->assertJsonMissing(['statement' => 'Unsupported claim.'])
            ->assertJsonMissing(['assertion_original' => 'Other private fact.']);
    }

    public function test_superseding_confirmed_fact_preserves_history_and_invalidates_stale_claim(): void
    {
        $user = $this->user('supersede@example.test');
        $original = app(CareerFactService::class)->createManual($user, 'skill', 'Original historical assertion.');
        $originalReviewedBy = $original->reviewed_by;
        $originalReviewedAt = $original->reviewed_at;
        $oldClaim = Claim::query()->where('statement', 'Original historical assertion.')->sole();

        $response = $this->actingAs($user)->postJson('/api/v1/career/facts/'.$original->id.'/supersede', [
            'fact_type' => 'skill', 'assertion' => 'Corrected current assertion.',
        ])->assertCreated();
        $replacementId = $response->json('data.id');

        $this->assertSame(CareerFact::STATUS_DEPRECATED, $original->fresh()->status);
        $this->assertSame('Original historical assertion.', $original->fresh()->assertion_original);
        $this->assertSame($originalReviewedBy, $original->fresh()->reviewed_by);
        $this->assertTrue($originalReviewedAt->equalTo($original->fresh()->reviewed_at));
        $this->assertDatabaseHas('career_facts', [
            'id' => $replacementId, 'owner_id' => $user->id, 'supersedes_fact_id' => $original->id,
            'status' => CareerFact::STATUS_CONFIRMED, 'reviewed_by' => $user->id,
        ]);
        $this->assertSame(TruthGuard::BLOCK, app(TruthGuard::class)->evaluate($oldClaim->fresh()));
        $this->actingAs($user)->getJson('/api/v1/career')->assertOk()
            ->assertJsonFragment(['id' => $original->id, 'status' => CareerFact::STATUS_DEPRECATED]);
        $this->actingAs($user)->getJson('/api/v1/career/trusted')->assertOk()
            ->assertJsonCount(1, 'data.facts')
            ->assertJsonPath('data.facts.0.id', $replacementId)
            ->assertJsonMissing(['id' => $original->id]);

        $this->actingAs($user)->postJson('/api/v1/career/facts/'.$original->id.'/supersede', [
            'fact_type' => 'skill', 'assertion' => 'Losing duplicate replacement.',
        ])->assertConflict();

        $next = app(CareerFactService::class)->supersede($user, CareerFact::query()->findOrFail($replacementId), 'skill', 'Second historical correction.');
        $this->assertSame($replacementId, $next->supersedes_fact_id);
        $this->actingAs($user)->getJson('/api/v1/career/trusted')->assertOk()
            ->assertJsonCount(1, 'data.facts')
            ->assertJsonPath('data.facts.0.id', $next->id);
    }

    public function test_truth_guard_has_distinct_pass_block_and_valid_evidence_resolution_outcomes(): void
    {
        $user = $this->user('resolution@example.test');
        $fact = app(CareerFactService::class)->createManual($user, 'skill', 'Ambiguous but supported wording.');
        $claim = Claim::query()->where('statement', $fact->approvedAssertion())->sole();
        $secondFact = app(CareerFactService::class)->createManual($user, 'experience', 'Ambiguous but supported wording.');
        $unsupported = Claim::query()->create(['owner_id' => $user->id, 'statement' => 'No evidence.', 'truth_status' => TruthGuard::BLOCK]);

        $this->assertSame(TruthGuard::USER_RESOLUTION_REQUIRED, app(TruthGuard::class)->evaluate($claim->fresh()));
        app(CareerFactService::class)->createManual($user, 'education', 'Ambiguous but supported wording.');
        $this->assertSame(TruthGuard::USER_RESOLUTION_REQUIRED, app(TruthGuard::class)->evaluate($claim->fresh()));
        $this->assertDatabaseCount('claim_evidence', 3);
        $this->assertSame(TruthGuard::BLOCK, app(TruthGuard::class)->evaluate($unsupported));
        $this->assertSame(TruthGuard::USER_RESOLUTION_REQUIRED, app(TruthGuard::class)->evaluate($claim->fresh()));
        $foreignUser = $this->user('resolution-foreign@example.test');
        $foreignFact = app(CareerFactService::class)->createManual($foreignUser, 'skill', 'Ambiguous but supported wording.');
        $this->actingAs($user)->patchJson('/api/v1/career/claims/'.$claim->id.'/resolve', [
            'career_fact_id' => $foreignFact->id,
        ])->assertNotFound();
        $unrelatedFact = app(CareerFactService::class)->createManual($user, 'skill', 'Unrelated valid fact.');
        $this->actingAs($user)->patchJson('/api/v1/career/claims/'.$claim->id.'/resolve', [
            'career_fact_id' => $unrelatedFact->id,
        ])->assertUnprocessable();
        $this->actingAs($user)->patchJson('/api/v1/career/claims/'.$claim->id.'/resolve', [
            'career_fact_id' => $secondFact->id,
        ])->assertOk()->assertJsonPath('data.resolved_career_fact_id', $secondFact->id);
        $resolved = $claim->fresh();
        $this->assertSame($user->id, $resolved->resolved_by);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertSame(TruthGuard::PASS, app(TruthGuard::class)->evaluate($resolved));
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'claim.valid_evidence_ambiguity_resolved',
            'actor_user_id' => $user->id,
            'subject_id' => $claim->id,
        ]);

        $this->expectException(ValidationException::class);
        app(ClaimResolutionService::class)->requireValidEvidenceResolution($unsupported);
    }

    public function test_manual_api_never_invokes_llm_and_records_actor_time_and_manual_provenance(): void
    {
        $provider = new FailOnCallProvider;
        $this->app->instance(LlmProvider::class, $provider);
        $user = $this->user('manual-independent@example.test');

        $this->actingAs($user)->postJson('/api/v1/career/facts/manual', [
            'fact_type' => 'skill', 'assertion' => 'Synthetic manual fact.',
        ])->assertCreated()->assertJsonPath('data.status', CareerFact::STATUS_CONFIRMED);

        $fact = CareerFact::query()->where('owner_id', $user->id)->sole();
        $this->assertFalse($provider->called);
        $this->assertSame(CareerFact::PROVENANCE_MANUAL, $fact->provenance_type);
        $this->assertSame($user->id, $fact->reviewed_by);
        $this->assertNotNull($fact->reviewed_at);
    }

    public function test_unconfigured_ai_fails_extraction_safely_but_manual_entry_still_works(): void
    {
        config([
            'ai.model_policies.low_cost_structured_extraction.provider' => 'none',
            'ai.model_policies.low_cost_structured_extraction.model' => '',
        ]);
        $this->app->forgetInstance(LlmProvider::class);
        Queue::fake();
        $user = $this->user('unconfigured-ai@example.test');

        $this->actingAs($user)->postJson('/api/v1/career/extractions', [
            'source_text' => 'Synthetic extraction unavailable text.',
        ])->assertAccepted()->assertJsonPath('data.extraction_status', CareerSource::STATUS_PENDING);
        try {
            app(CareerExtractionService::class)->extract($user, 'Synthetic extraction unavailable text.');
            $this->fail('An unconfigured provider must fail in the worker path.');
        } catch (LlmProviderException $exception) {
            $this->assertSame(LlmProviderException::NOT_CONFIGURED, $exception->category);
        }
        $this->assertDatabaseMissing('career_facts', ['owner_id' => $user->id]);
        $this->assertDatabaseHas('llm_runs', [
            'owner_id' => $user->id,
            'status' => 'FAILED',
            'validation_result' => 'NOT_VALIDATED',
            'error_category' => LlmProviderException::NOT_CONFIGURED,
        ]);

        $this->actingAs($user)->postJson('/api/v1/career/facts/manual', [
            'fact_type' => 'skill', 'assertion' => 'Manual entry survives without AI.',
        ])->assertCreated()->assertJsonPath('data.status', CareerFact::STATUS_CONFIRMED);
        $this->assertDatabaseHas('career_facts', [
            'owner_id' => $user->id,
            'assertion_original' => 'Manual entry survives without AI.',
            'provenance_type' => CareerFact::PROVENANCE_MANUAL,
        ]);
    }

    public function test_deprecated_fact_cannot_become_claim_evidence(): void
    {
        $user = $this->user('deprecated-evidence@example.test');
        $fact = app(CareerFactService::class)->createManual($user, 'skill', 'Deprecated evidence.');
        app(CareerFactService::class)->deprecate($user, $fact);
        $claim = Claim::query()->create(['owner_id' => $user->id, 'statement' => 'Deprecated evidence.', 'truth_status' => TruthGuard::BLOCK]);

        $this->expectException(ValidationException::class);
        ClaimEvidence::link($claim, $fact->fresh());
    }

    public function test_candidate_persistence_identity_prevents_duplicate_retry_rows(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // PostgreSQL constraint failure is exercised outside the test transaction by the upgrade harness.
            $this->addToAssertionCount(1);

            return;
        }

        $user = $this->user('candidate-race@example.test');
        $profile = app(CareerFactService::class)->profileFor($user);
        $source = $this->source($user, $profile, 'Race-safe source.');
        $attributes = [
            'owner_id' => $user->id, 'career_profile_id' => $profile->id, 'career_source_id' => $source->id,
            'provenance_type' => CareerFact::PROVENANCE_EXTRACTION, 'fact_type' => 'experience',
            'assertion_original' => 'Race-safe source.', 'source_excerpt' => 'Race-safe source.',
            'extracted_by' => 'synthetic@1', 'candidate_hash' => 'same-candidate-hash', 'status' => CareerFact::STATUS_PENDING,
        ];
        CareerFact::query()->create($attributes);

        $this->expectException(QueryException::class);
        CareerFact::query()->create($attributes);
    }

    public function test_model_policy_resolves_provider_and_model_from_configuration(): void
    {
        config([
            'ai.model_policies.extract' => ['provider' => 'openai', 'model' => 'extract-model'],
            'ai.model_policies.reason' => ['provider' => 'openai', 'model' => 'reason-model'],
        ]);
        $resolver = app(ModelPolicyResolver::class);

        $this->assertSame('extract-model', $resolver->resolve(new ModelPolicy('extract', true))->model);
        $this->assertSame('reason-model', $resolver->resolve(new ModelPolicy('reason', true))->model);
    }

    public function test_provider_transport_types_do_not_leak_into_domain_or_application_services(): void
    {
        foreach ([
            CareerExtractionService::class,
            CareerFactService::class,
            CareerOwnerChain::class,
            TruthGuard::class,
        ] as $class) {
            $source = file_get_contents((new \ReflectionClass($class))->getFileName());
            $this->assertIsString($source);
            $this->assertStringNotContainsString('Illuminate\\Http\\Client', $source);
            $this->assertStringNotContainsString('OpenAiResponsesProvider', $source);
        }
    }

    public function test_configured_policy_drives_adapter_and_completed_llm_run_metadata(): void
    {
        config([
            'ai.providers.openai.api_key' => 'synthetic-key',
            'ai.providers.openai.base_url' => 'https://policy.openai.test/v1',
            'ai.model_policies.low_cost_structured_extraction' => [
                'provider' => 'openai',
                'model' => 'policy-selected-model',
                'input_cost_micros_per_million_tokens' => 1_000_000,
                'output_cost_micros_per_million_tokens' => 1_000_000,
            ],
        ]);
        Http::fake(['policy.openai.test/v1/responses' => Http::response([
            'status' => 'completed',
            'model' => 'provider-resolved-model',
            'output' => [['content' => [['type' => 'output_text', 'text' => '{"facts":[]}']]]],
            'usage' => ['input_tokens' => 2, 'output_tokens' => 3],
        ], 200, ['x-request-id' => 'req-policy'])]);
        $this->app->instance(LlmProvider::class, app(ConfiguredLlmProvider::class));
        $user = $this->user('policy-run@example.test');

        app(CareerExtractionService::class)->extract($user, 'Synthetic policy source.');

        Http::assertSent(fn (Request $request): bool => $request['model'] === 'policy-selected-model');
        $this->assertDatabaseHas('llm_runs', [
            'owner_id' => $user->id,
            'workflow' => 'career_text_extraction',
            'skill_id' => 'career.fact-extraction',
            'skill_version' => '1.0.0',
            'prompt_version' => '1.0.0',
            'model_policy' => 'low_cost_structured_extraction',
            'provider' => 'openai',
            'model' => 'provider-resolved-model',
            'provider_request_id' => 'req-policy',
            'status' => 'COMPLETED',
            'input_tokens' => 2,
            'output_tokens' => 3,
            'retry_count' => 0,
            'validation_result' => 'PASS',
            'error_category' => null,
            'estimated_cost_micros' => 5,
        ]);
        $this->assertNotNull(LlmRun::query()->where('owner_id', $user->id)->sole()->latency_ms);
    }

    public function test_provider_refusal_incomplete_malformed_and_transport_fail_safely_with_run_metadata(): void
    {
        config(['ai.providers.openai.api_key' => 'synthetic-key']);
        $cases = [
            LlmProviderException::REFUSAL => ['status' => 'completed', 'output' => [['content' => [['type' => 'refusal', 'refusal' => 'no']]]]],
            LlmProviderException::INCOMPLETE => ['status' => 'incomplete', 'output' => []],
            LlmProviderException::MALFORMED_OUTPUT => ['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => '{bad']]]]],
        ];

        $sequence = Http::sequence();
        $caseNumber = 0;
        foreach ($cases as $body) {
            $caseNumber++;
            $body['usage'] = ['input_tokens' => 2, 'output_tokens' => 3];
            $sequence->push($body, 200, ['x-request-id' => 'req-failure-'.$caseNumber]);
        }
        $sequence->push([
            'error' => ['code' => 'synthetic_provider_failure'],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 0],
        ], 500, ['x-request-id' => 'req-provider-failure']);
        $sequence->pushFailedConnection('Synthetic connection failure.');
        Http::fake(['*' => $sequence]);
        $caseNumber = 0;
        foreach ($cases as $category => $body) {
            $caseNumber++;
            $this->app->instance(LlmProvider::class, new ResolvedOpenAiTestProvider(app(OpenAiResponsesProvider::class), 'synthetic-model'));
            $user = $this->user('provider-'.strtolower($category).'@example.test');
            try {
                app(CareerExtractionService::class)->extract($user, 'Synthetic private source '.$category.'.');
                $this->fail($category.' must fail.');
            } catch (LlmProviderException $exception) {
                $this->assertSame($category, $exception->category);
            }
            $this->assertDatabaseMissing('career_facts', ['owner_id' => $user->id]);
            $this->assertDatabaseHas('llm_runs', [
                'owner_id' => $user->id, 'status' => 'FAILED', 'provider' => 'openai',
                'model' => 'synthetic-model', 'validation_result' => 'NOT_VALIDATED', 'error_category' => $category,
                'provider_request_id' => 'req-failure-'.$caseNumber, 'input_tokens' => 2, 'output_tokens' => 3,
            ]);
            $this->assertNotNull(LlmRun::query()->where('owner_id', $user->id)->sole()->latency_ms);
        }

        $this->app->instance(LlmProvider::class, new ResolvedOpenAiTestProvider(app(OpenAiResponsesProvider::class), 'synthetic-model'));
        $user = $this->user('provider-error@example.test');
        try {
            app(CareerExtractionService::class)->extract($user, 'Synthetic provider failure source.');
            $this->fail('Provider failure must fail.');
        } catch (LlmProviderException $exception) {
            $this->assertSame(LlmProviderException::PROVIDER, $exception->category);
        }
        $this->assertDatabaseMissing('career_facts', ['owner_id' => $user->id]);
        $this->assertDatabaseHas('llm_runs', [
            'owner_id' => $user->id,
            'error_category' => LlmProviderException::PROVIDER,
            'provider_request_id' => 'req-provider-failure',
            'input_tokens' => 5,
            'output_tokens' => 0,
        ]);
        $this->assertNotNull(LlmRun::query()->where('owner_id', $user->id)->sole()->latency_ms);

        $this->app->instance(LlmProvider::class, new ResolvedOpenAiTestProvider(app(OpenAiResponsesProvider::class), 'synthetic-model'));
        $user = $this->user('provider-transport@example.test');
        try {
            app(CareerExtractionService::class)->extract($user, 'Synthetic transport source.');
            $this->fail('Transport failure must fail.');
        } catch (LlmProviderException $exception) {
            $this->assertSame(LlmProviderException::TRANSPORT, $exception->category);
        }
        $this->assertDatabaseMissing('career_facts', ['owner_id' => $user->id]);
        $this->assertDatabaseHas('llm_runs', ['owner_id' => $user->id, 'error_category' => LlmProviderException::TRANSPORT]);
        $this->assertNotNull(LlmRun::query()->where('owner_id', $user->id)->sole()->latency_ms);
    }

    public function test_career_api_and_logs_redact_private_content_on_persistence_failure(): void
    {
        Log::spy();
        $private = 'SYNTHETIC-PRIVATE-CAREER-TEXT-9f3d';
        $user = $this->user('redaction@example.test');
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER fail_career_source_insert BEFORE INSERT ON career_sources BEGIN SELECT RAISE(ABORT, 'synthetic persistence failure'); END");
        } else {
            $mock = \Mockery::mock(CareerExtractionService::class);
            $mock->shouldReceive('queue')->once()->andThrow(new \RuntimeException('Persistence failed around '.$private));
            $this->app->instance(CareerExtractionService::class, $mock);
        }

        $response = $this->actingAs($user)->postJson('/api/v1/career/extractions', ['source_text' => $private])
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'CAREER_OPERATION_FAILED');
        $this->assertStringNotContainsString($private, $response->getContent());
        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) use ($private): bool {
            return $message === 'career.operation_failed'
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), $private);
        });
    }

    public function test_provider_failure_response_and_logs_do_not_expose_private_source(): void
    {
        Log::spy();
        Queue::fake();
        $private = 'SYNTHETIC-PROVIDER-PRIVATE-TEXT-31af';
        $this->app->instance(LlmProvider::class, new SensitiveFailureProvider($private));
        $user = $this->user('provider-redaction@example.test');

        $response = $this->actingAs($user)->postJson('/api/v1/career/extractions', ['source_text' => $private])
            ->assertAccepted();
        try {
            app(CareerExtractionService::class)->extract($user, $private);
            $this->fail('The provider should fail in the worker path.');
        } catch (LlmProviderException) {
            $this->addToAssertionCount(1);
        }
        $this->assertStringNotContainsString($private, $response->getContent());
        Log::shouldNotHaveReceived('error');
    }

    public function test_career_http_500_message_is_redacted_from_response_and_logs(): void
    {
        Log::spy();
        $private = 'SYNTHETIC-HTTP-PRIVATE-TEXT-82cd';
        $mock = \Mockery::mock(CareerExtractionService::class);
        $mock->shouldReceive('queue')->once()->andThrow(new HttpException(500, 'Server failure around '.$private));
        $this->app->instance(CareerExtractionService::class, $mock);
        $user = $this->user('http-redaction@example.test');

        $response = $this->actingAs($user)->postJson('/api/v1/career/extractions', ['source_text' => $private])
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'CAREER_OPERATION_FAILED');
        $this->assertStringNotContainsString($private, $response->getContent());
        Log::shouldNotHaveReceived('error');
    }

    /** @return array{fact_type: string, assertion: string, source_excerpt: string, confidence: float} */
    private function candidate(string $assertion): array
    {
        return ['fact_type' => 'experience', 'assertion' => $assertion, 'source_excerpt' => $assertion, 'confidence' => 0.8];
    }

    private function user(string $email): User
    {
        return User::query()->create(['email' => $email, 'password' => 'a very long safe passphrase'])->fresh();
    }

    private function source(User $user, CareerProfile $profile, string $text): CareerSource
    {
        return CareerSource::query()->create([
            'owner_id' => $user->id, 'career_profile_id' => $profile->id, 'kind' => 'PASTED_TEXT',
            'source_text' => $text, 'content_hash' => hash('sha256', $text.Str::ulid()), 'extraction_status' => 'COMPLETED',
        ]);
    }

    private function rawFact(User $owner, CareerProfile $profile, ?CareerSource $source, string $assertion, string $provenance = CareerFact::PROVENANCE_EXTRACTION): CareerFact
    {
        return CareerFact::query()->create([
            'owner_id' => $owner->id, 'career_profile_id' => $profile->id, 'career_source_id' => $source?->id,
            'provenance_type' => $provenance, 'fact_type' => 'experience', 'assertion_original' => $assertion,
            'source_excerpt' => $assertion, 'extracted_by' => 'synthetic@1', 'candidate_hash' => hash('sha256', $assertion.Str::ulid()),
            'status' => CareerFact::STATUS_PENDING,
        ]);
    }

    private function pendingFact(User $user, string $assertion): CareerFact
    {
        $profile = app(CareerFactService::class)->profileFor($user);

        return $this->rawFact($user, $profile, $this->source($user, $profile, $assertion), $assertion);
    }
}

class RecordingProvider implements LlmProvider
{
    /** @var list<LlmRequest> */
    public array $requests = [];

    /** @param list<array<string, mixed>> $outputs */
    public function __construct(private array $outputs) {}

    public function generateStructured(LlmRequest $request): LlmResponse
    {
        $this->requests[] = $request;

        return new LlmResponse(array_shift($this->outputs) ?? ['facts' => []], 'synthetic', 'synthetic-model', 7, 3, 1, 'req-test');
    }
}

class FailOnCallProvider implements LlmProvider
{
    public bool $called = false;

    public function generateStructured(LlmRequest $request): LlmResponse
    {
        $this->called = true;
        throw new \RuntimeException('Provider must not be called.');
    }
}

class ResolvedOpenAiTestProvider implements LlmProvider
{
    public function __construct(
        private readonly OpenAiResponsesProvider $adapter,
        private readonly string $model,
    ) {}

    public function generateStructured(LlmRequest $request): LlmResponse
    {
        return $this->adapter->generateResolved(
            $request,
            new ResolvedModel($request->modelPolicy->id, 'openai', $this->model),
        );
    }
}

class SensitiveFailureProvider implements LlmProvider
{
    public function __construct(private readonly string $private) {}

    public function generateStructured(LlmRequest $request): LlmResponse
    {
        throw new LlmProviderException(
            LlmProviderException::PROVIDER,
            'Provider failed while processing '.$this->private,
            'synthetic',
            'synthetic-model',
        );
    }
}
