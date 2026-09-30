<?php

namespace App\Diagnostics;

use Throwable;

final class Redactor
{
    public static function text(string $value): string
    {
        $trimmed = trim($value);
        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            try {
                $decoded = json_decode($trimmed, true, 8, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $encoded = json_encode(self::context($decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    $value = $encoded;
                }
            } catch (\JsonException) {
                // Freeform text still passes through the token and key-value redactors below.
            }
        }
        $value = preg_replace('/\bBearer\s+[^\s]+/i', 'Bearer [REDACTED]', $value) ?? '[REDACTED]';
        $value = preg_replace('/\b(?:Cookie|Set-Cookie)\s*:\s*[^\r\n]*/i', 'Cookie: [REDACTED]', $value) ?? '[REDACTED]';
        $value = preg_replace('/\b(authorization|cookie|set-cookie)\s*[:=]\s*[^\r\n]*/i', '$1=[REDACTED]', $value) ?? '[REDACTED]';
        $sensitive = 'password(?:[_ -]confirmation)?|access_token|refresh_token|token|authorization|cookie|set-cookie|api[-_ ]?key|client[-_ ]?secret|secret|credential|prompt|source_text|raw_text|resume|candidate_data|recruiter_message|email|phone';
        $value = preg_replace_callback('/([?&])([^=&#]+)=([^&#]*)/', static function (array $match) use ($sensitive): string {
            $key = $match[2];
            for ($decode = 0; $decode < 3; $decode++) {
                $key = urldecode($key);
                if (preg_match('/'.$sensitive.'/i', $key) === 1) {
                    return $match[1].$match[2].'=[REDACTED]';
                }
            }

            return $match[0];
        }, $value) ?? '[REDACTED]';
        $value = preg_replace('/"('.$sensitive.')"\s*:\s*"(?:\\\\.|[^"\\\\])*"/i', '"$1":"[REDACTED]"', $value) ?? '[REDACTED]';
        $value = preg_replace('/(?<![?&])\b('.$sensitive.')\s*[:=]\s*(?:"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|[^\s\r\n]*)/i', '$1=[REDACTED]', $value) ?? '[REDACTED]';

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
                if (preg_match('/password|token|authorization|cookie|secret|credential|api.?key|session|prompt|response|source_text|raw_text|resume|\bcv\b|candidate|recruiter|email|phone|request_body|response_body/i', (string) $key)) {
                    $safe[$key] = '[REDACTED]';
                } elseif (strtolower((string) $key) === 'safe_stack' && is_string($item)) {
                    $safe[$key] = implode("\n", array_map(static fn (string $line): string => mb_substr($line, 0, 500), array_slice(explode("\n", $item), 0, 12)));
                } else {
                    $safe[$key] = self::context($item, $depth + 1);
                }
            }

            return $safe;
        }

        return is_string($value) ? self::text($value) : (is_scalar($value) || $value === null ? $value : '[OMITTED]');
    }

    public static function stack(Throwable $exception): string
    {
        $origin = basename($exception->getFile() ?: 'runtime').':'.$exception->getLine().' '.$exception::class.' (throw site)';
        $applicationFrames = [];
        $frameworkFrames = [];
        foreach (array_slice($exception->getTrace(), 0, 50) as $frame) {
            $file = str_replace('\\', '/', (string) ($frame['file'] ?? ''));
            $class = (string) ($frame['class'] ?? '');
            if (str_contains($class, "\0")) {
                $class = explode("\0", $class, 2)[0];
            }
            $rendered = basename($file !== '' ? $file : 'runtime').':'.(int) ($frame['line'] ?? 0).' '.
                self::frameFunction($class.($frame['type'] ?? '').$frame['function']);
            if (str_contains($file, '/app/') || str_contains($file, '/routes/')) {
                $applicationFrames[] = '[app] '.$rendered;
            } else {
                $frameworkFrames[] = '[framework] '.$rendered;
            }
        }
        $frames = [...array_slice($applicationFrames, 0, 11)];
        if (count($frames) < 11) {
            $frames = [...$frames, ...array_slice($frameworkFrames, 0, 11 - count($frames))];
        }

        return implode("\n", [$origin, ...$frames]);
    }

    public static function frameFunction(string $value): string
    {
        $value = preg_replace_callback(
            '~(?<![A-Za-z0-9])(?<path>(?:[A-Za-z]:[\\\\/]|/)(?:[^\\\\/:{}()]+[\\\\/])*[^\\\\/:{}()]+)(?<line>:\\d+)?~',
            static fn (array $match): string => basename(str_replace(chr(92), '/', $match['path'])).($match['line'] ?? ''),
            $value,
        ) ?? '[REDACTED]';

        return self::text($value);
    }
}
