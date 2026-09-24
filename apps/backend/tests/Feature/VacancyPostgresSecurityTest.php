<?php

namespace Tests\Feature;

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\Jobs\AnalyzeVacancy;
use App\Models\User;
use App\Services\DatabaseOwnerContext;
use App\Services\VacancyAnalysisService;
use App\Services\VacancyIngestionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class VacancyPostgresSecurityTest extends TestCase
{
    /** @var list<string> */
    private const OWNER_TABLES = [
        'vacancies',
        'vacancy_snapshots',
        'vacancy_requirements',
        'vacancy_analyses',
        'vacancy_match_dimensions',
        'vacancy_match_evidence',
        'vacancy_llm_runs',
    ];

    private string $fixtureRunId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRunId = Str::lower((string) Str::ulid());

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL runtime-role integration suite.');
        }
    }

    public function test_runtime_role_rls_and_owner_context_fail_closed(): void
    {
        Queue::fake();
        $this->app->instance(LlmProvider::class, new PostgresVacancyFakeProvider);
        $context = app(DatabaseOwnerContext::class);
        $owner = $this->user('rls-owner@example.test');
        $other = $this->user('rls-other@example.test');
        $ownerResult = app(VacancyIngestionService::class)->queue($owner, 'Owner Laravel requirement.', 'https://jobs.example.test/rls-owner');
        $otherResult = app(VacancyIngestionService::class)->queue($other, 'Other Laravel requirement.', 'https://jobs.example.test/rls-other');
        app(VacancyAnalysisService::class)->analyze($owner, $ownerResult['snapshot']);
        app(VacancyAnalysisService::class)->analyze($other, $otherResult['snapshot']);

        $role = DB::selectOne('SELECT current_user AS role, rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user');
        $this->assertSame(config('database.runtime_role'), $role->role);
        $this->assertFalse($role->rolsuper);
        $this->assertFalse($role->rolbypassrls);

        $flags = collect(DB::select(<<<'SQL'
SELECT c.relname, c.relrowsecurity, c.relforcerowsecurity
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public' AND c.relname LIKE 'vacanc%' AND c.relkind = 'r'
SQL))->keyBy('relname');
        foreach (self::OWNER_TABLES as $table) {
            $this->assertTrue($flags[$table]->relrowsecurity, $table.' must have RLS enabled.');
            $this->assertTrue($flags[$table]->relforcerowsecurity, $table.' must force RLS.');
            $this->assertSame(0, DB::table($table)->count(), $table.' must fail closed without owner context.');
        }

        $context->run((string) $owner->id, function () use ($ownerResult, $otherResult, $other): void {
            $this->assertSame(1, DB::table('vacancies')->count());
            $this->assertSame(1, DB::table('vacancy_snapshots')->count());
            $this->assertSame(1, DB::table('vacancy_requirements')->count());
            $this->assertSame(1, DB::table('vacancy_analyses')->count());
            $this->assertSame(7, DB::table('vacancy_match_dimensions')->count());
            $this->assertSame(1, DB::table('vacancy_llm_runs')->count());
            $this->assertNotNull(DB::table('vacancies')->where('id', $ownerResult['vacancy']->id)->first());
            $this->assertNull(DB::table('vacancies')->where('id', $otherResult['vacancy']->id)->first());
            $this->assertSame(0, DB::table('vacancies')->where('id', $otherResult['vacancy']->id)->update(['title' => 'cross-owner']));
            $this->assertSame(0, DB::table('vacancies')->where('id', $otherResult['vacancy']->id)->delete());

            try {
                DB::transaction(function () use ($otherResult, $other): void {
                    DB::table('vacancy_requirements')->insert([
                        'id' => (string) Str::ulid(),
                        'owner_id' => $other->id,
                        'vacancy_snapshot_id' => $otherResult['snapshot']->id,
                        'dimension' => 'TECHNICAL',
                        'importance' => 'MANDATORY',
                        'label' => 'Cross-owner',
                        'source_excerpt' => 'Cross-owner',
                        'confidence' => 1,
                        'extracted_by' => 'synthetic',
                        'candidate_hash' => hash('sha256', 'cross-owner-rls'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
                $this->fail('RLS accepted a child row for another owner.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        });

        $this->assertSame(0, DB::table('vacancies')->count(), 'Owner context leaked after scoped work.');
    }

    public function test_http_and_queue_owner_context_is_cleared(): void
    {
        Queue::fake();
        $this->app->instance(LlmProvider::class, new PostgresVacancyFakeProvider);
        $owner = $this->user('context-owner@example.test');
        $result = app(VacancyIngestionService::class)->queue($owner, 'Context Laravel requirement.', null);
        app(VacancyAnalysisService::class)->analyze($owner, $result['snapshot']);

        $this->actingAs($owner)->getJson('/api/v1/vacancies/'.$result['vacancy']->id)->assertOk();
        $this->assertSame(0, DB::table('vacancies')->count(), 'HTTP middleware leaked owner context.');

        $job = new AnalyzeVacancy((string) $owner->id, (string) $result['snapshot']->id);
        $job->handle(app(VacancyAnalysisService::class), app(DatabaseOwnerContext::class));
        $this->assertSame(0, DB::table('vacancies')->count(), 'Queue job leaked owner context.');
    }

    public function test_raw_snapshot_history_updates_are_rejected(): void
    {
        Queue::fake();
        $context = app(DatabaseOwnerContext::class);
        $user = $this->user('snapshot-db-immutability@example.test');
        $result = app(VacancyIngestionService::class)->queue($user, 'Historical source.', null);

        foreach (['raw_text' => 'mutated', 'content_hash' => str_repeat('f', 64), 'version' => 99, 'vacancy_id' => (string) Str::ulid(), 'owner_id' => (string) Str::ulid()] as $column => $value) {
            $context->run((string) $user->id, function () use ($result, $column, $value): void {
                try {
                    DB::transaction(fn () => DB::table('vacancy_snapshots')->where('id', $result['snapshot']->id)->update([$column => $value]));
                    $this->fail($column.' update was accepted.');
                } catch (QueryException $exception) {
                    $this->assertStringContainsString('vacancy snapshots are immutable', $exception->getMessage());
                }
            });
        }
    }

    private function user(string $email): User
    {
        [$localPart, $domain] = explode('@', $email, 2);

        return User::query()->create([
            'email' => $localPart.'+'.$this->fixtureRunId.'@'.$domain,
            'password' => 'a very long safe passphrase',
        ])->fresh();
    }
}

class PostgresVacancyFakeProvider implements LlmProvider
{
    public function generateStructured(LlmRequest $request): LlmResponse
    {
        $source = $request->untrustedSourceText;

        return new LlmResponse(['requirements' => [[
            'dimension' => 'TECHNICAL',
            'importance' => 'MANDATORY',
            'label' => 'Laravel',
            'normalized_value' => null,
            'source_excerpt' => str_contains($source, 'Owner') ? 'Owner Laravel requirement.' : (str_contains($source, 'Other') ? 'Other Laravel requirement.' : 'Context Laravel requirement.'),
            'confidence' => 0.9,
        ]]], 'fake', 'fake-structured', 5, 5, 1, 'postgres-security', 1);
    }
}
