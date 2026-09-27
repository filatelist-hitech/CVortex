<?php

use App\Diagnostics\ErrorCatalog;
use App\Diagnostics\IncidentRecorder;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureRequestId;
use App\Http\Middleware\SetDatabaseOwnerContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use League\OAuth2\Server\Exception\OAuthServerException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(EnsureRequestId::class);
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->alias([
            'active-user' => EnsureActiveUser::class,
            'db-owner-context' => SetDatabaseOwnerContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (Throwable $exception): bool {
            if (request()->is('mcp/v1') && $exception instanceof OAuthServerException) {
                Log::notice('mcp.oauth_authentication_rejected', [
                    'request_id' => request()->attributes->get('request_id'),
                    'exception_type' => $exception::class,
                ]);

                return false;
            }

            if (request()->is('api/v1/*')) {
                $entry = ErrorCatalog::classify($exception, (string) request()->segment(3));
                if ($entry['status'] < 500) {
                    return true;
                }
                app(IncidentRecorder::class)->record($entry['code'], $entry['message'],
                    (string) (request()->segment(3) ?? 'api'), $entry['severity'], $exception, [
                        'request_id' => request()->attributes->get('request_id'),
                        'user_id' => request()->user()?->id,
                        'route' => request()->route()?->getName(),
                        'operation' => request()->route()?->getName(),
                    ]);

                return false;
            }

            return true;
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->is('api/v1/*')) {
                $entry = ErrorCatalog::classify($exception, (string) $request->segment(3));

                return response()->json([
                    'message' => $entry['message'],
                    'error' => [
                        'code' => $entry['code'], 'message' => $entry['message'],
                        'request_id' => $request->attributes->get('request_id'),
                        'retryable' => $entry['retryable'],
                    ],
                    ...($exception instanceof ValidationException ? ['errors' => $exception->errors()] : []),
                ], $entry['status']);
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
