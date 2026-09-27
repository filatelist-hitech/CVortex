<?php

namespace App\Diagnostics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use WeakMap;

final class IncidentRecorder
{
    /** @var WeakMap<Throwable, bool>|null */
    private static ?WeakMap $recordedExceptions = null;

    public static function wasRecorded(Throwable $exception): bool
    {
        return isset((self::$recordedExceptions ??= new WeakMap)[$exception]);
    }

    /** @param array<string, mixed> $context */
    public function record(string $code, string $safeMessage, string $component, string $severity = 'ERROR', ?Throwable $exception = null, array $context = []): void
    {
        try {
            $shared = Log::sharedContext();
        } catch (Throwable) {
            $shared = [];
        }
        $safeContext = Redactor::context([...($shared ?: []), ...$context]);
        $safeStack = $exception === null ? null : Redactor::stack($exception);
        $fingerprint = hash('sha256', implode('|', [$code, $component, $exception ? $exception::class : '', $safeContext['operation'] ?? '']));
        try {
            $logContext = [
                'event_name' => 'diagnostics.incident', 'error_code' => $code, 'component' => $component,
                'exception_class' => $exception ? $exception::class : null, ...$safeContext,
            ];
            if ($safeStack !== null) {
                $logContext['safe_stack'] = $safeStack;
            }
            Log::log(strtolower($severity), 'diagnostics.incident', $logContext);
        } catch (Throwable) {
            // Preserve the original operation when the raw log sink fails.
        }

        try {
            $stored = DB::transaction(function () use ($fingerprint, $code, $safeMessage, $component, $severity, $exception, $safeContext, $safeStack): bool {
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
                    return false;
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
                    'attempt' => $safeContext['attempt'] ?? null, 'safe_stack' => $safeStack,
                    'created_at' => $now,
                ]);
                // Preserve recent reference IDs while bounding a noisy fingerprint.
                if ($incident->occurrence_count >= 1000 && $incident->occurrence_count % 100 === 0) {
                    $stale = DB::table('diagnostic_occurrences')->where('incident_id', $incident->id)
                        ->orderByDesc('created_at')->orderByDesc('id')->skip(1000)->limit(100)->pluck('id');
                    DB::table('diagnostic_occurrences')->whereIn('id', $stale)->delete();
                }

                return true;
            });
            if ($stored && $exception !== null) {
                self::$recordedExceptions ??= new WeakMap;
                self::$recordedExceptions[$exception] = true;
            }
        } catch (Throwable) {
            // PostgreSQL can be the failed dependency. Raw stderr remains independent.
        }
    }
}
