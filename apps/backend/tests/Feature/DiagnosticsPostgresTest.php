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
        $this->as($admin)->patchJson('/api/v1/diagnostics/incidents/'.$id, ['status' => 'RESOLVED'])->assertOk();
        $this->as($admin)->patchJson('/api/v1/diagnostics/incidents/'.$id, ['status' => 'RESOLVED'])->assertOk();
        $this->assertSame(1, DB::table('audit_events')->where('subject_id', $id)->count());
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
