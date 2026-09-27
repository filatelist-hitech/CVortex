<?php

namespace App\Diagnostics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class IncidentRecorder
{
    /** @param array<string, mixed> $context */
    public function record(string $code, string $safeMessage, string $component, string $severity = 'ERROR', ?Throwable $exception = null, array $context = []): void
    {
        try {
            $shared = Log::sharedContext();
        } catch (Throwable) {
            $shared = [];
        }
        $safeContext = Redactor::context([...($shared ?: []), ...$context]);
        $fingerprint = hash('sha256', implode('|', [$code, $component, $exception ? $exception::class : '', $safeContext['operation'] ?? '']));
        try {
            Log::log(strtolower($severity), 'diagnostics.incident', [
                'event_name' => 'diagnostics.incident', 'error_code' => $code, 'component' => $component,
                'exception_class' => $exception ? $exception::class : null, ...$safeContext,
            ]);
        } catch (Throwable) {
            // Preserve the original operation when the raw log sink fails.
        }

        try {
            DB::transaction(function () use ($fingerprint, $code, $safeMessage, $component, $severity, $exception, $safeContext): void {
                $now = now();
                DB::table('diagnostic_incidents')->insertOrIgnore([
                    'id' => (string) Str::ulid(), 'fingerprint' => $fingerprint, 'status' => 'OPEN',
                    'severity' => $severity, 'error_code' => $code, 'service' => $safeContext['service'] ?? 'backend',
                    'component' => $component, 'environment' => app()->environment(),
                    'message' => Redactor::text($safeMessage), 'exception_class' => $exception ? $exception::class : null,
                    'occurrence_count' => 0, 'first_seen_at' => $now, 'last_seen_at' => $now,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $incident = DB::table('diagnostic_incidents')->where('fingerprint', $fingerprint)->lockForUpdate()->first();
                if ($incident === null) {
                    return;
                }
                DB::table('diagnostic_incidents')->where('id', $incident->id)->update([
                    'occurrence_count' => DB::raw('occurrence_count + 1'), 'last_seen_at' => $now, 'updated_at' => $now,
                    'status' => $incident->status === 'RESOLVED' ? 'OPEN' : $incident->status,
                ]);
                DB::table('diagnostic_occurrences')->insert([
                    'id' => (string) Str::ulid(), 'incident_id' => $incident->id,
                    'request_id' => $safeContext['request_id'] ?? null, 'job_id' => $safeContext['job_id'] ?? null,
                    'llm_run_id' => $safeContext['llm_run_id'] ?? null, 'application_id' => $safeContext['application_id'] ?? null,
                    'user_id' => $safeContext['user_id'] ?? null, 'route' => $safeContext['route'] ?? null,
                    'operation' => $safeContext['operation'] ?? null, 'provider' => $safeContext['provider'] ?? null,
                    'attempt' => $safeContext['attempt'] ?? null, 'safe_stack' => self::safeStack($exception),
                    'created_at' => $now,
                ]);
                // Preserve recent reference IDs while bounding a noisy fingerprint.
                if ($incident->occurrence_count >= 1000 && $incident->occurrence_count % 100 === 0) {
                    $stale = DB::table('diagnostic_occurrences')->where('incident_id', $incident->id)
                        ->orderByDesc('created_at')->orderByDesc('id')->skip(1000)->limit(100)->pluck('id');
                    DB::table('diagnostic_occurrences')->whereIn('id', $stale)->delete();
                }
            });
        } catch (Throwable) {
            // PostgreSQL can be the failed dependency. Raw stderr remains independent.
        }
    }

    private static function safeStack(?Throwable $exception): ?string
    {
        if ($exception === null) {
            return null;
        }
        $frames = array_slice($exception->getTrace(), 0, 12);

        return implode("\n", array_map(static fn (array $frame): string => basename((string) ($frame['file'] ?? 'runtime')).':'.(int) ($frame['line'] ?? 0).' '.
            Redactor::text((string) ($frame['class'] ?? '').$frame['function']), $frames));
    }
}
