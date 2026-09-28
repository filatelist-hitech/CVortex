<?php

namespace App\Services;

use App\Diagnostics\IncidentRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class DependencyProbe
{
    public function ready(): bool
    {
        try {
            DB::select('select 1');
        } catch (Throwable $exception) {
            $this->recordFailure('postgresql', 'readiness_database', $exception);
            try {
                DB::purge();
            } catch (Throwable) {
                // A failed connection cleanup must not replace the readiness result.
            }

            return false;
        }

        try {
            Redis::connection()->ping();

            return true;
        } catch (Throwable $exception) {
            $this->recordFailure('redis', 'readiness_redis', $exception);
            try {
                app()->forgetInstance('redis');
            } catch (Throwable) {
                // A failed connection cleanup must not replace the readiness result.
            }

            return false;
        }
    }

    private function recordFailure(string $dependency, string $operation, Throwable $exception): void
    {
        try {
            app(IncidentRecorder::class)->record('DEPENDENCY_UNAVAILABLE', 'A required readiness dependency is unavailable.', $dependency, 'ERROR', $exception, [
                'operation' => $operation,
            ]);
        } catch (Throwable) {
            // Diagnostics are best-effort even when both PostgreSQL and the log sink fail.
        }
    }
}
