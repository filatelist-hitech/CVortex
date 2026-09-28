<?php

namespace App\Diagnostics;

use App\AI\Exceptions\LlmProviderException;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProviderRetryWarning
{
    public static function scheduled(string $operation, LlmProviderException $exception, int $attempt, int $delaySeconds, ?string $applicationId = null): void
    {
        try {
            $shared = Log::sharedContext();
        } catch (Throwable) {
            $shared = [];
        }

        $context = array_filter([
            'event_name' => 'llm.provider_retry_scheduled',
            'request_id' => self::identifier($shared['request_id'] ?? null, 128),
            'job_id' => self::identifier($shared['job_id'] ?? null, 128),
            'llm_run_id' => self::identifier($shared['llm_run_id'] ?? null, 26),
            'application_id' => self::identifier($applicationId, 26),
            'operation' => $operation,
            'provider' => self::label($exception->providerName),
            'attempt' => max(1, min(3, $attempt)),
            'retry_delay_seconds' => max(0, min(86400, $delaySeconds)),
        ], static fn (mixed $value): bool => $value !== null);

        try {
            Log::warning('diagnostics.provider_retry_scheduled', $context);
        } catch (Throwable) {
            // A missing warning sink must not turn a scheduled retry into a final failure.
        }
    }

    private static function identifier(mixed $value, int $maxLength): ?string
    {
        return is_string($value) && $value !== '' && strlen($value) <= $maxLength ? $value : null;
    }

    private static function label(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $label = substr(preg_replace('/[^a-zA-Z0-9_.-]/', '', $value) ?? '', 0, 64);

        return $label === '' ? null : $label;
    }
}
