<?php

namespace Tests\Feature;

use App\AI\Exceptions\LlmProviderException;
use App\Diagnostics\IncidentRecorder;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class DiagnosticsPostgresTest extends TestCase
{
    use DatabaseTransactions;

    public function test_runtime_role_can_group_and_admin_can_query_without_user_access(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL runtime-role boundary only.');
        }
        $role = DB::selectOne('SELECT current_user AS role')->role;
        $this->assertSame(config('database.runtime_role'), $role);
        $admin = $this->user('admin');
        $other = $this->user('user');
        $reference = 'req_'.Str::ulid();
        $recorder = app(IncidentRecorder::class);
        $recorder->record('INTERNAL_ERROR', 'Safe message', 'api', context: ['request_id' => $reference, 'user_id' => $other->id]);
        $recorder->record('INTERNAL_ERROR', 'Safe message', 'api', context: ['request_id' => $reference, 'user_id' => $other->id]);
        $incidentId = DB::table('diagnostic_occurrences')->where('request_id', $reference)->value('incident_id');
        $this->assertNotNull($incidentId);
        $this->assertSame(2, DB::table('diagnostic_incidents')->where('id', $incidentId)->value('occurrence_count'));
        $this->assertSame(2, DB::table('diagnostic_occurrences')->where('incident_id', $incidentId)->count());
        $this->as($other)->getJson('/api/v1/diagnostics/incidents?search='.$reference)->assertForbidden();
        $this->as($other)->getJson('/api/v1/diagnostics/incidents/'.$incidentId)->assertForbidden();
        $this->as($other)->patchJson('/api/v1/diagnostics/incidents/'.$incidentId, ['status' => 'RESOLVED'])->assertForbidden();
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?search='.$reference)->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_runtime_role_rolls_back_status_when_audit_fails(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL runtime-role boundary only.');
        }
        $this->assertSame(config('database.runtime_role'), DB::selectOne('SELECT current_user AS role')->role);
        $admin = $this->user('admin');
        app(IncidentRecorder::class)->record('INTERNAL_ERROR', 'Safe message', 'audit-rollback');
        $incidentId = DB::table('diagnostic_incidents')->where('component', 'audit-rollback')->value('id');
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andThrow(new \RuntimeException('audit unavailable'));
        $this->app->instance(AuditLogger::class, $audit);
        $this->withoutExceptionHandling();

        try {
            $this->as($admin)->patchJson('/api/v1/diagnostics/incidents/'.$incidentId, ['status' => 'RESOLVED']);
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('audit unavailable', $exception->getMessage());
        }

        $this->assertDatabaseHas('diagnostic_incidents', ['id' => $incidentId, 'status' => 'OPEN']);
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'diagnostics.incident.status_changed', 'subject_id' => $incidentId,
        ]);
    }

    public function test_runtime_provider_filter_retry_delay_and_noop_status_audit(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL runtime-role boundary only.');
        }
        $admin = $this->user('admin');
        $exception = new LlmProviderException(LlmProviderException::RATE_LIMITED, retryAfterSeconds: 42);
        $reference = 'req_'.Str::ulid();
        app(IncidentRecorder::class)->record('LLM_PROVIDER_RATE_LIMITED', 'Rate limited.', 'vacancy', exception: $exception, context: [
            'provider' => 'openai', 'request_id' => $reference,
        ]);
        $id = DB::table('diagnostic_occurrences')->where('request_id', $reference)->value('incident_id');
        $this->assertNotNull($id);
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?provider=openai&search='.$reference)
            ->assertOk()->assertJsonPath('data.total', 1);
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents/'.$id)->assertOk()
            ->assertJsonPath('data.occurrences.0.retry_after_seconds', 42);
        app(IncidentRecorder::class)->record('LLM_PROVIDER_UNAVAILABLE', 'Temporarily unavailable.', 'temporary-delay', exception: new LlmProviderException(LlmProviderException::TEMPORARY_UNAVAILABLE, retryAfterSeconds: 41));
        $temporaryId = DB::table('diagnostic_incidents')->where('component', 'temporary-delay')->value('id');
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents/'.$temporaryId)->assertOk()
            ->assertJsonPath('data.occurrences.0.retry_after_seconds', 41);
        $this->as($admin)->patchJson('/api/v1/diagnostics/incidents/'.$id, ['status' => 'RESOLVED'])->assertOk();
        $this->as($admin)->patchJson('/api/v1/diagnostics/incidents/'.$id, ['status' => 'RESOLVED'])->assertOk();
        $this->assertSame(1, DB::table('audit_events')->where('subject_id', $id)->count());
    }

    public function test_runtime_role_uses_id_to_break_latest_occurrence_timestamp_ties(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL runtime-role boundary only.');
        }
        $admin = $this->user('admin');
        $reference = 'req_'.Str::ulid();
        $this->assertSame(config('database.runtime_role'), DB::selectOne('SELECT current_user AS role')->role);
        $firstException = new LlmProviderException(LlmProviderException::RATE_LIMITED, retryAfterSeconds: 42);
        $secondException = new LlmProviderException(LlmProviderException::RATE_LIMITED, retryAfterSeconds: 42);
        $recorder = app(IncidentRecorder::class);
        $recorder->record('LLM_PROVIDER_RATE_LIMITED', 'Rate limited.', 'timestamp-tie', exception: $firstException);
        $recorder->record('LLM_PROVIDER_RATE_LIMITED', 'Rate limited.', 'timestamp-tie', exception: $secondException);
        $this->assertSame(1, DB::table('diagnostic_incidents')->where('component', 'timestamp-tie')->count());
        $id = DB::table('diagnostic_incidents')->where('component', 'timestamp-tie')->value('id');
        $this->assertSame(2, DB::table('diagnostic_incidents')->where('id', $id)->value('occurrence_count'));
        $ids = DB::table('diagnostic_occurrences')->where('incident_id', $id)->orderBy('id')->pluck('id');
        $this->assertCount(2, $ids);
        $timestamp = now()->subMinute()->startOfSecond();
        $baseUlid = (string) Str::ulid();
        $largerId = substr($baseUlid, 0, -1).'Z';
        $smallerId = substr($baseUlid, 0, -1).'A';
        $this->assertTrue($largerId > $smallerId);

        // Invert physical/insertion order relative to ID sort order:
        // Update the FIRST row to have the LARGER ID ($largerId),
        // and the SECOND row to have the SMALLER ID ($smallerId).
        // In PostgreSQL storage, $smallerId gets the higher TID (most recently inserted tuple).
        // A descending scan on created_at without "id DESC" will encounter $smallerId first!
        DB::table('diagnostic_occurrences')->where('id', $ids[0])->update([
            'id' => $largerId, 'created_at' => $timestamp, 'provider' => 'newer',
            'operation' => 'operation_newer', 'request_id' => $reference,
            'llm_run_id' => (string) Str::ulid(), 'application_id' => (string) Str::ulid(),
            'attempt' => 2, 'retry_after_seconds' => 42,
        ]);
        DB::table('diagnostic_occurrences')->where('id', $ids[1])->update([
            'id' => $smallerId, 'created_at' => $timestamp, 'provider' => 'older',
            'operation' => 'operation_older', 'request_id' => 'req_older',
            'attempt' => 1, 'retry_after_seconds' => 5,
        ]);
        $this->assertSame(2, DB::table('diagnostic_occurrences')->where('incident_id', $id)->where('created_at', $timestamp)->count());

        // Strict negative control: verify that without the mandatory "id DESC" tie-breaker,
        // PostgreSQL's backward index scan on (incident_id, created_at) returns the higher TID ($smallerId),
        // yielding the older occurrence. This proves that removing "id DESC" would break the contract.
        $withoutTieBreaker = DB::selectOne(
            'SELECT provider, operation FROM diagnostic_occurrences WHERE incident_id = ? ORDER BY created_at DESC LIMIT 1',
            [$id]
        );
        $this->assertNotNull($withoutTieBreaker);
        $this->assertSame('older', $withoutTieBreaker->provider, 'Negative control: query without id DESC must return older occurrence');
        $this->assertSame('operation_older', $withoutTieBreaker->operation, 'Negative control: query without id DESC must return older operation');

        $detailWithoutTie = DB::table('diagnostic_occurrences')->where('incident_id', $id)
            ->orderByDesc('created_at')->limit(20)->pluck('id');
        $this->assertSame([$smallerId, $largerId], $detailWithoutTie->all(), 'Negative control: detail query without id DESC must return inverted order');

        // Positive control: API uses "ORDER BY created_at DESC, id DESC LIMIT 1",
        // correctly selecting the larger ID ($largerId) for both provider and operation.
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?provider=newer&search='.$reference)->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.latest_provider', 'newer')
            ->assertJsonPath('data.data.0.latest_operation', 'operation_newer');
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?provider=older')->assertOk()
            ->assertJsonPath('data.total', 0);
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents/'.$id)->assertOk()
            ->assertJsonPath('data.occurrences.0.id', $largerId)
            ->assertJsonPath('data.occurrences.0.provider', 'newer')
            ->assertJsonPath('data.occurrences.0.operation', 'operation_newer')
            ->assertJsonPath('data.occurrences.0.request_id', $reference)
            ->assertJsonPath('data.occurrences.0.attempt', 2)
            ->assertJsonPath('data.occurrences.0.retry_after_seconds', 42)
            ->assertJsonPath('data.occurrences.1.id', $smallerId)
            ->assertJsonPath('data.occurrences.1.operation', 'operation_older');
    }

    public function test_runtime_role_deduplicates_the_same_throwable_object(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL runtime-role boundary only.');
        }
        $this->assertSame(config('database.runtime_role'), DB::selectOne('SELECT current_user AS role')->role);
        $exception = new LlmProviderException(LlmProviderException::RATE_LIMITED, retryAfterSeconds: 42);
        $reference = 'req_'.Str::ulid();
        $recorder = app(IncidentRecorder::class);
        $context = ['request_id' => $reference, 'provider' => 'openai'];
        $this->assertTrue($recorder->record('LLM_PROVIDER_RATE_LIMITED', 'Rate limited.', 'same-throwable', exception: $exception, context: $context));
        $this->assertTrue(IncidentRecorder::wasRecorded($exception));
        $this->assertTrue($recorder->record('LLM_PROVIDER_RATE_LIMITED', 'Rate limited.', 'same-throwable', exception: $exception, context: $context));

        $id = DB::table('diagnostic_incidents')->where('component', 'same-throwable')->value('id');
        $this->assertNotNull($id);
        $this->assertSame(1, DB::table('diagnostic_incidents')->where('component', 'same-throwable')->count());
        $this->assertSame(1, DB::table('diagnostic_incidents')->where('id', $id)->value('occurrence_count'));
        $this->assertSame(1, DB::table('diagnostic_occurrences')->where('incident_id', $id)->count());
        $this->assertDatabaseHas('diagnostic_occurrences', [
            'incident_id' => $id, 'request_id' => $reference, 'provider' => 'openai', 'retry_after_seconds' => 42,
        ]);
    }

    private function user(string $role): User
    {
        $user = User::query()->create(['email' => Str::ulid().'@example.test', 'password' => Hash::make('test password long enough')]);
        $user->forceFill(['role' => $role])->save();

        return $user->fresh();
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web')->withSession(['auth_generation' => $user->auth_generation]);
    }
}
