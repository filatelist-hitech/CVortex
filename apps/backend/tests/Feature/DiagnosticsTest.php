<?php

namespace Tests\Feature;

use App\AI\Exceptions\LlmProviderException;
use App\AI\ProviderRetryAfter;
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
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Log\Logger;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
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
        Route::get('/api/v1/_diagnostics-test/provider-temporary-retry', fn () => throw new LlmProviderException(
            LlmProviderException::TEMPORARY_UNAVAILABLE,
            retryAfterSeconds: 22,
        ));
        Route::get('/api/v1/_diagnostics-test/provider-config', fn () => throw new LlmProviderException(LlmProviderException::NOT_CONFIGURED));
        Route::get('/api/v1/_diagnostics-test/provider-config-retry-header', fn () => throw new LlmProviderException(
            LlmProviderException::NOT_CONFIGURED,
            retryAfterSeconds: 45,
        ));
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

    public function test_raw_log_success_marks_the_exception_before_a_postgres_failure(): void
    {
        $original = new \RuntimeException('password=SECRET_CANARY');
        Route::get('/api/v1/_diagnostics-test/log-only', static function () use ($original): never {
            throw $original;
        });
        Log::spy();
        DB::shouldReceive('transaction')->once()->andThrow(new \RuntimeException('database unavailable'));

        $response = $this->withHeader('X-Request-ID', 'req_log_only')
            ->getJson('/api/v1/_diagnostics-test/log-only')
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'INTERNAL_ERROR')
            ->assertJsonPath('error.retryable', false);

        $this->assertStringNotContainsString('SECRET_CANARY', $response->getContent());
        $this->assertTrue(IncidentRecorder::wasRecorded($original));
        Log::shouldHaveReceived('log')->once()->withArgs(function (string $level, string $message, array $context): bool {
            return $level === 'error'
                && $message === 'diagnostics.incident'
                && ($context['exception_class'] ?? null) === \RuntimeException::class
                && ! str_contains(json_encode($context), 'SECRET_CANARY');
        });
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

    public function test_closure_frame_names_keep_basename_and_line_without_absolute_paths(): void
    {
        $safe = Redactor::frameFunction('{closure:/var/www/html/SECRET_PATH_CANARY/routes/api.php:27}');

        $this->assertSame('{closure:api.php:27}', $safe);
        $this->assertStringNotContainsString('/var/www/html', $safe);
        $this->assertStringNotContainsString('SECRET_PATH_CANARY', $safe);
    }

    public function test_all_supported_application_log_channels_use_structured_redaction(): void
    {
        $channels = ['stack', 'single', 'daily', 'monthly', 'stderr'];
        $configuredChannels = array_diff(array_keys(config('logging.channels')), ['emergency']);
        foreach ($configuredChannels as $channel) {
            $this->assertContains(StructuredLogs::class, config('logging.channels.'.$channel.'.tap', []), $channel.' must use StructuredLogs.');
        }

        $original = [
            'default' => config('logging.default'),
            'single_path' => config('logging.channels.single.path'),
            'daily_path' => config('logging.channels.daily.path'),
            'monthly_path' => config('logging.channels.monthly.path'),
            'stderr_stream' => config('logging.channels.stderr.handler_with.stream'),
            'stack_channels' => config('logging.channels.stack.channels'),
        ];
        $directory = storage_path('framework/testing/log-channel-'.Str::random(12));
        @mkdir($directory, 0777, true);
        $paths = [];

        try {
            foreach ($channels as $channel) {
                $path = $directory.'/'.$channel.'.log';
                $paths[] = $directory.'/'.$channel;
                config([
                    'logging.default' => $channel,
                    'logging.channels.single.path' => $path,
                    'logging.channels.daily.path' => $path,
                    'logging.channels.monthly.path' => $path,
                    'logging.channels.stderr.handler_with.stream' => $path,
                    'logging.channels.stack.channels' => ['single'],
                ]);
                foreach (['stack', 'single', 'daily', 'monthly', 'stderr'] as $cached) {
                    Log::forgetChannel($cached);
                }
                $canary = 'LOG_CHANNEL_SECRET_'.$channel;
                Log::shareContext(['request_id' => 'req_'.$channel]);
                Log::channel()->info('password='.$canary);
                Log::flushSharedContext();

                $files = glob($directory.'/'.$channel.'*.log') ?: [];
                $this->assertCount(1, $files, $channel.' must write one rotated log file at '.$path.'; directory contains '.json_encode(scandir($directory)));
                $record = file_get_contents($files[0]);
                $this->assertIsString($record);
                json_decode(trim($record), true, flags: JSON_THROW_ON_ERROR);
                $this->assertStringNotContainsString($canary, $record);
                $this->assertStringContainsString('password=[REDACTED]', $record);
                $this->assertStringContainsString('"request_id":"req_'.$channel.'"', $record);
                $this->assertStringContainsString('"message"', $record);
            }
        } finally {
            Log::flushSharedContext();
            foreach (['stack', 'single', 'daily', 'monthly', 'stderr'] as $channel) {
                Log::forgetChannel($channel);
            }
            foreach ($paths as $path) {
                foreach (glob($path.'*') ?: [] as $file) {
                    @unlink($file);
                }
            }
            @rmdir($directory);
            config([
                'logging.default' => $original['default'],
                'logging.channels.single.path' => $original['single_path'],
                'logging.channels.daily.path' => $original['daily_path'],
                'logging.channels.monthly.path' => $original['monthly_path'],
                'logging.channels.stderr.handler_with.stream' => $original['stderr_stream'],
                'logging.channels.stack.channels' => $original['stack_channels'],
            ]);
        }
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
        $temporary = $this->withHeader('X-Request-ID', 'req_provider_temporary')->getJson('/api/v1/_diagnostics-test/provider-temporary')
            ->assertStatus(503)->assertJsonPath('error.code', 'LLM_PROVIDER_UNAVAILABLE')->assertJsonPath('error.retryable', true);
        $this->assertNull($temporary->headers->get('Retry-After'));
        $temporaryRetry = $this->withHeader('X-Request-ID', 'req_provider_temporary_retry')
            ->getJson('/api/v1/_diagnostics-test/provider-temporary-retry')
            ->assertStatus(503)->assertJsonPath('error.code', 'LLM_PROVIDER_UNAVAILABLE')->assertJsonPath('error.retryable', true);
        $this->assertSame('22', $temporaryRetry->headers->get('Retry-After'));
        $configured = $this->getJson('/api/v1/_diagnostics-test/provider-config')->assertStatus(503)
            ->assertJsonPath('error.code', 'LLM_PROVIDER_CONFIGURATION')->assertJsonPath('error.retryable', false)->json();
        $this->assertStringNotContainsString('retry', strtolower($configured['error']['message']));
        $notRetryable = $this->getJson('/api/v1/_diagnostics-test/provider-config-retry-header')->assertStatus(503)
            ->assertJsonPath('error.code', 'LLM_PROVIDER_CONFIGURATION')->assertJsonPath('error.retryable', false);
        $this->assertNull($notRetryable->headers->get('Retry-After'));
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

    public function test_status_change_rolls_back_when_audit_recording_fails(): void
    {
        $admin = $this->user('admin');
        app(IncidentRecorder::class)->record('INTERNAL_ERROR', 'A safe failure.', 'audit-rollback');
        $id = DB::table('diagnostic_incidents')->where('component', 'audit-rollback')->value('id');
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andThrow(new \RuntimeException('audit unavailable'));
        $this->app->instance(AuditLogger::class, $audit);
        $this->withoutExceptionHandling();

        try {
            $this->as($admin)->patchJson('/api/v1/diagnostics/incidents/'.$id, ['status' => 'RESOLVED']);
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('audit unavailable', $exception->getMessage());
        }

        $this->assertDatabaseHas('diagnostic_incidents', ['id' => $id, 'status' => 'OPEN']);
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'diagnostics.incident.status_changed', 'subject_id' => $id,
        ]);
    }

    public function test_diagnostics_error_responses_match_the_openapi_error_shape(): void
    {
        $admin = $this->user('admin');
        $oversized = $this->as($admin)->postJson('/api/v1/diagnostics/report', [
            'component' => 'browser',
            'kind' => 'runtime',
            'unused' => str_repeat('A', 2100),
        ])->assertStatus(413)->assertJsonStructure([
            'message',
            'error' => ['code', 'message', 'request_id', 'retryable'],
        ]);
        $this->assertSame($oversized->json('error.request_id'), $oversized->headers->get('X-Request-ID'));

        $invalidFilter = $this->as($admin)->getJson('/api/v1/diagnostics/incidents?severity=DEBUG')
            ->assertStatus(422)->assertJsonStructure([
                'message',
                'error' => ['code', 'message', 'request_id', 'retryable'],
                'errors' => ['severity'],
            ]);
        $this->assertSame($invalidFilter->json('error.request_id'), $invalidFilter->headers->get('X-Request-ID'));
    }

    public function test_diagnostics_incident_detail_and_update_require_authentication(): void
    {
        app(IncidentRecorder::class)->record('INTERNAL_ERROR', 'Safe failure.', 'openapi-auth');
        $incidentId = DB::table('diagnostic_incidents')->where('component', 'openapi-auth')->value('id');
        $this->getJson('/api/v1/diagnostics/incidents/'.$incidentId)->assertUnauthorized();
        $this->patchJson('/api/v1/diagnostics/incidents/'.$incidentId, ['status' => 'RESOLVED'])->assertUnauthorized();
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
        $firstErrorRef = str_repeat('a', 64);
        $secondErrorRef = str_repeat('b', 64);
        $this->as($user)->postJson('/api/v1/diagnostics/report', [
            'component' => 'app-root', 'kind' => 'runtime', 'user_id' => 'other',
            'stack' => 'Authorization: Bearer SECRET_CANARY', 'message' => 'api_key=SECRET_CANARY',
            'route' => '/', 'error_ref' => $firstErrorRef,
        ])->assertStatus(202);
        $event = DB::table('diagnostic_occurrences')->first();
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('/', $event->route);
        $this->assertSame($firstErrorRef, $event->error_ref);
        $this->assertStringNotContainsString('SECRET_CANARY', json_encode($event));
        $this->as($user)->postJson('/api/v1/diagnostics/report', [
            'component' => 'app-root', 'kind' => 'runtime', 'route' => '/', 'error_ref' => $secondErrorRef,
            'message' => 'email=pii@example.test password=SECRET_CANARY',
        ])->assertStatus(202);
        $this->as($user)->postJson('/api/v1/diagnostics/report', ['component' => 'browser', 'kind' => 'rejection'])->assertStatus(202);
        $this->as($user)->postJson('/api/v1/diagnostics/report', ['component' => 'global-root', 'kind' => 'render'])->assertStatus(202);
        $this->assertDatabaseCount('diagnostic_incidents', 3);
        $this->assertDatabaseCount('diagnostic_occurrences', 4);
        $this->assertSame([$firstErrorRef, $secondErrorRef], DB::table('diagnostic_occurrences as occurrence')
            ->join('diagnostic_incidents as incident', 'incident.id', '=', 'occurrence.incident_id')
            ->where('incident.component', 'app-root')->orderBy('occurrence.error_ref')->pluck('occurrence.error_ref')->all());
        $this->as($user)->postJson('/api/v1/diagnostics/report', ['component' => '../../bad', 'kind' => 'runtime'])->assertUnprocessable();
        $this->as($user)->postJson('/api/v1/diagnostics/report', [
            'component' => 'browser',
            'kind' => 'runtime',
            'route' => '/?api_key=SECRET_CANARY',
            'error_ref' => 'not-a-digest',
        ])->assertUnprocessable();
        foreach (range(1, 4) as $index) {
            $this->as($user)->postJson('/api/v1/diagnostics/report', [
                'component' => 'client-component-'.$index,
                'kind' => 'runtime',
            ])->assertUnprocessable();
        }
        $this->assertDatabaseCount('diagnostic_incidents', 3);
        $this->assertDatabaseCount('diagnostic_occurrences', 4);
        $this->assertStringNotContainsString('SECRET_CANARY', json_encode(DB::table('diagnostic_occurrences')->get()));
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

    public function test_unique_job_locks_cover_the_maximum_provider_retry_window(): void
    {
        $careerJob = new ExtractCareerSource('owner-id', 'source-id');
        $vacancyJob = new AnalyzeVacancy('owner-id', 'snapshot-id');
        $maximumRetryWindow = ProviderRetryAfter::MAX_SECONDS * ($careerJob->tries - 1);

        $this->assertGreaterThanOrEqual($maximumRetryWindow, $careerJob->uniqueFor);
        $this->assertGreaterThanOrEqual($maximumRetryWindow, $vacancyJob->uniqueFor);
    }

    public function test_transient_queue_attempt_logs_warning_then_succeeds_without_error_incident(): void
    {
        $user = $this->user('user');
        $profile = CareerProfile::query()->create(['owner_id' => $user->id]);
        $source = CareerSource::query()->create([
            'owner_id' => $user->id, 'career_profile_id' => $profile->id, 'kind' => 'PASTED_TEXT',
            'source_text' => 'Career retry fixture.', 'content_hash' => hash('sha256', 'Career retry fixture.'),
            'extraction_status' => CareerSource::STATUS_FAILED, 'error_code' => 'PROVIDER_ERROR',
        ]);
        $llmRunId = (string) Str::ulid();
        Log::spy();
        Log::shouldReceive('sharedContext')->once()->andReturn([
            'request_id' => 'req_retry_warning',
            'job_id' => 'job_retry_warning',
            'llm_run_id' => $llmRunId,
        ]);
        Log::shouldReceive('warning')->once()->withArgs(function (string $message, array $context) use ($llmRunId): bool {
            return $message === 'diagnostics.provider_retry_scheduled'
                && $context['event_name'] === 'llm.provider_retry_scheduled'
                && $context['request_id'] === 'req_retry_warning'
                && $context['job_id'] === 'job_retry_warning'
                && $context['llm_run_id'] === $llmRunId
                && $context['operation'] === 'career_text_extraction'
                && $context['provider'] === 'openai'
                && $context['attempt'] === 1
                && $context['retry_delay_seconds'] === 37;
        });

        $retry = (new ExtractCareerSource((string) $user->id, (string) $source->id))->withFakeQueueInteractions();
        $failedAttempt = Mockery::mock(CareerExtractionService::class);
        $failedAttempt->shouldReceive('extract')->once()->andThrow(new LlmProviderException(
            LlmProviderException::TRANSPORT,
            providerName: 'openai',
            retryAfterSeconds: 37,
        ));
        $retry->handle($failedAttempt);
        $retry->assertReleased(37)->assertNotFailed();
        $this->assertDatabaseHas('career_sources', [
            'id' => $source->id, 'extraction_status' => CareerSource::STATUS_PENDING, 'error_code' => null,
        ]);

        $success = (new ExtractCareerSource((string) $user->id, (string) $source->id))->withFakeQueueInteractions();
        $successfulAttempt = Mockery::mock(CareerExtractionService::class);
        $successfulAttempt->shouldReceive('extract')->once()->andReturnUsing(function () use ($source): CareerSource {
            CareerSource::query()->whereKey($source->id)->update(['extraction_status' => CareerSource::STATUS_COMPLETED]);

            return $source->fresh();
        });
        $success->handle($successfulAttempt);

        $this->assertDatabaseHas('career_sources', [
            'id' => $source->id, 'extraction_status' => CareerSource::STATUS_COMPLETED, 'error_code' => null,
        ]);
        $this->assertDatabaseCount('diagnostic_incidents', 0);
        $this->assertDatabaseCount('diagnostic_occurrences', 0);
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

    public function test_sync_job_restores_request_log_context_after_success_and_exception(): void
    {
        config(['queue.default' => 'sync']);
        $messages = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$messages): void {
            if (in_array($event->message, ['diagnostics.inline_job', 'diagnostics.controller_after_job'], true)) {
                $messages[$event->message] = $event->context;
            }
        });
        Route::get('/api/v1/_diagnostics-test/sync-context/{outcome}', function (string $outcome) {
            Log::shareContext(['request_scope' => 'controller']);
            DiagnosticsContextJob::$during = [];
            try {
                DiagnosticsContextJob::dispatch($outcome === 'fail');
            } catch (\RuntimeException) {
                // The controller continues after the inline job failure.
            }
            Log::info('diagnostics.controller_after_job');

            return response()->json(['during' => DiagnosticsContextJob::$during, 'after' => Log::sharedContext()]);
        });

        foreach (['success', 'fail'] as $outcome) {
            $requestId = 'req_sync_'.$outcome;
            $response = $this->withHeader('X-Request-ID', $requestId)
                ->getJson('/api/v1/_diagnostics-test/sync-context/'.$outcome)->assertOk();
            $this->assertSame($requestId, $response->json('during.request_id'));
            $this->assertSame('controller', $response->json('during.request_scope'));
            $this->assertSame(1, $response->json('during.attempt'));
            $this->assertSame($requestId, $response->json('after.request_id'));
            $this->assertSame('controller', $response->json('after.request_scope'));
            $this->assertArrayNotHasKey('attempt', $response->json('after'));
            $this->assertArrayNotHasKey('job_id', $response->json('after'));
            $this->assertSame($requestId, $messages['diagnostics.inline_job']['request_id']);
            $this->assertSame(1, $messages['diagnostics.inline_job']['attempt']);
            $this->assertSame($requestId, $messages['diagnostics.controller_after_job']['request_id']);
            $this->assertArrayNotHasKey('attempt', $messages['diagnostics.controller_after_job']);
        }
    }

    public function test_worker_jobs_clear_prior_context_after_success_and_exception(): void
    {
        $messages = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$messages): void {
            if (in_array($event->message, ['diagnostics.worker_a', 'diagnostics.worker_b'], true)) {
                $messages[$event->message] = $event->context;
            }
        });
        foreach (['processed', 'exception'] as $outcome) {
            $first = Mockery::mock(Job::class);
            $first->shouldReceive('payload')->andReturn(['cvortex' => ['request_id' => 'req_worker_a']]);
            $first->shouldReceive('getJobId')->andReturn('job_a');
            $first->shouldReceive('attempts')->andReturn(1);
            Log::shareContext(['request_id' => 'stale_request', 'job_id' => 'stale_job']);
            Event::dispatch(new JobProcessing('redis', $first));
            $this->assertSame('req_worker_a', Log::sharedContext()['request_id']);
            $this->assertSame('job_a', Log::sharedContext()['job_id']);
            Log::shareContext(['llm_run_id' => 'run_a']);
            Log::info('diagnostics.worker_a');
            Event::dispatch($outcome === 'processed'
                ? new JobProcessed('redis', $first)
                : new JobExceptionOccurred('redis', $first, new \RuntimeException('retryable')));
            $this->assertSame([], Log::sharedContext());

            $second = Mockery::mock(Job::class);
            $second->shouldReceive('payload')->andReturn(['cvortex' => ['request_id' => 'req_worker_b']]);
            $second->shouldReceive('getJobId')->andReturn('job_b');
            $second->shouldReceive('attempts')->andReturn(1);
            Event::dispatch(new JobProcessing('redis', $second));
            $this->assertSame('req_worker_b', Log::sharedContext()['request_id']);
            $this->assertSame('job_b', Log::sharedContext()['job_id']);
            $this->assertArrayNotHasKey('llm_run_id', Log::sharedContext());
            Log::info('diagnostics.worker_b');
            $this->assertSame('req_worker_b', $messages['diagnostics.worker_b']['request_id']);
            $this->assertSame('job_b', $messages['diagnostics.worker_b']['job_id']);
            $this->assertArrayNotHasKey('llm_run_id', $messages['diagnostics.worker_b']);
            Event::dispatch(new JobProcessed('redis', $second));
            $this->assertSame([], Log::sharedContext());
        }
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

final class DiagnosticsContextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public static array $during = [];

    public function __construct(private readonly bool $fail) {}

    public function handle(): void
    {
        self::$during = Log::sharedContext();
        Log::info('diagnostics.inline_job');
        if ($this->fail) {
            throw new \RuntimeException('sync context test failure');
        }
    }
}
