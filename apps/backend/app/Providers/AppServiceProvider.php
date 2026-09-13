<?php

namespace App\Providers;

use App\AI\Contracts\LlmProvider;
use App\AI\Providers\OpenAiResponsesProvider;
use App\AI\Providers\UnconfiguredLlmProvider;
use App\Services\EmailNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            LlmProvider::class,
            config('ai.provider') === 'openai' ? OpenAiResponsesProvider::class : UnconfiguredLlmProvider::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perSecond(
                (int) config('auth.login_rate_limit.attempts'),
                (int) config('auth.login_rate_limit.decay_seconds'),
            )->by('login:'.$request->ip().'|'.app(EmailNormalizer::class)->normalize((string) $request->input('email')));
        });

        Route::pattern('id', '[0-9A-HJKMNP-TV-Z]{26}');
    }
}
