<?php

namespace Tests\Feature;

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\Models\ApplicationPreparation;
use App\Models\User;
use App\Models\VacancyAnalysis;
use App\Services\ApplicationPreparationService;
use App\Services\CareerFactService;
use App\Services\DatabaseOwnerContext;
use App\Services\VacancyAnalysisService;
use App\Services\VacancyIngestionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApplicationPostgresSecurityTest extends TestCase
{
    private string $runId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runId = Str::lower((string) Str::ulid());
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Application owner-boundary suite requires PostgreSQL.');
        }
    }

    public function test_runtime_role_forces_rls_and_composite_owner_links(): void
    {
        Queue::fake();
        $this->app->instance(LlmProvider::class, new ApplicationPostgresFakeProvider);
        $context = app(DatabaseOwnerContext::class);
        $owner = $this->user('owner@example.test');
        $other = $this->user('other@example.test');
        $context->run((string) $owner->id, fn () => app(CareerFactService::class)->createManual($owner, 'skill', 'Laravel'));
        $otherFact = $context->run((string) $other->id, fn () => app(CareerFactService::class)->createManual($other, 'skill', 'Led a team of 100 people.'));
        $otherClaimId = (string) $context->run((string) $other->id, fn () => DB::table('claim_evidence')
            ->where('career_fact_id', $otherFact->id)->value('claim_id'));
        $this->assertNotSame('', $otherClaimId);

        $ownerVacancy = app(VacancyIngestionService::class)->queue($owner, 'Backend Engineer. Laravel is required.', null);
        $otherVacancy = app(VacancyIngestionService::class)->queue($other, 'Backend Engineer. Laravel is required.', null);
        $ownerAnalysis = app(VacancyAnalysisService::class)->analyze($owner, $ownerVacancy['snapshot']);
        $otherAnalysis = app(VacancyAnalysisService::class)->analyze($other, $otherVacancy['snapshot']);
        $this->ensureMatchedClaim($context, $owner, $ownerAnalysis);
        $this->ensureMatchedClaim($context, $other, $otherAnalysis);
        $preparation = $context->run((string) $owner->id, fn (): ApplicationPreparation => app(ApplicationPreparationService::class)->open($owner, (string) $ownerVacancy['vacancy']->id));
        $resource = $context->run((string) $owner->id, fn (): array => app(ApplicationPreparationService::class)->generate($owner, $preparation));
        $draftId = (string) collect($resource['items'])->first()['id'];
        $otherPreparation = $context->run((string) $other->id, fn (): ApplicationPreparation => app(ApplicationPreparationService::class)->open($other, (string) $otherVacancy['vacancy']->id));
        $otherResource = $context->run((string) $other->id, fn (): array => app(ApplicationPreparationService::class)->generate($other, $otherPreparation));
        $otherDraftId = (string) collect($otherResource['items'])->first()['id'];

        $role = DB::selectOne('SELECT current_user AS role, rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user');
        $this->assertSame(config('database.runtime_role'), $role->role);
        $this->assertFalse($role->rolsuper);
        $this->assertFalse($role->rolbypassrls);

        $tables = ['application_preparations', 'application_draft_items', 'application_claim_usages', 'application_approval_events', 'application_llm_runs'];
        $flags = collect(DB::select("SELECT relname, relrowsecurity, relforcerowsecurity FROM pg_class WHERE relnamespace = 'public'::regnamespace AND relname LIKE 'application_%' AND relkind = 'r'"))->keyBy('relname');
        foreach ($tables as $table) {
            $this->assertTrue($flags[$table]->relrowsecurity, $table.' must have RLS enabled.');
            $this->assertTrue($flags[$table]->relforcerowsecurity, $table.' must force RLS.');
            $this->assertSame(0, DB::table($table)->count(), $table.' must fail closed without owner context.');
        }

        $context->run((string) $owner->id, function () use ($owner, $other, $preparation, $otherVacancy, $otherClaimId, $draftId, $otherDraftId): void {
            $this->assertSame(1, DB::table('application_preparations')->count());
            $this->assertSame(3, DB::table('application_draft_items')->count());
            $this->assertSame(0, DB::table('application_approval_events')->count());
            $this->assertNotNull(DB::table('application_preparations')->where('id', $preparation->id)->first());
            $this->assertSame(0, DB::table('application_preparations')->where('owner_id', $other->id)->count());
            $this->assertNull(DB::table('application_draft_items')->where('id', $otherDraftId)->first());
            $this->assertSame(0, DB::table('application_draft_items')->where('id', $otherDraftId)->update(['status' => 'APPROVED']));

            try {
                DB::table('application_preparations')->insert([
                    'id' => (string) Str::ulid(), 'owner_id' => $owner->id, 'vacancy_id' => $otherVacancy['vacancy']->id,
                    'vacancy_snapshot_id' => $otherVacancy['snapshot']->id,
                    'vacancy_analysis_id' => DB::table('vacancy_analyses')->where('vacancy_id', $otherVacancy['vacancy']->id)->value('id'),
                    'career_signature' => str_repeat('0', 64), 'status' => 'DRAFT', 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->fail('A preparation linked to another owner vacancy was accepted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }

            try {
                DB::table('application_claim_usages')->insert([
                    'id' => (string) Str::ulid(), 'owner_id' => $owner->id, 'draft_item_id' => $draftId,
                    'claim_id' => $otherClaimId, 'assertion_text' => 'Foreign claim', 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->fail('A draft linked to another owner Claim was accepted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        });

        $this->assertSame(0, DB::table('application_preparations')->count(), 'Owner context leaked after scoped work.');
    }

    private function user(string $email): User
    {
        [$localPart, $domain] = explode('@', $email, 2);

        return User::query()->create([
            'email' => $localPart.'+'.$this->runId.'@'.$domain,
            'password' => 'a very long safe passphrase',
        ])->fresh();
    }

    private function ensureMatchedClaim(DatabaseOwnerContext $context, User $user, VacancyAnalysis $analysis): void
    {
        $context->run((string) $user->id, function () use ($user, $analysis): void {
            $dimensionId = DB::table('vacancy_match_dimensions')->where('owner_id', $user->id)
                ->where('vacancy_analysis_id', $analysis->id)->value('id');
            $claimId = DB::table('claims')->where('owner_id', $user->id)->value('id');
            $this->assertNotNull($dimensionId);
            $this->assertNotNull($claimId);
            DB::table('vacancy_match_evidence')->insert([
                'id' => (string) Str::ulid(),
                'owner_id' => $user->id,
                'vacancy_match_dimension_id' => $dimensionId,
                'career_fact_id' => null,
                'claim_id' => $claimId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}

class ApplicationPostgresFakeProvider implements LlmProvider
{
    public function generateStructured(LlmRequest $request): LlmResponse
    {
        if ($request->schemaName === 'vacancy_requirements') {
            $output = ['requirements' => [[
                'dimension' => 'TECHNICAL', 'importance' => 'MANDATORY', 'label' => 'Laravel',
                'normalized_value' => 'laravel', 'source_excerpt' => 'Laravel is required.', 'confidence' => 0.9,
            ]]];
        } elseif ($request->schemaName === 'application_drafts') {
            $input = json_decode($request->untrustedSourceText, true, flags: JSON_THROW_ON_ERROR);
            $claim = $input['confirmed_claims'][0];
            $requirement = $input['vacancy']['requirements'][0];
            $item = ['assertion' => 'Built Laravel APIs for backend services.', 'claim_ids' => [$claim['id']]];
            $cover = ['assertion' => 'I built Laravel APIs.', 'claim_ids' => [$claim['id']]];
            $output = [
                'recommendations' => [[
                    'requirement_id' => $requirement['id'], 'section' => 'Experience', 'before' => $claim['statement'],
                    'after' => $item['assertion'], 'reason' => 'The confirmed experience matches the requirement.',
                    'risk' => 'Keep the claim within confirmed API experience.', 'claim_usages' => [$item],
                ]],
                'short_cover' => ['content' => $cover['assertion'], 'claim_usages' => [$cover]],
                'standard_cover' => ['content' => $cover['assertion'].' I can contribute backend experience.', 'claim_usages' => [$cover]],
            ];
        } elseif ($request->schemaName === 'application_truth_review') {
            $input = json_decode($request->untrustedSourceText, true, flags: JSON_THROW_ON_ERROR);
            $output = ['status' => 'PASS', 'assertions' => array_map(
                fn (array $usage): array => ['text' => $usage['assertion'], 'claim_ids' => $usage['claim_ids']],
                $input['proposed_claim_usages'],
            )];
        } else {
            throw new \LogicException('Unexpected runtime Skill.');
        }

        return new LlmResponse($output, 'fake', 'fake-structured', 20, 10, 3, 'application-postgres', 7);
    }
}
