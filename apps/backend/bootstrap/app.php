<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureRequestId;
use App\Http\Middleware\SetDatabaseOwnerContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
            if ((request()->is('api/v1/career*') || request()->is('api/v1/vacancies*'))
                && ! $exception instanceof ValidationException
                && ! $exception instanceof AuthenticationException
                && (! $exception instanceof HttpExceptionInterface || $exception->getStatusCode() >= 500)) {
                Log::error(request()->is('api/v1/vacancies*') ? 'vacancy.operation_failed' : 'career.operation_failed', [
                    'operation' => request()->route()?->getName() ?? 'private-domain',
                    'exception_type' => $exception::class,
                ]);

                return false;
            }

            return true;
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (($request->is('api/v1/career*') || $request->is('api/v1/vacancies*'))
                && ! $exception instanceof ValidationException
                && ! $exception instanceof AuthenticationException
                && (! $exception instanceof HttpExceptionInterface || $exception->getStatusCode() >= 500)) {
                return response()->json([
                    'message' => 'The private operation could not be completed. Please try again.',
                    'error' => ['code' => $request->is('api/v1/vacancies*') ? 'VACANCY_OPERATION_FAILED' : 'CAREER_OPERATION_FAILED'],
                ], 500);
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
