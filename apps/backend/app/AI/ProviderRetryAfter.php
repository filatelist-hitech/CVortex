<?php

namespace App\AI;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class ProviderRetryAfter
{
    public const MAX_SECONDS = 86400;

    public static function parse(?string $value, ?int $now = null): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return min(self::MAX_SECONDS, (int) $value);
        }

        $date = self::httpDate($value);
        if ($date === null) {
            return null;
        }

        return min(self::MAX_SECONDS, max(0, $date->getTimestamp() - ($now ?? time())));
    }

    public static function boundedSeconds(?int $seconds): ?int
    {
        if ($seconds === null || $seconds < 0) {
            return null;
        }

        return min(self::MAX_SECONDS, $seconds);
    }

    private static function httpDate(string $value): ?DateTimeImmutable
    {
        $timezone = new DateTimeZone('UTC');
        foreach ([DateTimeInterface::RFC7231, 'l, d-M-y H:i:s \\G\\M\\T', 'D M j H:i:s Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value, $timezone);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                && $date->format($format) === $value) {
                return $date;
            }
        }

        return null;
    }
}
