<?php

namespace App\Diagnostics;

use Throwable;

final class Redactor
{
    public static function text(string $value): string
    {
        $value = preg_replace('/\bBearer\s+\S+/i', 'Bearer [REDACTED]', $value) ?? '[REDACTED]';
        $value = preg_replace('/"(password(?:_confirmation)?|access_token|refresh_token|token|authorization|cookie|set-cookie|api_?key|client_secret|secret)"\s*:\s*"[^"]*"/i', '"$1":"[REDACTED]"', $value) ?? '[REDACTED]';
        $value = preg_replace('/\b(password(?:_confirmation)?|access_token|refresh_token|token|authorization|cookie|set-cookie|api_?key|client_secret|secret)\s*[:=]\s*[^\s,;&]+/i', '$1=[REDACTED]', $value) ?? '[REDACTED]';

        return mb_substr($value, 0, 500);
    }

    public static function context(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 5) {
            return '[TRUNCATED]';
        }
        if ($value instanceof Throwable) {
            return $value::class;
        }
        if (is_array($value)) {
            $safe = [];
            foreach (array_slice($value, 0, 40, true) as $key => $item) {
                $safe[$key] = preg_match('/password|token|authorization|cookie|secret|api.?key|session|prompt|response|source_text|raw_text|resume|email|phone/i', (string) $key)
                    ? '[REDACTED]' : self::context($item, $depth + 1);
            }

            return $safe;
        }

        return is_string($value) ? self::text($value) : (is_scalar($value) || $value === null ? $value : '[OMITTED]');
    }

    public static function stack(Throwable $exception): string
    {
        $frames = array_slice($exception->getTrace(), 0, 12);

        return implode("\n", array_map(static fn (array $frame): string => basename((string) ($frame['file'] ?? 'runtime')).':'.(int) ($frame['line'] ?? 0).' '.
            self::text((string) ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function']), $frames));
    }
}
