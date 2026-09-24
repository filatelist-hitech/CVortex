<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const OWNER_TABLES = [
        'vacancies',
        'vacancy_snapshots',
        'vacancy_requirements',
        'vacancy_analyses',
        'vacancy_match_dimensions',
        'vacancy_match_evidence',
        'vacancy_llm_runs',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->convergeDuplicateUrlAggregates();

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX vacancies_owner_source_url_unique
ON vacancies (owner_id, source_url)
WHERE source_url IS NOT NULL;

CREATE OR REPLACE FUNCTION reject_vacancy_snapshot_update() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'vacancy snapshots are immutable' USING ERRCODE = '55000';
END;
$$;

CREATE TRIGGER vacancy_snapshots_immutable
BEFORE UPDATE ON vacancy_snapshots
FOR EACH ROW EXECUTE FUNCTION reject_vacancy_snapshot_update();
SQL);

        foreach (self::OWNER_TABLES as $table) {
            DB::unprepared(<<<SQL
ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;
ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;
CREATE POLICY {$table}_owner_isolation ON {$table}
    USING (owner_id::text = NULLIF(current_setting('cvortex.owner_id', true), ''))
    WITH CHECK (owner_id::text = NULLIF(current_setting('cvortex.owner_id', true), ''));
SQL);
        }

        $role = $this->quotedRuntimeRole();
        DB::unprepared("GRANT USAGE ON SCHEMA public TO {$role}");
        DB::unprepared("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO {$role}");
        DB::unprepared("GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO {$role}");
        DB::unprepared("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$role}");
        DB::unprepared("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO {$role}");
    }

    private function convergeDuplicateUrlAggregates(): void
    {
        DB::unprepared(<<<'SQL'
DO $$
DECLARE
    logical_vacancy record;
    duplicate_vacancy record;
    snapshot_record record;
    next_version integer;
BEGIN
    FOR logical_vacancy IN
        SELECT owner_id, source_url,
               (array_agg(id ORDER BY created_at, id))[1] AS canonical_id
        FROM vacancies
        WHERE source_url IS NOT NULL
        GROUP BY owner_id, source_url
        HAVING count(*) > 1
    LOOP
        SELECT COALESCE(max(version), 0) INTO next_version
        FROM vacancy_snapshots
        WHERE vacancy_id = logical_vacancy.canonical_id;

        FOR duplicate_vacancy IN
            SELECT id
            FROM vacancies
            WHERE owner_id = logical_vacancy.owner_id
              AND source_url = logical_vacancy.source_url
              AND id <> logical_vacancy.canonical_id
            ORDER BY created_at, id
        LOOP
            FOR snapshot_record IN
                SELECT id
                FROM vacancy_snapshots
                WHERE vacancy_id = duplicate_vacancy.id
                ORDER BY version, id
            LOOP
                next_version := next_version + 1;
                UPDATE vacancy_snapshots
                SET vacancy_id = logical_vacancy.canonical_id,
                    version = next_version
                WHERE id = snapshot_record.id;
            END LOOP;

            UPDATE vacancy_analyses
            SET vacancy_id = logical_vacancy.canonical_id
            WHERE vacancy_id = duplicate_vacancy.id;

            DELETE FROM vacancies WHERE id = duplicate_vacancy.id;
        END LOOP;
    END LOOP;
END;
$$;
SQL);
    }

    private function quotedRuntimeRole(): string
    {
        $role = (string) config('database.runtime_role');
        if (preg_match('/^[a-z_][a-z0-9_]*$/', $role) !== 1) {
            throw new RuntimeException('DB_RUNTIME_ROLE must be a simple PostgreSQL role name.');
        }

        return '"'.$role.'"';
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_reverse(self::OWNER_TABLES) as $table) {
            DB::unprepared(<<<SQL
DROP POLICY IF EXISTS {$table}_owner_isolation ON {$table};
ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY;
SQL);
        }

        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS vacancy_snapshots_immutable ON vacancy_snapshots;
DROP FUNCTION IF EXISTS reject_vacancy_snapshot_update();
DROP INDEX IF EXISTS vacancies_owner_source_url_unique;
SQL);
    }
};
