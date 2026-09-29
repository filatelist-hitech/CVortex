<?php

namespace Tests\Feature;

use App\AI\Exceptions\LlmProviderException;
use App\Diagnostics\ErrorCatalog;
use App\Diagnostics\IncidentRecorder;
use App\Diagnostics\Redactor;
use App\Diagnostics\StructuredLogs;
use App\Jobs\AnalyzeVacancy;
use App\Jobs\ExtractCareerSource;
use App\Models\CareerProfile;
use App\Models\CareerSource;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancySnapshot;
use App\Services\AuditLogger;
use App\Services\CareerExtractionService;
use App\Services\DatabaseOwnerContext;
use App\Services\DependencyProbe;
use App\Services\VacancyAnalysisService;
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
use Symfony\Component\HttpKernel\Exception\HttpException;
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
        Route::get('/api/v1/_diagnostics-test/provider-rate-limited', fn () => throw new LlmProviderException(
            LlmProviderException::RATE_LIMITED,
            retryAfterSeconds: 45,
        ));
        Route::get('/api/v1/_diagnostics-test/provider-temporary', fn () => throw new LlmProviderException(LlmProviderException::TEMPORARY_UNAVAILABLE));
        Route::get('/api/v1/_diagnostics-test/provider-config', fn () => throw new LlmProviderException(LlmProviderException::NOT_CONFIGURED));
        Route::get('/api/v1/_diagnostics-test/provider-invalid-config', fn () => throw new LlmProviderException(LlmProviderException::INVALID_CONFIGURATION));
        Route::get('/api/v1/_diagnostics-test/provider-malformed-output', fn () => throw new LlmProviderException(LlmProviderException::MALFORMED_OUTPUT));
        Route::get('/api/v1/_diagnostics-test/http-service-unavailable', fn () => throw new HttpException(503, 'Dependency failed.', headers: [
            'Retry-After' => '20', 'Set-Cookie' => 'session=SECRET_CANARY',
        ]));
        Route::get('/api/v1/career/_diagnostics-test/unclassified', fn () => throw new \RuntimeException('Unknown operation failure.'));
        Route::get('/api/v1/_diagnostics-test/http-exception-headers', fn () => throw new HttpException(429, 'Too many requests.', headers: [
            'Retry-After' => '30', 'X-RateLimit-Limit' => '60', 'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => '1234567890', 'RateLimit-Policy' => '60;w=60',
            'Set-Cookie' => 'session=SECRET_CANARY', 'Location' => 'https://private.example.test/path',
            'X-RateLimit-Limit-Invalid' => '60', 'RateLimit-Remaining' => "0\r\nSet-Cookie: SECRET_CANARY",
        ]));
        Route::post('/api/v1/_diagnostics-test/method-only', fn () => response()->json(['data' => 'ok']));
        Route::middleware('throttle:1,1')->get('/api/v1/_diagnostics-test/throttled', fn () => response()->json(['data' => 'ok']));
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

    public function test_failure_of_both_diagnostic_sinks_returns_false_without_replacing_the_primary_exception(): void
    {
        Log::shouldReceive('sharedContext')->once()->andThrow(new \RuntimeException('log context unavailable'));
        Log::shouldReceive('log')->once()->andThrow(new \RuntimeException('log sink unavailable'));
        DB::shouldReceive('transaction')->once()->andThrow(new \RuntimeException('database unavailable'));
        $original = new \RuntimeException('primary operation failure');

        $stored = app(IncidentRecorder::class)->record('INTERNAL_ERROR', 'Safe failure.', 'failure_sinks', exception: $original);

        $this->assertFalse($stored);
        $this->assertSame('primary operation failure', $original->getMessage());
    }

    public function test_readiness_probe_preserves_dependency_failure_when_diagnostics_also_fail(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new \RuntimeException('postgres unavailable'));
        DB::shouldReceive('purge')->once()->andThrow(new \RuntimeException('purge unavailable'));
        DB::shouldReceive('transaction')->once()->andThrow(new \RuntimeException('postgres unavailable'));
        Log::shouldReceive('sharedContext')->once()->andThrow(new \RuntimeException('log context unavailable'));
        Log::shouldReceive('log')->once()->andThrow(new \RuntimeException('log sink unavailable'));

        $this->assertFalse(app(DependencyProbe::class)->ready());
    }

    public function test_request_id_middleware_keeps_http_response_working_when_log_context_fails(): void
    {
        Route::get('/api/v1/_diagnostics-test/log-context-failure', fn () => response()->json(['data' => 'ok']));
        Log::shouldReceive('shareContext')->once()->andThrow(new \RuntimeException('log sink unavailable'));

        $response = $this->withHeader('X-Request-ID', 'req_without_log_context')->getJson('/api/v1/_diagnostics-test/log-context-failure')->assertOk();
        $this->assertSame('req_without_log_context', $response->headers->get('X-Request-ID'));
    }

    public function test_nested_secrets_are_redacted(): void
    {
        $safe = Redactor::context(['Authorization' => 'Bearer SECRET_CANARY', 'nested' => [
            'API_KEY' => 'SECRET_CANARY', 'message' => 'password=SECRET_CANARY',
            'headers' => ['X-API-Key' => 'SECRET_CANARY', 'Cookie' => 'session=SECRET_CANARY'],
            'prompt' => 'Candidate experience SECRET_CANARY',
        ]]);
        $this->assertStringNotContainsString('SECRET_CANARY', json_encode($safe));
        $this->assertStringNotContainsString('SECRET_CANARY', Redactor::text('{"nested":{"api_key":"SECRET_CANARY"}}'));
        $unstructured = Redactor::text("Authorization: Basic SECRET_CANARY extra words\npassword=\"two words SECRET_CANARY\"\nhttps://example.test/?access_token=SECRET_CANARY&next=ok\nCookie: a=ok; session=SECRET_CANARY");
        $this->assertStringNotContainsString('SECRET_CANARY', $unstructured);
        $this->assertStringContainsString('next=ok', $unstructured);
        $encodedQuery = Redactor::text('https://example.test/?access%255Ftoken=ENCODED_SECRET_CANARY&next=ok');
        $this->assertStringNotContainsString('ENCODED_SECRET_CANARY', $encodedQuery);
        $nestedJson = Redactor::text('{"request":{"api_key":{"value":"SECRET_CANARY"},"prompt":"private prompt SECRET_CANARY"}}');
        $this->assertStringNotContainsString('SECRET_CANARY', $nestedJson);
    }

    public function test_structured_logger_redacts_escaped_json_secret_values(): void
    {
        $handler = new TestHandler;
        $logger = new MonologLogger('test');
        $logger->pushHandler($handler);
        $structured = new Logger($logger);
        (new StructuredLogs)($structured);
        $message = json_encode(['password' => 'abc"SECRET_CANARY'], JSON_THROW_ON_ERROR);

        $structured->error($message);

        $record = $handler->getRecords()[0];
        $this->assertStringNotContainsString('SECRET_CANARY', $record->message);
        $this->assertStringContainsString('[REDACTED]', $record->message);
    }

    public function test_global_reporter_preserves_non_http_and_original_failures(): void
    {
        $requestAbstract = $this->app->isAlias('request') ? $this->app->getAlias('request') : 'request';
        $originalRequest = $this->app->resolved('request') ? $this->app->make('request') : null;
        $this->app->offsetUnset('request');
        $this->app->offsetUnset($requestAbstract);
        $this->assertFalse($this->app->resolved('request'));
        Log::spy();
        $original = new \RuntimeException('original CLI failure');
        try {
            report($original);
        } finally {
            if ($originalRequest !== null) {
                $this->app->instance($requestAbstract, $originalRequest);
            }
        }

        $this->assertDatabaseHas('diagnostic_incidents', [
            'error_code' => 'INTERNAL_ERROR', 'component' => 'console', 'exception_class' => \RuntimeException::class,
        ]);
        Log::shouldHaveReceived('error')->once()->withArgs(fn ($message, $context): bool => ($context['exception'] ?? null) === $original);
    }

    public function test_reporter_falls_back_to_laravel_logging_when_incident_reporting_fails(): void
    {
        $original = new \RuntimeException('original HTTP failure');
        Route::get('/api/v1/_diagnostics-test/reporter-failure', function () use ($original): never {
            throw $original;
        });
        $this->app->instance(IncidentRecorder::class, new class
        {
            public function record(...$arguments): bool
            {
                throw new \RuntimeException('diagnostics sink failure');
            }
        });
        Log::spy();

        $this->getJson('/api/v1/_diagnostics-test/reporter-failure')->assertStatus(500)
            ->assertJsonPath('error.code', 'INTERNAL_ERROR');

        Log::shouldHaveReceived('error')->once()->withArgs(fn ($message, $context): bool => ($context['exception'] ?? null) === $original);
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
            $throwSite = basename($exception->getFile()).':'.$exception->getLine();
            $logger->error('password=SECRET_CANARY', [
                'Authorization' => 'Bearer SECRET_CANARY',
                'nested' => ['API_KEY' => 'SECRET_CANARY'],
                'exception' => $exception,
            ]);
        }
        $record = $handler->getRecords()[0];
        $this->assertSame(\RuntimeException::class, $record->message);
        $this->assertStringContainsString($throwSite, $record->context['safe_stack']);
        $this->assertStringContainsString('TestCase.php', $record->context['safe_stack']);
        $this->assertStringNotContainsString(dirname(__DIR__, 2), $record->context['safe_stack']);
        $this->assertStringNotContainsString('SECRET_CANARY', json_encode($record->context));
        $this->assertLessThanOrEqual(12, count(explode("\n", $record->context['safe_stack'])));
    }

    public function test_incident_stderr_keeps_only_sanitized_exception_frames(): void
    {
        Log::spy();
        try {
            throw new \RuntimeException('password=SECRET_CANARY');
        } catch (\RuntimeException $exception) {
            $throwSite = basename($exception->getFile()).':'.$exception->getLine();
            app(IncidentRecorder::class)->record('INTERNAL_ERROR', 'Safe failure.', 'safe_stack', exception: $exception);
        }

        Log::shouldHaveReceived('log')->once()->withArgs(function (string $level, string $message, array $context) use ($throwSite): bool {
            return $message === 'diagnostics.incident'
                && isset($context['safe_stack'])
                && str_contains($context['safe_stack'], $throwSite)
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
        $rateLimited = $this->withHeader('X-Request-ID', 'req_provider_rate')->getJson('/api/v1/_diagnostics-test/provider-rate-limited')->assertStatus(503)
            ->assertJsonPath('error.code', 'LLM_PROVIDER_RATE_LIMITED')->assertJsonPath('error.retryable', true);
        $this->assertSame('req_provider_rate', $rateLimited->headers->get('X-Request-ID'));
        $this->assertSame('45', $rateLimited->headers->get('Retry-After'));
        $this->assertSame('LLM_PROVIDER_RATE_LIMITED', $rateLimited->json('error.code'));
        $this->assertDatabaseHas('diagnostic_incidents', ['error_code' => 'LLM_PROVIDER_RATE_LIMITED', 'retryable' => true]);
        $this->withHeader('X-Request-ID', 'req_provider_temporary')->getJson('/api/v1/_diagnostics-test/provider-temporary')->assertStatus(503)
            ->assertJsonPath('error.code', 'LLM_PROVIDER_UNAVAILABLE')->assertJsonPath('error.retryable', true);
        $configured = $this->getJson('/api/v1/_diagnostics-test/provider-config')->assertStatus(503)
            ->assertJsonPath('error.code', 'LLM_PROVIDER_CONFIGURATION')->assertJsonPath('error.retryable', false)->json();
        $this->assertStringNotContainsString('retry', strtolower($configured['error']['message']));
        $this->getJson('/api/v1/_diagnostics-test/provider-invalid-config')->assertStatus(503)
            ->assertJsonPath('error.code', 'LLM_PROVIDER_CONFIGURATION')->assertJsonPath('error.retryable', false);
        $malformedResponse = $this->withHeader('X-Request-ID', 'req_malformed_output')->getJson('/api/v1/_diagnostics-test/provider-malformed-output')->assertStatus(503)
            ->assertJsonPath('error.code', 'LLM_OUTPUT_INVALID')->assertJsonPath('error.retryable', false);
        $this->assertSame('req_malformed_output', $malformedResponse->json('error.request_id'));
        $this->assertSame(1, DB::table('diagnostic_occurrences as occurrence')
            ->join('diagnostic_incidents as incident', 'incident.id', '=', 'occurrence.incident_id')
            ->where('occurrence.request_id', 'req_malformed_output')->where('incident.error_code', 'LLM_OUTPUT_INVALID')->count());
        $this->assertSame(0, DB::table('diagnostic_occurrences as occurrence')
            ->join('diagnostic_incidents as incident', 'incident.id', '=', 'occurrence.incident_id')
            ->where('occurrence.request_id', 'req_malformed_output')->where('incident.error_code', 'LLM_PROVIDER_UNAVAILABLE')->count());
        $this->assertSame('LLM_OUTPUT_INVALID', ErrorCatalog::providerFailureCode(
            new LlmProviderException(LlmProviderException::MALFORMED_OUTPUT),
        ));
        $categories = [
            LlmProviderException::TRANSPORT => ['LLM_PROVIDER_UNAVAILABLE', true],
            LlmProviderException::RATE_LIMITED => ['LLM_PROVIDER_RATE_LIMITED', true],
            LlmProviderException::TEMPORARY_UNAVAILABLE => ['LLM_PROVIDER_UNAVAILABLE', true],
            LlmProviderException::NOT_CONFIGURED => ['LLM_PROVIDER_CONFIGURATION', false],
            LlmProviderException::INVALID_CONFIGURATION => ['LLM_PROVIDER_CONFIGURATION', false],
            LlmProviderException::MALFORMED_OUTPUT => ['LLM_OUTPUT_INVALID', false],
            LlmProviderException::REFUSAL => ['LLM_REQUEST_REFUSED', false],
            LlmProviderException::INCOMPLETE => ['LLM_RESPONSE_INCOMPLETE', false],
            LlmProviderException::PROVIDER => ['LLM_PROVIDER_FAILED', false],
        ];
        foreach ($categories as $category => [$code, $retryable]) {
            $entry = ErrorCatalog::classify(new LlmProviderException($category));
            $this->assertSame($code, $entry['code']);
            $this->assertSame($retryable, $entry['retryable']);
            $this->assertSame('ERROR', $entry['severity']);
            $this->assertSame($code, ErrorCatalog::providerFailureCode(new LlmProviderException($category)));
        }
    }

    public function test_http_exception_response_preserves_only_safe_headers(): void
    {
        $response = $this->getJson('/api/v1/_diagnostics-test/http-exception-headers')->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');

        $this->assertSame('30', $response->headers->get('Retry-After'));
        $this->assertSame('60', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('0', $response->headers->get('X-RateLimit-Remaining'));
        $this->assertSame('1234567890', $response->headers->get('X-RateLimit-Reset'));
        $this->assertSame('60;w=60', $response->headers->get('RateLimit-Policy'));
        $this->assertNull($response->headers->get('Set-Cookie'));
        $this->assertNull($response->headers->get('Location'));
        $this->assertNull($response->headers->get('RateLimit-Remaining'));
        $this->assertStringNotContainsString('SECRET_CANARY', $response->getContent());

        $methodError = $this->getJson('/api/v1/_diagnostics-test/method-only')->assertStatus(405)
            ->assertJsonPath('error.code', 'REQUEST_REJECTED');
        $this->assertStringContainsString('POST', (string) $methodError->headers->get('Allow'));

        $serviceUnavailable = $this->withHeader('X-Request-ID', 'req_http_503')->getJson('/api/v1/_diagnostics-test/http-service-unavailable')
            ->assertStatus(503)->assertJsonPath('error.code', 'INTERNAL_ERROR')->assertJsonPath('error.retryable', false);
        $this->assertSame('req_http_503', $serviceUnavailable->json('error.request_id'));
        $this->assertSame('req_http_503', $serviceUnavailable->headers->get('X-Request-ID'));
        $this->assertSame('20', $serviceUnavailable->headers->get('Retry-After'));
        $this->assertNull($serviceUnavailable->headers->get('Set-Cookie'));
        $this->assertStringContainsString('application/json', (string) $serviceUnavailable->headers->get('Content-Type'));
        $this->assertStringNotContainsString('SECRET_CANARY', $serviceUnavailable->getContent());

        $this->getJson('/api/v1/career/_diagnostics-test/unclassified')->assertStatus(500)
            ->assertJsonPath('error.code', 'INTERNAL_ERROR')->assertJsonPath('error.retryable', false);
    }

    public function test_throttled_api_responses_use_the_retryable_rate_limit_contract(): void
    {
        $this->getJson('/api/v1/_diagnostics-test/throttled')->assertOk();
        $this->getJson('/api/v1/_diagnostics-test/throttled')->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED')->assertJsonPath('error.retryable', true)
            ->assertJsonPath('error.message', 'Too many requests. Please wait and try again.')
            ->assertHeader('Retry-After')->assertHeader('X-RateLimit-Limit', '1')
            ->assertHeader('X-RateLimit-Remaining', '0')->assertHeader('X-RateLimit-Reset');
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
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?search=req_test')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.last_page', 1);
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents/'.$id)->assertOk()
            ->assertJsonPath('data.occurrences.0.request_id', 'req_test')
            ->assertJsonPath('data.incident.retryable', false)
            ->assertJsonPath('data.incident.impact', 'The affected operation did not complete.')
            ->assertJsonPath('data.incident.recovery_action', 'Inspect the sanitized incident details and dependency health.');
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
            'operation' => 'application_draft_generation', 'provider' => 'openai',
        ]);
        $incidentId = DB::table('diagnostic_incidents')->value('id');

        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?application_id='.$applicationId)
            ->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $incidentId)
            ->assertJsonPath('data.data.0.latest_operation', 'application_draft_generation')
            ->assertJsonPath('data.data.0.latest_provider', 'openai');
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?search='.$applicationId)
            ->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $incidentId);
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents/'.$incidentId)
            ->assertOk()->assertJsonPath('data.occurrences.0.application_id', $applicationId);
    }

    public function test_incident_list_prioritizes_active_severity_and_supports_relative_time_filter(): void
    {
        $admin = $this->user('admin');
        $recorder = app(IncidentRecorder::class);
        $recorder->record('INTERNAL_ERROR', 'Old resolved.', 'old');
        $recorder->record('INTERNAL_ERROR', 'Open warning.', 'warning', 'WARNING');
        $recorder->record('INTERNAL_ERROR', 'Open critical.', 'critical', 'CRITICAL');
        DB::table('diagnostic_incidents')->where('component', 'old')->update([
            'status' => 'RESOLVED', 'severity' => 'CRITICAL', 'last_seen_at' => now()->subHours(25),
        ]);
        DB::table('diagnostic_incidents')->where('component', 'warning')->update(['last_seen_at' => now()->subMinute()]);

        $response = $this->as($admin)->getJson('/api/v1/diagnostics/incidents')->assertOk();
        $this->assertSame(['critical', 'warning', 'old'], array_column($response->json('data.data'), 'component'));
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?hours=24')->assertOk()
            ->assertJsonPath('data.total', 2);
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?sort=last_seen')->assertOk()
            ->assertJsonPath('data.data.0.component', 'critical');
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?sort=invalid')->assertUnprocessable();
    }

    public function test_provider_filter_matches_latest_provider_and_combines_with_other_filters(): void
    {
        $admin = $this->user('admin');
        $recorder = app(IncidentRecorder::class);
        $recorder->record('LLM_PROVIDER_UNAVAILABLE', 'Provider failed.', 'vacancy', 'ERROR', context: [
            'provider' => 'old-provider', 'request_id' => 'req_provider_match',
        ]);
        $recorder->record('LLM_PROVIDER_UNAVAILABLE', 'Provider failed.', 'vacancy', 'ERROR', context: [
            'provider' => 'openai', 'request_id' => 'req_provider_match',
        ]);
        $id = DB::table('diagnostic_incidents')->value('id');
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?provider=openai')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $id)
            ->assertJsonPath('data.data.0.latest_provider', 'openai');
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?provider=missing')->assertOk()->assertJsonPath('data.total', 0);
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?provider=old-provider')->assertOk()->assertJsonPath('data.total', 0);
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?provider=openai&severity=ERROR&status=OPEN&hours=24&search=req_provider_match')
            ->assertOk()->assertJsonPath('data.total', 1);
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?provider=openai&status=RESOLVED')->assertOk()->assertJsonPath('data.total', 0);
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?provider=openai&from='.now()->addDay()->toDateString())
            ->assertOk()->assertJsonPath('data.total', 0);
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?provider='.str_repeat('a', 65))->assertUnprocessable();
    }

    public function test_tied_occurrence_timestamps_use_descending_id_for_every_latest_view(): void
    {
        $admin = $this->user('admin');
        $exception = new LlmProviderException(LlmProviderException::RATE_LIMITED, retryAfterSeconds: 42);
        $recorder = app(IncidentRecorder::class);
        $recorder->record('LLM_PROVIDER_RATE_LIMITED', 'Rate limited.', 'tied-occurrences', exception: $exception);
        $recorder->record('LLM_PROVIDER_RATE_LIMITED', 'Rate limited.', 'tied-occurrences', exception: $exception);
        $id = DB::table('diagnostic_incidents')->where('component', 'tied-occurrences')->value('id');
        $ids = DB::table('diagnostic_occurrences')->where('incident_id', $id)->orderBy('id')->pluck('id');
        $this->assertCount(2, $ids);
        $timestamp = now()->subMinute()->startOfSecond();
        DB::table('diagnostic_occurrences')->where('id', $ids[0])->update([
            'created_at' => $timestamp, 'provider' => 'older', 'request_id' => 'req_older',
            'llm_run_id' => (string) Str::ulid(), 'application_id' => (string) Str::ulid(),
            'attempt' => 1, 'retry_after_seconds' => 5,
        ]);
        $latestRun = (string) Str::ulid();
        $latestApplication = (string) Str::ulid();
        DB::table('diagnostic_occurrences')->where('id', $ids[1])->update([
            'created_at' => $timestamp, 'provider' => 'newer', 'request_id' => 'req_newer',
            'llm_run_id' => $latestRun, 'application_id' => $latestApplication,
            'attempt' => 2, 'retry_after_seconds' => 42,
        ]);

        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?provider=newer')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.latest_provider', 'newer');
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?provider=older')->assertOk()
            ->assertJsonPath('data.total', 0);
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents/'.$id)->assertOk()
            ->assertJsonPath('data.occurrences.0.id', $ids[1])
            ->assertJsonPath('data.occurrences.0.provider', 'newer')
            ->assertJsonPath('data.occurrences.0.request_id', 'req_newer')
            ->assertJsonPath('data.occurrences.0.llm_run_id', $latestRun)
            ->assertJsonPath('data.occurrences.0.application_id', $latestApplication)
            ->assertJsonPath('data.occurrences.0.attempt', 2)
            ->assertJsonPath('data.occurrences.0.retry_after_seconds', 42)
            ->assertJsonPath('data.occurrences.1.id', $ids[0]);
    }

    public function test_provider_retry_delay_is_bounded_and_exposed_only_when_valid(): void
    {
        $admin = $this->user('admin');
        foreach ([42, null, -1, 86401] as $delay) {
            app(IncidentRecorder::class)->record('LLM_PROVIDER_RATE_LIMITED', 'Rate limited.', 'component_'.($delay ?? 'missing'), exception: new LlmProviderException(LlmProviderException::RATE_LIMITED, retryAfterSeconds: $delay));
            $id = DB::table('diagnostic_incidents')->where('component', 'component_'.($delay ?? 'missing'))->value('id');
            $this->as($admin)->getJson('/api/v1/diagnostics/incidents/'.$id)->assertOk()
                ->assertJsonPath('data.occurrences.0.retry_after_seconds', $delay === 42 ? 42 : null);
        }
        app(IncidentRecorder::class)->record('LLM_PROVIDER_UNAVAILABLE', 'Provider temporarily unavailable.', 'temporary-delay', exception: new LlmProviderException(LlmProviderException::TEMPORARY_UNAVAILABLE, retryAfterSeconds: 41));
        $id = DB::table('diagnostic_incidents')->where('component', 'temporary-delay')->value('id');
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents/'.$id)->assertOk()
            ->assertJsonPath('data.occurrences.0.retry_after_seconds', 41);
    }

    public function test_repeated_status_transition_is_a_no_op_for_audit(): void
    {
        $admin = $this->user('admin');
        app(IncidentRecorder::class)->record('INTERNAL_ERROR', 'Safe failure.', 'api');
        $id = DB::table('diagnostic_incidents')->value('id');
        foreach (['RESOLVED', 'RESOLVED', 'IGNORED', 'IGNORED', 'OPEN', 'OPEN'] as $status) {
            $this->as($admin)->patchJson('/api/v1/diagnostics/incidents/'.$id, ['status' => $status])
                ->assertOk()->assertJsonPath('data.incident.status', $status);
        }
        $this->assertSame(3, DB::table('audit_events')->where('event_type', 'diagnostics.incident.status_changed')->count());
    }

    public function test_status_transition_rolls_back_when_audit_write_fails(): void
    {
        $admin = $this->user('admin');
        app(IncidentRecorder::class)->record('INTERNAL_ERROR', 'Safe failure.', 'api');
        $id = DB::table('diagnostic_incidents')->value('id');
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andThrow(new \RuntimeException('audit unavailable'));
        $this->app->instance(AuditLogger::class, $audit);

        $this->as($admin)->patchJson('/api/v1/diagnostics/incidents/'.$id, ['status' => 'RESOLVED'])->assertStatus(500);
        $this->assertSame('OPEN', DB::table('diagnostic_incidents')->where('id', $id)->value('status'));
        $this->assertSame(0, DB::table('audit_events')->where('subject_id', $id)->count());
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

    public function test_browser_report_returns_a_safe_failure_when_no_diagnostic_sink_accepts_it(): void
    {
        $user = $this->user('user');
        Log::shouldReceive('sharedContext')->once()->andReturn([]);
        Log::shouldReceive('log')->once()->andThrow(new \RuntimeException('log sink unavailable'));
        DB::shouldReceive('transaction')->once()->andThrow(new \RuntimeException('database unavailable'));

        $response = $this->as($user)->postJson('/api/v1/diagnostics/report', ['component' => 'browser', 'kind' => 'runtime'])
            ->assertStatus(503)->assertJsonPath('error.code', 'DIAGNOSTICS_UNAVAILABLE')->assertJsonPath('error.retryable', true);
        $this->assertSame($response->json('error.request_id'), $response->headers->get('X-Request-ID'));
    }

    public function test_terminal_llm_failures_stop_queue_jobs_and_transient_failures_use_bounded_retry_delays(): void
    {
        $user = $this->user('user');
        $vacancy = Vacancy::query()->create(['owner_id' => $user->id, 'title' => 'Engineer', 'company' => 'Example']);
        $snapshot = VacancySnapshot::record((string) $user->id, (string) $vacancy->id, 1, 'Source text.', null, hash('sha256', 'Source text.'), now());
        $analysisFailure = new LlmProviderException(LlmProviderException::INVALID_CONFIGURATION);
        Vacancy::query()->whereKey($vacancy->id)->update(['analysis_status' => Vacancy::STATUS_FAILED, 'error_code' => 'PROVIDER_ERROR']);
        $analysis = (new AnalyzeVacancy((string) $user->id, (string) $snapshot->id))->withFakeQueueInteractions();
        $analysisService = Mockery::mock(VacancyAnalysisService::class);
        $analysisService->shouldReceive('analyze')->once()->andThrow($analysisFailure);
        $analysis->handle($analysisService, app(DatabaseOwnerContext::class));
        $analysis->assertFailedWith(LlmProviderException::class);
        $this->assertDatabaseHas('vacancies', [
            'id' => $vacancy->id, 'analysis_status' => Vacancy::STATUS_FAILED, 'error_code' => 'PROVIDER_ERROR',
        ]);
        $this->assertSame([5, 30], $analysis->backoff());

        $analysisRateLimit = new LlmProviderException(LlmProviderException::RATE_LIMITED, retryAfterSeconds: 37);
        Vacancy::query()->whereKey($vacancy->id)->update(['analysis_status' => Vacancy::STATUS_FAILED, 'error_code' => 'PROVIDER_ERROR']);
        $analysisRetry = (new AnalyzeVacancy((string) $user->id, (string) $snapshot->id))->withFakeQueueInteractions();
        $analysisRetryService = Mockery::mock(VacancyAnalysisService::class);
        $analysisRetryService->shouldReceive('analyze')->once()->andThrow($analysisRateLimit);
        $analysisRetry->handle($analysisRetryService, app(DatabaseOwnerContext::class));
        $analysisRetry->assertReleased(37)->assertNotFailed();
        $this->assertDatabaseHas('vacancies', [
            'id' => $vacancy->id, 'analysis_status' => Vacancy::STATUS_PENDING, 'error_code' => null,
        ]);

        $analysisTemporary = new LlmProviderException(LlmProviderException::TEMPORARY_UNAVAILABLE, retryAfterSeconds: 41);
        Vacancy::query()->whereKey($vacancy->id)->update(['analysis_status' => Vacancy::STATUS_FAILED, 'error_code' => 'PROVIDER_ERROR']);
        $analysisTemporaryJob = (new AnalyzeVacancy((string) $user->id, (string) $snapshot->id))->withFakeQueueInteractions();
        $analysisTemporaryService = Mockery::mock(VacancyAnalysisService::class);
        $analysisTemporaryService->shouldReceive('analyze')->once()->andThrow($analysisTemporary);
        $analysisTemporaryJob->handle($analysisTemporaryService, app(DatabaseOwnerContext::class));
        $analysisTemporaryJob->assertReleased(41)->assertNotFailed();

        $profile = CareerProfile::query()->create(['owner_id' => $user->id]);
        $source = CareerSource::query()->create([
            'owner_id' => $user->id, 'career_profile_id' => $profile->id, 'kind' => 'PASTED_TEXT',
            'source_text' => 'Career source.', 'content_hash' => hash('sha256', 'Career source.'),
        ]);
        $careerFailure = new LlmProviderException(LlmProviderException::NOT_CONFIGURED);
        CareerSource::query()->whereKey($source->id)->update(['extraction_status' => CareerSource::STATUS_FAILED, 'error_code' => 'PROVIDER_ERROR']);
        $career = (new ExtractCareerSource((string) $user->id, (string) $source->id))->withFakeQueueInteractions();
        $careerService = Mockery::mock(CareerExtractionService::class);
        $careerService->shouldReceive('extract')->once()->andThrow($careerFailure);
        $career->handle($careerService);
        $career->assertFailedWith(LlmProviderException::class);
        $this->assertDatabaseHas('career_sources', [
            'id' => $source->id, 'extraction_status' => CareerSource::STATUS_FAILED, 'error_code' => 'PROVIDER_ERROR',
        ]);
        $this->assertSame([5, 30], $career->backoff());

        $retryableFailure = new LlmProviderException(LlmProviderException::TRANSPORT);
        CareerSource::query()->whereKey($source->id)->update(['extraction_status' => CareerSource::STATUS_FAILED, 'error_code' => 'PROVIDER_ERROR']);
        $retryableJob = (new ExtractCareerSource((string) $user->id, (string) $source->id))->withFakeQueueInteractions();
        $retryableService = Mockery::mock(CareerExtractionService::class);
        $retryableService->shouldReceive('extract')->once()->andThrow($retryableFailure);
        $retryableJob->handle($retryableService);
        $retryableJob->assertReleased(5)->assertNotFailed();
        $this->assertDatabaseHas('career_sources', [
            'id' => $source->id, 'extraction_status' => CareerSource::STATUS_PENDING, 'error_code' => null,
        ]);

        $careerRateLimit = new LlmProviderException(LlmProviderException::RATE_LIMITED, retryAfterSeconds: 37);
        CareerSource::query()->whereKey($source->id)->update(['extraction_status' => CareerSource::STATUS_FAILED, 'error_code' => 'PROVIDER_ERROR']);
        $careerRateLimitJob = (new ExtractCareerSource((string) $user->id, (string) $source->id))->withFakeQueueInteractions();
        $careerRateLimitService = Mockery::mock(CareerExtractionService::class);
        $careerRateLimitService->shouldReceive('extract')->once()->andThrow($careerRateLimit);
        $careerRateLimitJob->handle($careerRateLimitService);
        $careerRateLimitJob->assertReleased(37)->assertNotFailed();
        $this->assertDatabaseHas('career_sources', [
            'id' => $source->id, 'extraction_status' => CareerSource::STATUS_PENDING, 'error_code' => null,
        ]);

        $careerTemporary = new LlmProviderException(LlmProviderException::TEMPORARY_UNAVAILABLE, retryAfterSeconds: 41);
        CareerSource::query()->whereKey($source->id)->update(['extraction_status' => CareerSource::STATUS_FAILED, 'error_code' => 'PROVIDER_ERROR']);
        $careerTemporaryJob = (new ExtractCareerSource((string) $user->id, (string) $source->id))->withFakeQueueInteractions();
        $careerTemporaryService = Mockery::mock(CareerExtractionService::class);
        $careerTemporaryService->shouldReceive('extract')->once()->andThrow($careerTemporary);
        $careerTemporaryJob->handle($careerTemporaryService);
        $careerTemporaryJob->assertReleased(41)->assertNotFailed();

        CareerSource::query()->whereKey($source->id)->update(['extraction_status' => CareerSource::STATUS_FAILED, 'error_code' => 'PROVIDER_ERROR']);
        $careerFinalJob = (new ExtractCareerSource((string) $user->id, (string) $source->id))->withFakeQueueInteractions();
        $careerFinalJob->job->attempts = 3;
        $careerFinalService = Mockery::mock(CareerExtractionService::class);
        $careerFinalService->shouldReceive('extract')->once()->andThrow($careerRateLimit);
        $careerFinalJob->handle($careerFinalService);
        $careerFinalJob->assertFailedWith(LlmProviderException::class)->assertNotReleased();
        $this->assertDatabaseHas('career_sources', [
            'id' => $source->id, 'extraction_status' => CareerSource::STATUS_FAILED, 'error_code' => 'PROVIDER_ERROR',
        ]);

        Vacancy::query()->whereKey($vacancy->id)->update(['analysis_status' => Vacancy::STATUS_FAILED, 'error_code' => 'PROVIDER_ERROR']);
        $analysisFinalJob = (new AnalyzeVacancy((string) $user->id, (string) $snapshot->id))->withFakeQueueInteractions();
        $analysisFinalJob->job->attempts = 3;
        $analysisFinalService = Mockery::mock(VacancyAnalysisService::class);
        $analysisFinalService->shouldReceive('analyze')->once()->andThrow($analysisRateLimit);
        $analysisFinalJob->handle($analysisFinalService, app(DatabaseOwnerContext::class));
        $analysisFinalJob->assertFailedWith(LlmProviderException::class)->assertNotReleased();
        $this->assertDatabaseHas('vacancies', [
            'id' => $vacancy->id, 'analysis_status' => Vacancy::STATUS_FAILED, 'error_code' => 'PROVIDER_ERROR',
        ]);
    }

    public function test_expected_missing_console_record_has_a_safe_operator_message(): void
    {
        $this->artisan('user:enable', ['id' => (string) Str::ulid()])
            ->assertExitCode(1)
            ->expectsOutput('The requested record was not found.');
        $this->assertDatabaseMissing('diagnostic_incidents', ['error_code' => 'CLI_COMMAND_FAILED']);
    }

    public function test_final_queue_failure_is_correlated(): void
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('payload')->andReturn(['cvortex' => ['request_id' => 'req_queue']]);
        $job->shouldReceive('getJobId')->andReturn('job_test');
        $job->shouldReceive('getQueue')->andReturn('analysis-high');
        $job->shouldReceive('resolveName')->andReturn('TestJob');
        $job->shouldReceive('attempts')->andReturn(3);
        Log::spy();
        Event::dispatch(new JobFailed('sync', $job, new \RuntimeException('failed')));
        $this->assertDatabaseHas('diagnostic_incidents', ['error_code' => 'QUEUE_JOB_FAILED', 'occurrence_count' => 1]);
        $this->assertDatabaseHas('diagnostic_occurrences', [
            'request_id' => 'req_queue', 'job_id' => 'job_test', 'attempt' => 3,
            'queue' => 'analysis-high', 'connection' => 'sync',
        ]);
        Log::shouldHaveReceived('log')->once()->withArgs(fn ($level, $message, $context): bool => $message === 'diagnostics.incident' && $context['queue'] === 'analysis-high' && $context['connection'] === 'sync');
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
