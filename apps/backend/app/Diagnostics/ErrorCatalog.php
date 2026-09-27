<?php

namespace App\Diagnostics;

use App\AI\Exceptions\LlmProviderException;
use App\Exceptions\SafeCareerException;
use App\Exceptions\SafeVacancyException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ErrorCatalog
{
    /** @return array{code:string,message:string,status:int,retryable:bool,severity:string} */
    public static function classify(Throwable $exception, string $path = ''): array
    {
        if ($exception instanceof ValidationException) {
            return self::entry('VALIDATION_FAILED', 'Please check the submitted information.', 422, false, 'INFO');
        }
        if ($exception instanceof AuthenticationException) {
            return self::entry('AUTH_REQUIRED', 'Please sign in and try again.', 401, false, 'INFO');
        }
        if ($exception instanceof AuthorizationException) {
            return self::entry('PERMISSION_DENIED', 'You do not have access to this action.', 403, false, 'INFO');
        }
        if ($exception instanceof ModelNotFoundException) {
            return self::entry('RESOURCE_NOT_FOUND', 'The request could not be completed.', 404, false, 'INFO');
        }
        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 429) {
            return self::entry('RATE_LIMITED', 'Too many requests. Please wait and try again.', 429, true, 'WARNING');
        }
        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
            $status = $exception->getStatusCode();

            return self::entry($status === 403 ? 'PERMISSION_DENIED' : ($status === 404 ? 'RESOURCE_NOT_FOUND' : 'REQUEST_REJECTED'),
                $status === 403 ? 'You do not have access to this action.' : 'The request could not be completed.', $status, false, 'INFO');
        }
        if ($exception instanceof LlmProviderException) {
            $code = self::providerFailureCode($exception);
            if ($code === 'LLM_OUTPUT_INVALID') {
                return self::entry($code, 'The generated content could not be validated. Contact your administrator.', 503, false, 'ERROR');
            }
            $message = match (true) {
                $exception->isRetryable() => 'The analysis service is temporarily unavailable. Please retry later.',
                $exception->requiresConfiguration() => 'The analysis provider needs configuration. Contact your administrator.',
                default => 'The analysis could not be completed. Contact your administrator.',
            };

            return self::entry($code, $message, 503, $exception->isRetryable(), 'ERROR');
        }
        if ($exception instanceof SafeCareerException || $exception instanceof SafeVacancyException) {
            return self::entry($exception instanceof SafeCareerException ? 'CAREER_OPERATION_FAILED' : 'VACANCY_OPERATION_FAILED',
                'The private operation could not be completed. Please try again.', 500, true, 'ERROR');
        }
        if (str_starts_with($path, 'career')) {
            return self::entry('CAREER_OPERATION_FAILED', 'The private operation could not be completed. Please try again.', 500, true, 'ERROR');
        }
        if (str_starts_with($path, 'vacancies')) {
            return self::entry('VACANCY_OPERATION_FAILED', 'The private operation could not be completed. Please try again.', 500, true, 'ERROR');
        }

        return self::entry('INTERNAL_ERROR', 'The operation could not be completed. Please try later.', 500, false, 'ERROR');
    }

    public static function providerFailureCode(LlmProviderException $exception): string
    {
        return $exception->category === LlmProviderException::MALFORMED_OUTPUT
            ? 'LLM_OUTPUT_INVALID'
            : 'LLM_PROVIDER_UNAVAILABLE';
    }

    /** @return array{code:string,message:string,status:int,retryable:bool,severity:string} */
    private static function entry(string $code, string $message, int $status, bool $retryable, string $severity): array
    {
        return compact('code', 'message', 'status', 'retryable', 'severity');
    }
}
