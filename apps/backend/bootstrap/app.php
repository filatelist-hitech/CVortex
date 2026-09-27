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
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
            try {
                if (IncidentRecorder::wasRecorded($exception)) {
                    return false;
                }

                if (! app()->bound('request') || ! app()->resolved('request')) {
                    return true;
                }
                $request = app('request');
                if (! $request instanceof Request || ! $request->attributes->has('request_id')) {
                    return true;
                }

                if ($request->is('mcp/v1') && $exception instanceof OAuthServerException) {
                    Log::notice('mcp.oauth_authentication_rejected', [
                        'request_id' => $request->attributes->get('request_id'),
                        'exception_type' => $exception::class,
                    ]);

                    return false;
                }

                if ($request->is('api/v1/*')) {
                    $entry = ErrorCatalog::classify($exception, (string) $request->segment(3));
                    if ($entry['status'] < 500) {
                        return true;
                    }
                    $recorded = app(IncidentRecorder::class)->record($entry['code'], $entry['message'],
                        (string) ($request->segment(3) ?? 'api'), $entry['severity'], $exception, [
                            'request_id' => $request->attributes->get('request_id'),
                            'user_id' => $request->user()?->id,
                            'route' => $request->route()?->getName(),
                            'operation' => $request->route()?->getName(),
                        ]);

                    return ! $recorded;
                }
            } catch (Throwable) {
                // Reporting is best-effort; let Laravel report the original exception if it fails.
                return true;
            }

            return true;
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->is('api/v1/*')) {
                $entry = ErrorCatalog::classify($exception, (string) $request->segment(3));
                $headers = [];
                if ($exception instanceof HttpExceptionInterface) {
                    $safeNames = [
                        'allow', 'retry-after', 'x-ratelimit-limit', 'x-ratelimit-remaining', 'x-ratelimit-reset',
                        'ratelimit-limit', 'ratelimit-remaining', 'ratelimit-reset', 'ratelimit-policy',
                    ];
                    foreach ($exception->getHeaders() as $name => $value) {
                        $name = strtolower($name);
                        if (! in_array($name, $safeNames, true) || (! is_string($value) && ! is_int($value))) {
                            continue;
                        }
                        $value = (string) $value;
                        if (strlen($value) > 128) {
                            continue;
                        }
                        $valid = match ($name) {
                            'allow' => preg_match('/\A[A-Za-z][A-Za-z0-9-]*(?:\s*,\s*[A-Za-z][A-Za-z0-9-]*)*\z/D', $value) === 1,
                            'retry-after' => ctype_digit($value)
                                || preg_match('/\A(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun), \d{2} (?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d{4} \d{2}:\d{2}:\d{2} GMT\z/D', $value) === 1,
                            'ratelimit-limit', 'ratelimit-policy' => preg_match('/\A\d+(?:;w=\d+)?(?:,\s*\d+(?:;w=\d+)?)*\z/D', $value) === 1,
                            default => preg_match('/\A\d+(?:,\s*\d+)*\z/D', $value) === 1,
                        };
                        if ($valid) {
                            $headers[$name] = $value;
                        }
                    }
                }

                return response()->json([
                    'message' => $entry['message'],
                    'error' => [
                        'code' => $entry['code'], 'message' => $entry['message'],
                        'request_id' => $request->attributes->get('request_id'),
                        'retryable' => $entry['retryable'],
                    ],
                    ...($exception instanceof ValidationException ? ['errors' => $exception->errors()] : []),
                ], $entry['status'], $headers);
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
