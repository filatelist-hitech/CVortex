<?php

use App\Diagnostics\ConsoleFailureReporter;
use App\Jobs\DiagnosticFailureProbe;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('diagnostics:prune', function (): int {
    try {
        DB::table('diagnostic_occurrences')->where('created_at', '<', now()->subDays(config('diagnostics.occurrence_days')))->delete();
        DB::table('diagnostic_incidents')->whereIn('status', ['RESOLVED', 'IGNORED'])
            ->where('last_seen_at', '<', now()->subDays(config('diagnostics.closed_incident_days')))->delete();
    } catch (Throwable $exception) {
        $reference = app(ConsoleFailureReporter::class)->report($exception, 'diagnostics:prune');
        $this->error('Pruning failed. Reference: '.$reference);

        return 1;
    }
    $this->info('Expired diagnostic occurrences and closed incidents pruned.');

    return 0;
})->purpose('Apply diagnostic retention without deleting active incidents');

Artisan::command('diagnostics:failed-jobs', function (): int {
    try {
        $events = DB::table('diagnostic_occurrences as occurrence')
            ->join('diagnostic_incidents as incident', 'incident.id', '=', 'occurrence.incident_id')
            ->whereNotNull('occurrence.job_id')
            ->orderByDesc('occurrence.created_at')->limit(25)
            ->get(['occurrence.created_at', 'occurrence.job_id', 'occurrence.request_id', 'occurrence.operation']);
        $this->table(['Time', 'Job ID', 'Request ID', 'Job class'], $events->map(fn ($event): array => [
            $event->created_at, $event->job_id, $event->request_id, $event->operation,
        ])->all());
    } catch (Throwable $exception) {
        $reference = app(ConsoleFailureReporter::class)->report($exception, 'diagnostics:failed-jobs');
        $this->error('Failed-jobs lookup failed. Reference: '.$reference);

        return 1;
    }

    return 0;
})->purpose('List recent sanitized final queue failures');

Schedule::command('diagnostics:prune')->daily();

if (app()->environment('testing')) {
    Artisan::command('diagnostics:probe-queue', function (): void {
        DiagnosticFailureProbe::dispatch();
        $this->info('Synthetic failure job dispatched.');
    })->purpose('Dispatch a failure probe in the testing environment only');
}
