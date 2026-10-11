DO $$
DECLARE
    table_name text;
BEGIN
    IF NOT EXISTS (
        SELECT FROM pg_index WHERE indexrelid=to_regclass('vacancies_owner_source_url_unique')
        AND indrelid='vacancies'::regclass AND indisunique AND indisvalid AND indnkeyatts=2
        AND pg_get_indexdef(indexrelid) LIKE '%ON public.vacancies USING btree (owner_id, source_url)%'
        AND pg_get_expr(indpred, indrelid)='(source_url IS NOT NULL)'
    ) THEN RAISE EXCEPTION 'repaired schema: wrong/missing partial owner/source unique index'; END IF;
    FOREACH table_name IN ARRAY ARRAY['vacancies', 'vacancy_snapshots', 'vacancy_requirements',
        'vacancy_analyses', 'vacancy_match_dimensions', 'vacancy_match_evidence', 'vacancy_llm_runs'] LOOP
        IF NOT EXISTS (SELECT FROM pg_class WHERE oid=to_regclass(table_name) AND relrowsecurity AND relforcerowsecurity) OR
           NOT EXISTS (SELECT FROM pg_policies WHERE tablename=table_name AND policyname=table_name || '_owner_isolation') THEN
            RAISE EXCEPTION 'repaired schema: forced owner RLS/policy missing on %', table_name;
        END IF;
    END LOOP;
    IF NOT EXISTS (SELECT FROM pg_trigger WHERE tgrelid='vacancy_snapshots'::regclass
                   AND tgname='vacancy_snapshots_immutable' AND tgenabled='O') THEN
        RAISE EXCEPTION 'repaired schema: immutable snapshot trigger missing';
    END IF;
    IF EXISTS (SELECT FROM vacancy_snapshots s JOIN vacancies v ON v.id=s.vacancy_id
               WHERE s.owner_id IS DISTINCT FROM v.owner_id) OR
       EXISTS (SELECT FROM vacancy_analyses a JOIN vacancy_snapshots s ON s.id=a.vacancy_snapshot_id
               WHERE a.owner_id IS DISTINCT FROM s.owner_id OR a.vacancy_id IS DISTINCT FROM s.vacancy_id) OR
       EXISTS (SELECT FROM vacancy_llm_runs r JOIN vacancy_snapshots s ON s.id=r.vacancy_snapshot_id
               WHERE r.owner_id IS DISTINCT FROM s.owner_id) THEN
        RAISE EXCEPTION 'repaired schema: owner/aggregate relationships broken';
    END IF;
    IF EXISTS (SELECT FROM pg_constraint WHERE conname='vacancy_snapshots_owner_id_content_hash_unique') THEN
        RAISE EXCEPTION 'repaired schema: unexpected owner-wide snapshot content uniqueness';
    END IF;
END;
$$;
SELECT 'QA002-08: unique index, RLS, trigger and owner relationships PASS';
