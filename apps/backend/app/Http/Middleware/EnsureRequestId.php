<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRequestId
{
    private const REQUEST_ID_PATTERN = '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header('X-Request-ID');
        $requestId = is_string($incoming) && preg_match(self::REQUEST_ID_PATTERN, $incoming) === 1
            ? $incoming
            : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        Log::shareContext(['request_id' => $requestId]);

        $response = $next($request);
        if ($response instanceof JsonResponse && $response->getStatusCode() >= 400
            && $request->is('api/v1/*') && ! $request->is('api/v1/health/*')) {
            $body = $response->getData(true);
            if (is_array($body)) {
                $error = is_array($body['error'] ?? null) ? $body['error'] : [];
                $error['code'] ??= match ($response->getStatusCode()) {
                    401 => 'AUTH_REQUIRED', 403 => 'PERMISSION_DENIED', 404 => 'RESOURCE_NOT_FOUND',
                    422 => 'VALIDATION_FAILED', 429 => 'RATE_LIMITED', default => 'REQUEST_FAILED',
                };
                $error['message'] ??= 'The request could not be completed.';
                $error['request_id'] = $requestId;
                $error['retryable'] ??= $error['code'] === 'RATE_LIMITED';
                $body['error'] = $error;
                $response->setData($body);
            }
        }
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
