DO $$
DECLARE
    table_name text;
    constraint_name text;
BEGIN
    IF EXISTS (SELECT FROM migrations WHERE migration >= '2026_09_19_000009') THEN
        RAISE EXCEPTION 'legacy schema: remediation/later migration already recorded';
    END IF;
    IF NOT EXISTS (SELECT FROM migrations WHERE migration = '2026_09_19_000008_create_vacancy_core_tables') THEN
        RAISE EXCEPTION 'legacy schema: 000008 boundary missing';
    END IF;
    FOREACH table_name IN ARRAY ARRAY['vacancies', 'vacancy_snapshots', 'vacancy_requirements',
        'vacancy_analyses', 'vacancy_match_dimensions', 'vacancy_match_evidence', 'vacancy_llm_runs'] LOOP
        IF to_regclass(table_name) IS NULL THEN RAISE EXCEPTION 'legacy schema: missing table %', table_name; END IF;
        IF EXISTS (SELECT FROM pg_class WHERE oid = to_regclass(table_name) AND relrowsecurity) THEN
            RAISE EXCEPTION 'legacy schema: unexpected RLS on %', table_name;
        END IF;
    END LOOP;
    IF EXISTS (SELECT FROM pg_indexes WHERE tablename='vacancies' AND indexdef LIKE 'CREATE UNIQUE INDEX%'
               AND indexdef LIKE '%(owner_id, source_url)%') THEN
        RAISE EXCEPTION 'legacy schema: owner/source unique index already present';
    END IF;
    IF EXISTS (SELECT FROM pg_trigger WHERE tgrelid='vacancy_snapshots'::regclass AND NOT tgisinternal) THEN
        RAISE EXCEPTION 'legacy schema: unexpected snapshot trigger';
    END IF;
    IF NOT EXISTS (SELECT FROM information_schema.columns c WHERE c.table_name='vacancy_snapshots'
                   AND column_name='version' AND data_type='integer' AND is_nullable='NO') OR
       NOT EXISTS (SELECT FROM information_schema.columns c WHERE c.table_name='vacancy_snapshots'
                   AND column_name='imported_at' AND data_type='timestamp with time zone' AND is_nullable='NO') THEN
        RAISE EXCEPTION 'legacy schema: snapshot version/chronology columns wrong';
    END IF;
    FOREACH constraint_name IN ARRAY ARRAY['vacancy_snapshots_vacancy_id_version_unique',
        'vacancy_snapshots_vacancy_owner_fk', 'vacancy_analyses_vacancy_owner_fk',
        'vacancy_analyses_snapshot_owner_fk', 'vacancy_llm_runs_snapshot_owner_fk'] LOOP
        IF NOT EXISTS (SELECT FROM pg_constraint WHERE conname=constraint_name AND convalidated) THEN
            RAISE EXCEPTION 'legacy schema: missing validated constraint %', constraint_name;
        END IF;
    END LOOP;
    IF to_regclass('vacancies_owner_source_url_unique') IS NOT NULL OR
       EXISTS (SELECT FROM pg_constraint WHERE conname='vacancy_snapshots_owner_id_content_hash_unique') THEN
        RAISE EXCEPTION 'legacy schema: unexpected uniqueness constraint';
    END IF;
END;
$$;
SELECT 'QA002-01/02: legacy schema and remediation absence PASS';
