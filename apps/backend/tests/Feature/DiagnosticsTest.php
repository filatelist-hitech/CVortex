<?php

namespace Tests\Feature;

use App\Diagnostics\IncidentRecorder;
use App\Diagnostics\Redactor;
use App\Diagnostics\StructuredLogs;
use App\Models\User;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Logger;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Mockery;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Tests\TestCase;

class DiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/api/v1/_diagnostics-test/fail', fn () => throw new \RuntimeException('Authorization: Bearer SECRET_CANARY api_key=SECRET_CANARY password=SECRET_CANARY'));
        Route::get('/api/v1/_diagnostics-test/controlled', fn () => response()->json(['error' => ['code' => 'PROVIDER_ERROR']], 503));
    }

    public function test_internal_error_is_safe_correlated_and_grouped(): void
    {
        $id = (string) Str::uuid();
        $first = $this->withHeader('X-Request-ID', $id)->getJson('/api/v1/_diagnostics-test/fail');
        $first->assertStatus(500)->assertJsonPath('error.code', 'INTERNAL_ERROR')->assertJsonPath('error.request_id', $id);
        $this->assertSame($id, $first->headers->get('X-Request-ID'));
        $this->assertStringNotContainsString('SECRET_CANARY', $first->getContent());
        $this->assertStringNotContainsString('RuntimeException', $first->getContent());

        $this->getJson('/api/v1/_diagnostics-test/fail')->assertStatus(500);
        $this->assertDatabaseCount('diagnostic_incidents', 1);
        $this->assertSame(2, DB::table('diagnostic_incidents')->first()->occurrence_count);
        $this->assertDatabaseCount('diagnostic_occurrences', 2);
        $this->assertStringNotContainsString('SECRET_CANARY', json_encode(DB::table('diagnostic_occurrences')->get()));
    }

    public function test_recent_reference_ids_remain_searchable_after_repeated_failures(): void
    {
        $recorder = app(IncidentRecorder::class);
        for ($index = 0; $index < 22; $index++) {
            $recorder->record('INTERNAL_ERROR', 'Safe failure.', 'repeated', context: ['request_id' => 'req_repeat_'.$index]);
        }
        $this->assertDatabaseCount('diagnostic_incidents', 1);
        $this->assertDatabaseCount('diagnostic_occurrences', 22);
        $this->assertDatabaseHas('diagnostic_occurrences', ['request_id' => 'req_repeat_21']);
    }

    public function test_log_sink_failure_does_not_prevent_incident_recording(): void
    {
        Log::shouldReceive('sharedContext')->once()->andThrow(new \RuntimeException('sink failed'));
        Log::shouldReceive('log')->once()->andThrow(new \RuntimeException('sink failed'));
        app(IncidentRecorder::class)->record('INTERNAL_ERROR', 'Safe failure.', 'sink');
        $this->assertDatabaseHas('diagnostic_incidents', ['component' => 'sink', 'occurrence_count' => 1]);
    }

    public function test_nested_secrets_are_redacted(): void
    {
        $safe = Redactor::context(['Authorization' => 'Bearer SECRET_CANARY', 'nested' => [
            'API_KEY' => 'SECRET_CANARY', 'message' => 'password=SECRET_CANARY',
        ]]);
        $this->assertStringNotContainsString('SECRET_CANARY', json_encode($safe));
        $this->assertStringNotContainsString('SECRET_CANARY', Redactor::text('{"nested":{"api_key":"SECRET_CANARY"}}'));
    }

    public function test_structured_logger_drops_exception_message_and_nested_secret_values(): void
    {
        $handler = new TestHandler;
        $monolog = new MonologLogger('test');
        $monolog->pushHandler($handler);
        $logger = new Logger($monolog);
        (new StructuredLogs)($logger);
        $logger->error('password=SECRET_CANARY', [
            'Authorization' => 'Bearer SECRET_CANARY',
            'nested' => ['API_KEY' => 'SECRET_CANARY'],
            'exception' => new \RuntimeException('SECRET_CANARY'),
        ]);
        $record = $handler->getRecords()[0];
        $this->assertSame(\RuntimeException::class, $record->message);
        $this->assertStringNotContainsString('SECRET_CANARY', json_encode($record->context));
    }

    public function test_controlled_error_receives_reference_without_changing_its_code(): void
    {
        $this->getJson('/api/v1/_diagnostics-test/controlled')->assertStatus(503)
            ->assertJsonPath('error.code', 'PROVIDER_ERROR')->assertJsonPath('error.retryable', true)
            ->assertJsonStructure(['error' => ['request_id', 'message']]);
    }

    public function test_admin_only_diagnostics_and_lifecycle(): void
    {
        $user = $this->user('user');
        $admin = $this->user('admin');
        app(IncidentRecorder::class)->record('INTERNAL_ERROR', 'A safe failure.', 'api', context: [
            'request_id' => 'req_test', 'user_id' => $user->id,
        ]);
        $id = DB::table('diagnostic_incidents')->value('id');

        $this->as($user)->getJson('/api/v1/diagnostics/incidents')->assertStatus(403);
        $this->as($user)->getJson('/api/v1/diagnostics/incidents/'.$id)->assertStatus(403);
        $this->as($user)->patchJson('/api/v1/diagnostics/incidents/'.$id, ['status' => 'RESOLVED'])->assertStatus(403);
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?search=req_test')->assertOk()->assertJsonPath('data.total', 1);
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents/'.$id)->assertOk()->assertJsonPath('data.occurrences.0.request_id', 'req_test');
        $this->as($admin)->patchJson('/api/v1/diagnostics/incidents/'.$id, ['status' => 'RESOLVED'])->assertOk()->assertJsonPath('data.incident.status', 'RESOLVED');
        $this->assertDatabaseHas('audit_events', ['event_type' => 'diagnostics.incident.status_changed']);
    }

    public function test_browser_report_ignores_untrusted_details_and_requires_auth(): void
    {
        $this->postJson('/api/v1/diagnostics/report', ['component' => 'app'])->assertUnauthorized();
        $user = $this->user('user');
        $this->as($user)->postJson('/api/v1/diagnostics/report', [
            'component' => 'app-root', 'kind' => 'runtime', 'user_id' => 'other',
            'stack' => 'Authorization: Bearer SECRET_CANARY', 'message' => 'api_key=SECRET_CANARY',
        ])->assertStatus(202);
        $event = DB::table('diagnostic_occurrences')->first();
        $this->assertSame($user->id, $event->user_id);
        $this->assertStringNotContainsString('SECRET_CANARY', json_encode($event));
        $this->as($user)->postJson('/api/v1/diagnostics/report', ['component' => '../../bad', 'kind' => 'runtime'])->assertUnprocessable();
    }

    public function test_final_queue_failure_is_correlated(): void
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('payload')->andReturn(['cvortex' => ['request_id' => 'req_queue']]);
        $job->shouldReceive('getJobId')->andReturn('job_test');
        $job->shouldReceive('resolveName')->andReturn('TestJob');
        $job->shouldReceive('attempts')->andReturn(3);
        Event::dispatch(new JobFailed('sync', $job, new \RuntimeException('failed')));
        $this->assertDatabaseHas('diagnostic_incidents', ['error_code' => 'QUEUE_JOB_FAILED', 'occurrence_count' => 1]);
        $this->assertDatabaseHas('diagnostic_occurrences', ['request_id' => 'req_queue', 'job_id' => 'job_test', 'attempt' => 3]);
    }

    public function test_retention_prunes_old_detail_and_closed_incidents_but_keeps_open_group(): void
    {
        $recorder = app(IncidentRecorder::class);
        $recorder->record('INTERNAL_ERROR', 'Safe', 'open');
        $recorder->record('INTERNAL_ERROR', 'Safe', 'closed');
        DB::table('diagnostic_incidents')->where('component', 'closed')->update([
            'status' => 'RESOLVED', 'last_seen_at' => now()->subDays(100),
        ]);
        DB::table('diagnostic_occurrences')->update(['created_at' => now()->subDays(40)]);
        $this->artisan('diagnostics:prune')->assertExitCode(0);
        $this->assertDatabaseHas('diagnostic_incidents', ['component' => 'open', 'occurrence_count' => 1]);
        $this->assertDatabaseMissing('diagnostic_incidents', ['component' => 'closed']);
        $this->assertDatabaseCount('diagnostic_occurrences', 0);
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

        return $this->actingAs($user, 'web')->withSession(['auth_generation' => $user->fresh()->auth_generation]);
    }
}
