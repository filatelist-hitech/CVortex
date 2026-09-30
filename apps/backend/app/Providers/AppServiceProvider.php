<?php

namespace App\Providers;

use App\AI\Contracts\LlmProvider;
use App\AI\Exceptions\LlmProviderException;
use App\AI\Providers\ConfiguredLlmProvider;
use App\Diagnostics\ErrorCatalog;
use App\Diagnostics\IncidentRecorder;
use App\Jobs\AnalyzeVacancy;
use App\Jobs\ExtractCareerSource;
use App\Logging\SanitizingLogManager;
use App\Mcp\Http\AddMcpOAuthIssuer;
use App\Mcp\Http\RequireMcpOAuthResource;
use App\Mcp\OAuth\ResourceAccessToken;
use App\Queue\QueueExecutionContext;
use App\Services\EmailNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('log', fn ($app) => new SanitizingLogManager($app));
        $this->app->singleton(QueueExecutionContext::class);

        if (! config('mcp.enabled')) {
            Passport::ignoreRoutes();
        } else {
            Passport::authorizationView('mcp.authorize');
            Passport::useAccessTokenEntity(ResourceAccessToken::class);
        }

        $this->app->bind(
            LlmProvider::class,
            ConfiguredLlmProvider::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $syncContexts = new \WeakMap;
        $restoreContext = static function (JobProcessed|JobExceptionOccurred $event) use ($syncContexts): void {
            try {
                $previous = $syncContexts[$event->job] ?? null;
                unset($syncContexts[$event->job]);
                Log::withoutContext();
                Log::flushSharedContext();
                if ($previous !== null) {
                    Log::shareContext($previous);
                }
            } catch (Throwable) {
                // Queue processing must continue when log context cleanup fails.
            }
        };

        Queue::createPayloadUsing(function (): array {
            if (! app()->bound('request')) {
                return [];
            }
            $request = request();

            return ['cvortex' => [
                'request_id' => $request->attributes->get('request_id'),
                'user_id' => $request->user()?->id,
            ]];
        });
        Queue::before(function (JobProcessing $event) use ($syncContexts): void {
            app(QueueExecutionContext::class)->begin();
            try {
                $payload = $event->job->payload()['cvortex'] ?? [];
                $previous = $event->connectionName === 'sync' ? Log::sharedContext() : [];
                if ($event->connectionName === 'sync') {
                    $syncContexts[$event->job] = $previous;
                }
                Log::withoutContext();
                Log::flushSharedContext();
                Log::shareContext(array_merge($previous, array_filter([
                    'request_id' => $payload['request_id'] ?? null,
                    'job_id' => $event->job->getJobId(),
                    'user_id' => $payload['user_id'] ?? null,
                    'attempt' => $event->job->attempts(),
                ])));
            } catch (Throwable) {
                // Queue processing must continue when log context setup fails.
            }
        });
        $finishQueueContext = static function (JobProcessed|JobExceptionOccurred $event) use ($restoreContext): void {
            app(QueueExecutionContext::class)->finish();
            $restoreContext($event);
        };
        Queue::after($finishQueueContext);
        Queue::exceptionOccurred($finishQueueContext);
        Queue::failing(function (JobFailed $event): void {
            try {
                $payload = $event->job->payload()['cvortex'] ?? [];
                try {
                    $shared = Log::sharedContext();
                } catch (Throwable) {
                    $shared = [];
                }
                $jobClass = $event->job->resolveName();
                $providerException = $event->exception instanceof LlmProviderException ? $event->exception : null;
                $providerFailure = $providerException !== null
                    && in_array($jobClass, [AnalyzeVacancy::class, ExtractCareerSource::class], true);
                $providerCode = $providerFailure ? ErrorCatalog::providerFailureCode($providerException) : null;
                app(IncidentRecorder::class)->record($providerCode ?? 'QUEUE_JOB_FAILED',
                    $providerCode === null ? 'A background operation failed.' : ErrorCatalog::incidentDetails($providerCode)['message'],
                    $providerFailure ? ($jobClass === AnalyzeVacancy::class ? 'vacancy' : 'career') : 'queue',
                    'ERROR', $event->exception, [
                        'request_id' => $payload['request_id'] ?? null, 'user_id' => $payload['user_id'] ?? null,
                        'job_id' => $event->job->getJobId(), 'llm_run_id' => $shared['llm_run_id'] ?? null,
                        'operation' => $jobClass,
                        'queue' => $event->job->getQueue(), 'connection' => $event->connectionName,
                        'provider' => $providerException?->providerName,
                        'attempt' => $event->job->attempts(),
                    ]);
            } catch (Throwable) {
                // Never let the failed-job observer replace the original queue exception.
            }
        });
        $this->app->booted(function (): void {
            if (! config('mcp.enabled')) {
                return;
            }

            foreach (['passport.authorizations.authorize', 'passport.authorizations.approve', 'passport.authorizations.deny'] as $routeName) {
                $route = Route::getRoutes()->getByName($routeName);
                if ($route !== null) {
                    $route->middleware(AddMcpOAuthIssuer::class);
                }
            }

            $tokenRoute = Route::getRoutes()->getByName('passport.token');
            if ($tokenRoute !== null) {
                $tokenRoute->middleware(RequireMcpOAuthResource::class);
            }

            $authorizeRoute = Route::getRoutes()->getByName('passport.authorizations.authorize');
            if ($authorizeRoute !== null) {
                $authorizeRoute->middleware(RequireMcpOAuthResource::class);
            }
        });

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perSecond(
                (int) config('auth.login_rate_limit.attempts'),
                (int) config('auth.login_rate_limit.decay_seconds'),
            )->by('login:'.$request->ip().'|'.app(EmailNormalizer::class)->normalize((string) $request->input('email')));
        });
        RateLimiter::for('diagnostics-report', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->user()?->id));

        Route::pattern('id', '(?i:[0-9A-HJKMNP-TV-Z]{26})');
    }
}
