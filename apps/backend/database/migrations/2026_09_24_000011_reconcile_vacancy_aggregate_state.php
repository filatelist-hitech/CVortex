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

        DB::transaction(function (): void {
            DB::unprepared('DROP TRIGGER IF EXISTS vacancy_snapshots_immutable ON vacancy_snapshots');

            DB::unprepared(<<<'SQL'
DO $$
DECLARE
    vacancy_record record;
    version_offset integer;
BEGIN
    FOR vacancy_record IN SELECT DISTINCT vacancy_id FROM vacancy_snapshots LOOP
        SELECT COALESCE(max(version), 0) + count(*) + 1 INTO version_offset
        FROM vacancy_snapshots
        WHERE vacancy_id = vacancy_record.vacancy_id;

        UPDATE vacancy_snapshots
        SET version = version + version_offset
        WHERE vacancy_id = vacancy_record.vacancy_id;

        WITH chronological AS (
            SELECT id, row_number() OVER (ORDER BY imported_at, created_at, id)::integer AS next_version
            FROM vacancy_snapshots
            WHERE vacancy_id = vacancy_record.vacancy_id
        )
        UPDATE vacancy_snapshots AS snapshot
        SET version = chronological.next_version
        FROM chronological
        WHERE snapshot.id = chronological.id;
    END LOOP;
END;
$$;
SQL);

            DB::unprepared(<<<'SQL'
WITH latest_snapshots AS (
    SELECT DISTINCT ON (vacancy_id) vacancy_id, owner_id, id AS snapshot_id
    FROM vacancy_snapshots
    ORDER BY vacancy_id, version DESC, imported_at DESC, created_at DESC, id DESC
), latest_runs AS (
    SELECT DISTINCT ON (vacancy_snapshot_id) vacancy_snapshot_id, status, error_category
    FROM vacancy_llm_runs
    ORDER BY vacancy_snapshot_id, created_at DESC, id DESC
), analyzed_snapshots AS (
    SELECT DISTINCT vacancy_snapshot_id
    FROM vacancy_analyses
)
UPDATE vacancies AS vacancy
SET analysis_status = CASE
        WHEN latest_run.status = 'FAILED' THEN 'FAILED'
        WHEN latest_run.status = 'RUNNING' THEN 'RUNNING'
        WHEN analyzed_snapshot.vacancy_snapshot_id IS NOT NULL THEN 'COMPLETED'
        ELSE 'PENDING'
    END,
    error_code = CASE
        WHEN latest_run.status <> 'FAILED' OR latest_run.status IS NULL THEN NULL
        WHEN latest_run.error_category = 'PROVIDER_ERROR' THEN 'PROVIDER_ERROR'
        WHEN latest_run.error_category IN ('SCHEMA_INVALID', 'SEMANTIC_REJECTED') THEN 'INVALID_EXTRACTION_RESULT'
        ELSE 'ANALYSIS_ERROR'
    END,
    updated_at = now()
FROM latest_snapshots AS latest_snapshot
LEFT JOIN latest_runs AS latest_run ON latest_run.vacancy_snapshot_id = latest_snapshot.snapshot_id
LEFT JOIN analyzed_snapshots AS analyzed_snapshot ON analyzed_snapshot.vacancy_snapshot_id = latest_snapshot.snapshot_id
WHERE vacancy.id = latest_snapshot.vacancy_id
  AND vacancy.owner_id = latest_snapshot.owner_id;

CREATE TRIGGER vacancy_snapshots_immutable
BEFORE UPDATE ON vacancy_snapshots
FOR EACH ROW EXECUTE FUNCTION reject_vacancy_snapshot_update();
SQL);
        });
    }

    public function down(): void
    {
        // This forward data repair is idempotent and intentionally retained on rollback.
        // Reverting versions or aggregate status would restore the inconsistent state it fixes.
    }
};
