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
    /** @return array{code:string,message:string,status:int,retryable:bool,severity:string,recovery_action:string,impact:string} */
    public static function classify(Throwable $exception): array
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
            $details = self::incidentDetails($code);

            return self::entry($code, $details['message'], 503, $details['retryable'], 'ERROR');
        }
        if ($exception instanceof SafeCareerException || $exception instanceof SafeVacancyException) {
            return self::entry($exception instanceof SafeCareerException ? 'CAREER_OPERATION_FAILED' : 'VACANCY_OPERATION_FAILED',
                'The private operation could not be completed. Please review the incident and retry after diagnosis.', 500, false, 'ERROR');
        }
        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            if ($status < 500) {
                return self::entry($status === 403 ? 'PERMISSION_DENIED' : ($status === 404 ? 'RESOURCE_NOT_FOUND' : 'REQUEST_REJECTED'),
                    $status === 403 ? 'You do not have access to this action.' : 'The request could not be completed.', $status, false, 'INFO');
            }

            return self::entry('INTERNAL_ERROR', 'The operation could not be completed. Please review the incident and retry after diagnosis.', $status, false, 'ERROR');
        }

        return self::entry('INTERNAL_ERROR', 'The operation could not be completed. Please review the incident and retry after diagnosis.', 500, false, 'ERROR');
    }

    public static function providerFailureCode(LlmProviderException $exception): string
    {
        return match ($exception->category) {
            LlmProviderException::TRANSPORT, LlmProviderException::TEMPORARY_UNAVAILABLE => 'LLM_PROVIDER_UNAVAILABLE',
            LlmProviderException::RATE_LIMITED => 'LLM_PROVIDER_RATE_LIMITED',
            LlmProviderException::NOT_CONFIGURED, LlmProviderException::INVALID_CONFIGURATION => 'LLM_PROVIDER_CONFIGURATION',
            LlmProviderException::MALFORMED_OUTPUT => 'LLM_OUTPUT_INVALID',
            LlmProviderException::REFUSAL => 'LLM_REQUEST_REFUSED',
            LlmProviderException::INCOMPLETE => 'LLM_RESPONSE_INCOMPLETE',
            default => 'LLM_PROVIDER_FAILED',
        };
    }

    /** @return array<string, string> */
    public static function responseHeaders(Throwable $exception): array
    {
        if ($exception instanceof LlmProviderException
            && $exception->category === LlmProviderException::RATE_LIMITED
            && is_int($exception->retryAfterSeconds)
            && $exception->retryAfterSeconds >= 0
            && $exception->retryAfterSeconds <= 86400) {
            return ['Retry-After' => (string) $exception->retryAfterSeconds];
        }

        return [];
    }

    /** @return array{retryable:bool,message:string,impact:string,recovery_action:string} */
    public static function incidentDetails(string $code): array
    {
        return match ($code) {
            'LLM_PROVIDER_UNAVAILABLE' => [
                'retryable' => true,
                'message' => 'The analysis service is temporarily unavailable. Please retry later.',
                'impact' => 'The LLM-backed operation did not complete.',
                'recovery_action' => 'Retry after the provider recovers; check provider status if the failure continues.',
            ],
            'LLM_PROVIDER_RATE_LIMITED' => [
                'retryable' => true,
                'message' => 'The analysis provider is rate limited. Wait briefly, then retry.',
                'impact' => 'The LLM-backed operation did not complete.',
                'recovery_action' => 'Wait until provider capacity returns, then retry the operation.',
            ],
            'LLM_PROVIDER_CONFIGURATION' => [
                'retryable' => false,
                'message' => 'The analysis provider needs configuration. Contact your administrator.',
                'impact' => 'LLM-backed operations may remain unavailable until configuration is corrected.',
                'recovery_action' => 'Verify provider credentials, endpoint and model configuration.',
            ],
            'LLM_OUTPUT_INVALID' => [
                'retryable' => false,
                'message' => 'The generated content could not be validated. Review the source and try a corrected request.',
                'impact' => 'The generated output was rejected before it could be trusted.',
                'recovery_action' => 'Review the source and validation constraints before retrying.',
            ],
            'LLM_REQUEST_REFUSED' => [
                'retryable' => false,
                'message' => 'The provider refused this request. Review the request and contact your administrator if it continues.',
                'impact' => 'The LLM-backed operation did not complete.',
                'recovery_action' => 'Review the request and provider policy before retrying.',
            ],
            'LLM_RESPONSE_INCOMPLETE' => [
                'retryable' => false,
                'message' => 'The provider returned an incomplete result. Review the input size and operation limits.',
                'impact' => 'The incomplete result was not accepted.',
                'recovery_action' => 'Review input size and output limits before retrying.',
            ],
            'LLM_PROVIDER_FAILED' => [
                'retryable' => false,
                'message' => 'The analysis could not be completed. Contact your administrator.',
                'impact' => 'The LLM-backed operation did not complete.',
                'recovery_action' => 'Inspect the incident and provider status before retrying.',
            ],
            'RATE_LIMITED' => [
                'retryable' => true,
                'message' => 'Too many requests. Please wait and try again.',
                'impact' => 'The request was temporarily throttled.',
                'recovery_action' => 'Wait for Retry-After, then retry the request.',
            ],
            'FRONTEND_RUNTIME_ERROR' => [
                'retryable' => false,
                'message' => 'The browser reported a failure. Its root cause was not captured.',
                'impact' => 'The affected browser operation may not have completed.',
                'recovery_action' => 'Check the affected route and occurrence reference. Review browser logs if the failure repeats.',
            ],
            default => [
                'retryable' => false,
                'message' => 'The operation could not be completed. Please review the incident and retry after diagnosis.',
                'impact' => 'The affected operation did not complete.',
                'recovery_action' => 'Inspect the sanitized incident details and dependency health.',
            ],
        };
    }

    /** @return array{code:string,message:string,status:int,retryable:bool,severity:string,recovery_action:string,impact:string} */
    private static function entry(string $code, string $message, int $status, bool $retryable, string $severity): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'status' => $status,
            'retryable' => $retryable,
            'severity' => $severity,
            'recovery_action' => self::incidentDetails($code)['recovery_action'],
            'impact' => self::incidentDetails($code)['impact'],
        ];
    }
}
