<?php

namespace App\Queue;

use App\AI\Exceptions\LlmProviderException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final class PendingJobRecovery
{
    public static function recoveryWindowSeconds(): int
    {
        $connection = (string) config('queue.default', 'redis');
        $retryAfter = (int) config('queue.connections.'.$connection.'.retry_after', 90);
        $workerTimeout = (int) config('horizon.defaults.supervisor-1.timeout', config('horizon.defaults.timeout', 60));

        return max(180, $retryAfter * 2, $workerTimeout * 2);
    }

    public static function runIsStale(?\DateTimeInterface $updatedAt): bool
    {
        return $updatedAt !== null
            && $updatedAt <= now()->subSeconds(self::recoveryWindowSeconds());
    }

    public static function recoveryIsDue(Model $operation, ?\DateTimeInterface $legacyDispatchAt = null): bool
    {
        $recoveryAt = $operation->getAttribute('dispatch_recovery_at');
        if ($recoveryAt === null) {
            $legacyDispatchAt ??= $operation->getAttribute('created_at') ?? $operation->getAttribute('updated_at');
            if ($legacyDispatchAt === null) {
                return true;
            }
            // Before persisted deadlines existed, the jobs used a long unique
            // lock to protect provider delays. Keep that legacy window intact.
            $recoveryAt = Carbon::instance($legacyDispatchAt)->addSeconds(LlmProviderException::UNIQUE_LOCK_SECONDS);
        }

        return $recoveryAt <= now();
    }

    public static function reserve(Model $operation, int $delaySeconds = 0): void
    {
        $nextAttemptAt = now()->addSeconds(max(0, $delaySeconds));
        $operation->forceFill([
            'next_attempt_at' => $nextAttemptAt,
            'dispatch_recovery_at' => $nextAttemptAt->copy()->addSeconds(self::recoveryWindowSeconds()),
        ])->save();
    }
}
