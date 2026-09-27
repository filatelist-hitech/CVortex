<?php

use App\Jobs\DiagnosticFailureProbe;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('diagnostics:prune', function (): void {
    DB::table('diagnostic_occurrences')->where('created_at', '<', now()->subDays(config('diagnostics.occurrence_days')))->delete();
    DB::table('diagnostic_incidents')->whereIn('status', ['RESOLVED', 'IGNORED'])
        ->where('last_seen_at', '<', now()->subDays(config('diagnostics.closed_incident_days')))->delete();
    $this->info('Expired diagnostic occurrences and closed incidents pruned.');
})->purpose('Apply diagnostic retention without deleting active incidents');

Artisan::command('diagnostics:failed-jobs', function (): void {
    $events = DB::table('diagnostic_occurrences as occurrence')
        ->join('diagnostic_incidents as incident', 'incident.id', '=', 'occurrence.incident_id')
        ->whereIn('incident.error_code', ['QUEUE_JOB_FAILED', 'LLM_PROVIDER_UNAVAILABLE'])
        ->whereNotNull('occurrence.job_id')
        ->orderByDesc('occurrence.created_at')->limit(25)
        ->get(['occurrence.created_at', 'occurrence.job_id', 'occurrence.request_id', 'occurrence.operation']);
    $this->table(['Time', 'Job ID', 'Request ID', 'Job class'], $events->map(fn ($event): array => [
        $event->created_at, $event->job_id, $event->request_id, $event->operation,
    ])->all());
})->purpose('List recent sanitized final queue failures');

Schedule::command('diagnostics:prune')->daily();

if (app()->environment('testing')) {
    Artisan::command('diagnostics:probe-queue', function (): void {
        DiagnosticFailureProbe::dispatch();
        $this->info('Synthetic failure job dispatched.');
    })->purpose('Dispatch a failure probe in the testing environment only');
}
