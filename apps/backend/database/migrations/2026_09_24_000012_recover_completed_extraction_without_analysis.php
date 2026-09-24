<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
WITH latest_snapshots AS (
    SELECT DISTINCT ON (vacancy_id) vacancy_id, owner_id, id AS snapshot_id
    FROM vacancy_snapshots
    ORDER BY vacancy_id, version DESC, imported_at DESC, created_at DESC, id DESC
), latest_runs AS (
    SELECT DISTINCT ON (vacancy_snapshot_id) vacancy_snapshot_id, status
    FROM vacancy_llm_runs
    ORDER BY vacancy_snapshot_id, created_at DESC, id DESC
), analyzed_snapshots AS (
    SELECT DISTINCT vacancy_snapshot_id
    FROM vacancy_analyses
)
UPDATE vacancies AS vacancy
SET analysis_status = 'FAILED',
    error_code = 'ANALYSIS_ERROR',
    updated_at = now()
FROM latest_snapshots AS latest_snapshot
JOIN latest_runs AS latest_run ON latest_run.vacancy_snapshot_id = latest_snapshot.snapshot_id
LEFT JOIN analyzed_snapshots AS analyzed_snapshot ON analyzed_snapshot.vacancy_snapshot_id = latest_snapshot.snapshot_id
WHERE vacancy.id = latest_snapshot.vacancy_id
  AND vacancy.owner_id = latest_snapshot.owner_id
  AND vacancy.analysis_status = 'PENDING'
  AND vacancy.error_code IS NULL
  AND latest_run.status = 'COMPLETED'
  AND analyzed_snapshot.vacancy_snapshot_id IS NULL;
SQL);
    }

    public function down(): void
    {
        // Keep this forward repair on rollback; restoring PENDING would strand failed analyses.
    }
};
