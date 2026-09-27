<?php

namespace Tests\Feature;

use App\AI\Exceptions\LlmProviderException;
use App\Diagnostics\IncidentRecorder;
use App\Diagnostics\Redactor;
use App\Diagnostics\StructuredLogs;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Logger;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
        Route::get('/api/v1/_diagnostics-test/controlled-retryable', fn () => response()->json(['error' => ['code' => 'PROVIDER_ERROR', 'retryable' => true]], 503));
        Route::get('/api/v1/_diagnostics-test/provider-rate-limited', fn () => throw new LlmProviderException(LlmProviderException::RATE_LIMITED));
        Route::get('/api/v1/_diagnostics-test/provider-temporary', fn () => throw new LlmProviderException(LlmProviderException::TEMPORARY_UNAVAILABLE));
        Route::get('/api/v1/_diagnostics-test/provider-config', fn () => throw new LlmProviderException(LlmProviderException::NOT_CONFIGURED));
        Route::get('/api/v1/_diagnostics-test/provider-invalid-config', fn () => throw new LlmProviderException(LlmProviderException::INVALID_CONFIGURATION));
        Route::get('/api/v1/_diagnostics-test/sync-fail', function (): never {
            DiagnosticsSyncFailJob::dispatch();
            throw new \LogicException('The sync test job did not fail as expected.');
        });
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

    public function test_structured_logger_keeps_safe_exception_frames_without_message_or_secrets(): void
    {
        $handler = new TestHandler;
        $monolog = new MonologLogger('test');
        $monolog->pushHandler($handler);
        $logger = new Logger($monolog);
        (new StructuredLogs)($logger);
        try {
            throw new \RuntimeException('SECRET_CANARY');
        } catch (\RuntimeException $exception) {
            $logger->error('password=SECRET_CANARY', [
                'Authorization' => 'Bearer SECRET_CANARY',
                'nested' => ['API_KEY' => 'SECRET_CANARY'],
                'exception' => $exception,
            ]);
        }
        $record = $handler->getRecords()[0];
        $this->assertSame(\RuntimeException::class, $record->message);
        $this->assertStringContainsString('TestCase.php', $record->context['safe_stack']);
        $this->assertStringNotContainsString(dirname(__DIR__, 2), $record->context['safe_stack']);
        $this->assertStringNotContainsString('SECRET_CANARY', json_encode($record->context));
    }

    public function test_incident_stderr_keeps_only_sanitized_exception_frames(): void
    {
        Log::spy();
        try {
            throw new \RuntimeException('password=SECRET_CANARY');
        } catch (\RuntimeException $exception) {
            app(IncidentRecorder::class)->record('INTERNAL_ERROR', 'Safe failure.', 'safe_stack', exception: $exception);
        }

        Log::shouldHaveReceived('log')->once()->withArgs(function (string $level, string $message, array $context): bool {
            return $message === 'diagnostics.incident'
                && isset($context['safe_stack'])
                && str_contains($context['safe_stack'], 'TestCase.php')
                && ! str_contains($context['safe_stack'], dirname(__DIR__, 2))
                && ! str_contains(json_encode($context), 'SECRET_CANARY');
        });
    }

    public function test_controlled_error_does_not_infer_retryability_from_http_status(): void
    {
        $this->getJson('/api/v1/_diagnostics-test/controlled')->assertStatus(503)
            ->assertJsonPath('error.code', 'PROVIDER_ERROR')->assertJsonPath('error.retryable', false)
            ->assertJsonStructure(['error' => ['request_id', 'message']]);
        $this->getJson('/api/v1/_diagnostics-test/controlled-retryable')->assertStatus(503)
            ->assertJsonPath('error.code', 'PROVIDER_ERROR')->assertJsonPath('error.retryable', true);
    }

    public function test_provider_error_contract_uses_failure_category_for_retryability(): void
    {
        $this->getJson('/api/v1/_diagnostics-test/provider-rate-limited')->assertStatus(503)
            ->assertJsonPath('error.retryable', true);
        $this->getJson('/api/v1/_diagnostics-test/provider-temporary')->assertStatus(503)
            ->assertJsonPath('error.retryable', true);
        $configured = $this->getJson('/api/v1/_diagnostics-test/provider-config')->assertStatus(503)
            ->assertJsonPath('error.retryable', false)->json();
        $this->assertStringNotContainsString('retry', strtolower($configured['error']['message']));
        $this->getJson('/api/v1/_diagnostics-test/provider-invalid-config')->assertStatus(503)
            ->assertJsonPath('error.retryable', false);
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

    public function test_application_id_filter_search_and_detail_use_occurrence_correlation(): void
    {
        $user = $this->user('user');
        $admin = $this->user('admin');
        $applicationId = (string) Str::ulid();
        app(IncidentRecorder::class)->record('LLM_PROVIDER_UNAVAILABLE', 'Application provider failed.', 'application', context: [
            'application_id' => $applicationId,
            'user_id' => $user->id,
            'llm_run_id' => (string) Str::ulid(),
        ]);
        $incidentId = DB::table('diagnostic_incidents')->value('id');

        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?application_id='.$applicationId)
            ->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $incidentId);
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?search='.$applicationId)
            ->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $incidentId);
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents/'.$incidentId)
            ->assertOk()->assertJsonPath('data.occurrences.0.application_id', $applicationId);
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
        $this->as($user)->postJson('/api/v1/diagnostics/report', ['component' => 'browser', 'kind' => 'rejection'])->assertStatus(202);
        $this->as($user)->postJson('/api/v1/diagnostics/report', ['component' => 'global-root', 'kind' => 'render'])->assertStatus(202);
        $this->assertDatabaseCount('diagnostic_incidents', 3);
        $this->assertDatabaseCount('diagnostic_occurrences', 3);
        $this->as($user)->postJson('/api/v1/diagnostics/report', ['component' => '../../bad', 'kind' => 'runtime'])->assertUnprocessable();
        foreach (range(1, 4) as $index) {
            $this->as($user)->postJson('/api/v1/diagnostics/report', [
                'component' => 'client-component-'.$index,
                'kind' => 'runtime',
            ])->assertUnprocessable();
        }
        $this->assertDatabaseCount('diagnostic_incidents', 3);
        $this->assertDatabaseCount('diagnostic_occurrences', 3);
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

    public function test_sync_queue_failure_is_recorded_once_when_it_bubbles_through_the_api_request(): void
    {
        config(['queue.default' => 'sync']);
        $this->withHeader('X-Request-ID', 'req_sync_job')->getJson('/api/v1/_diagnostics-test/sync-fail')
            ->assertStatus(500)->assertJsonPath('error.code', 'INTERNAL_ERROR');

        $this->assertDatabaseCount('diagnostic_incidents', 1);
        $this->assertDatabaseCount('diagnostic_occurrences', 1);
        $occurrence = DB::table('diagnostic_occurrences')->sole();
        $this->assertSame('req_sync_job', $occurrence->request_id);
        $this->assertSame(DiagnosticsSyncFailJob::class, $occurrence->operation);
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

final class DiagnosticsSyncFailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        throw new \RuntimeException('sync job failed');
    }
}
