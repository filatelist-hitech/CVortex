<?php

namespace App\Providers;

use App\AI\Contracts\LlmProvider;
use App\AI\Exceptions\LlmProviderException;
use App\AI\Providers\ConfiguredLlmProvider;
use App\Diagnostics\IncidentRecorder;
use App\Jobs\AnalyzeVacancy;
use App\Jobs\ExtractCareerSource;
use App\Mcp\Http\AddMcpOAuthIssuer;
use App\Mcp\Http\RequireMcpOAuthResource;
use App\Mcp\OAuth\ResourceAccessToken;
use App\Services\EmailNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        Queue::before(function (JobProcessing $event): void {
            $payload = $event->job->payload()['cvortex'] ?? [];
            Log::flushSharedContext();
            Log::shareContext(array_filter([
                'request_id' => $payload['request_id'] ?? null,
                'job_id' => $event->job->getJobId(),
                'user_id' => $payload['user_id'] ?? null,
                'attempt' => $event->job->attempts(),
            ]));
        });
        Queue::after(fn (JobProcessed $event) => Log::flushSharedContext());
        Queue::failing(function (JobFailed $event): void {
            $payload = $event->job->payload()['cvortex'] ?? [];
            try {
                $shared = Log::sharedContext();
            } catch (Throwable) {
                $shared = [];
            }
            $jobClass = $event->job->resolveName();
            $providerFailure = $event->exception instanceof LlmProviderException
                && in_array($jobClass, [AnalyzeVacancy::class, ExtractCareerSource::class], true);
            app(IncidentRecorder::class)->record($providerFailure ? 'LLM_PROVIDER_UNAVAILABLE' : 'QUEUE_JOB_FAILED',
                $providerFailure ? 'The analysis provider failed.' : 'A background operation failed.',
                $providerFailure ? ($jobClass === AnalyzeVacancy::class ? 'vacancy' : 'career') : 'queue',
                'ERROR', $event->exception, [
                    'request_id' => $payload['request_id'] ?? null, 'user_id' => $payload['user_id'] ?? null,
                    'job_id' => $event->job->getJobId(), 'llm_run_id' => $shared['llm_run_id'] ?? null,
                    'operation' => $jobClass,
                    'provider' => $providerFailure ? $event->exception->providerName : null,
                    'attempt' => $event->job->attempts(),
                ]);
            Log::flushSharedContext();
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
