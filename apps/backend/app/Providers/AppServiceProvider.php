<?php

namespace App\Providers;

use App\AI\Contracts\LlmProvider;
use App\AI\Providers\ConfiguredLlmProvider;
use App\Mcp\Http\AddMcpOAuthIssuer;
use App\Mcp\Http\RequireMcpOAuthResource;
use App\Mcp\OAuth\ResourceAccessToken;
use App\Services\EmailNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

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

        Route::pattern('id', '(?i:[0-9A-HJKMNP-TV-Z]{26})');
    }
}
