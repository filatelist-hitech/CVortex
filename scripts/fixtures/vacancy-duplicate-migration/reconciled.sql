DO $$
DECLARE
    failures text[] := ARRAY[]::text[];
BEGIN
    IF (SELECT count(*) FROM vacancies) IS DISTINCT FROM 5 THEN failures := array_append(failures, 'duplicate vacancies not converged or matching failure row lost'); END IF;
    IF (SELECT count(*) FROM vacancy_snapshots) IS DISTINCT FROM 10 THEN failures := array_append(failures, 'snapshots not preserved'); END IF;
    IF (SELECT count(*) FROM vacancy_analyses) IS DISTINCT FROM 5 THEN failures := array_append(failures, 'analyses not preserved'); END IF;
    IF (SELECT count(*) FROM vacancy_llm_runs) IS DISTINCT FROM 3 THEN failures := array_append(failures, 'LLM runs not preserved'); END IF;
    IF (SELECT analysis_status FROM vacancies WHERE source_url = 'https://jobs.example.test/pending') IS DISTINCT FROM 'PENDING' THEN failures := array_append(failures, 'latest pending snapshot status not reconciled'); END IF;
    IF (SELECT error_code FROM vacancies WHERE source_url = 'https://jobs.example.test/pending') IS NOT NULL THEN failures := array_append(failures, 'pending aggregate retained stale error'); END IF;
    IF (SELECT analysis_status FROM vacancies WHERE source_url = 'https://jobs.example.test/failed') IS DISTINCT FROM 'FAILED' THEN failures := array_append(failures, 'latest failed snapshot status not reconciled'); END IF;
    IF (SELECT error_code FROM vacancies WHERE source_url = 'https://jobs.example.test/failed') IS DISTINCT FROM 'PROVIDER_ERROR' THEN failures := array_append(failures, 'latest failed snapshot error not preserved'); END IF;
    IF (SELECT id FROM vacancy_snapshots WHERE vacancy_id = '00000000000000000000000015' ORDER BY version DESC LIMIT 1) IS DISTINCT FROM '00000000000000000000000028' THEN failures := array_append(failures, 'merged snapshot chronology not preserved'); END IF;
    IF (SELECT string_agg(version::text, ',' ORDER BY imported_at) FROM vacancy_snapshots WHERE vacancy_id = '00000000000000000000000015') IS DISTINCT FROM '1,2,3,4' THEN failures := array_append(failures, 'merged versions do not follow global chronology'); END IF;
    IF (SELECT analysis_status FROM vacancies WHERE source_url = 'https://jobs.example.test/interleaved') IS DISTINCT FROM 'RUNNING' THEN failures := array_append(failures, 'active latest run was hidden by an older analysis'); END IF;
    IF (SELECT analysis_status FROM vacancies WHERE source_url = 'https://jobs.example.test/completed') IS DISTINCT FROM 'COMPLETED' THEN failures := array_append(failures, 'latest analyzed snapshot status not reconciled'); END IF;
    IF (SELECT analysis_status FROM vacancies WHERE source_url = 'https://jobs.example.test/matching-failed') IS DISTINCT FROM 'FAILED' THEN failures := array_append(failures, 'matching failure was cleared into unrecoverable pending state'); END IF;
    IF (SELECT error_code FROM vacancies WHERE source_url = 'https://jobs.example.test/matching-failed') IS DISTINCT FROM 'ANALYSIS_ERROR' THEN failures := array_append(failures, 'matching failure code was cleared'); END IF;

    -- FK & aggregate mapping assertions: vacancy -> snapshots
    IF (SELECT string_agg(id, ',' ORDER BY id) FROM vacancy_snapshots WHERE vacancy_id = '00000000000000000000000011') IS DISTINCT FROM '00000000000000000000000021,00000000000000000000000022' THEN failures := array_append(failures, 'vacancy 11 snapshots mapping mismatch'); END IF;
    IF (SELECT string_agg(id, ',' ORDER BY id) FROM vacancy_snapshots WHERE vacancy_id = '00000000000000000000000013') IS DISTINCT FROM '00000000000000000000000023,00000000000000000000000024' THEN failures := array_append(failures, 'vacancy 13 snapshots mapping mismatch'); END IF;
    IF (SELECT string_agg(id, ',' ORDER BY id) FROM vacancy_snapshots WHERE vacancy_id = '00000000000000000000000015') IS DISTINCT FROM '00000000000000000000000025,00000000000000000000000026,00000000000000000000000027,00000000000000000000000028' THEN failures := array_append(failures, 'vacancy 15 snapshots mapping mismatch'); END IF;
    IF (SELECT string_agg(id, ',' ORDER BY id) FROM vacancy_snapshots WHERE vacancy_id = '00000000000000000000000017') IS DISTINCT FROM '00000000000000000000000029' THEN failures := array_append(failures, 'vacancy 17 snapshots mapping mismatch'); END IF;
    IF (SELECT string_agg(id, ',' ORDER BY id) FROM vacancy_snapshots WHERE vacancy_id = '00000000000000000000000018') IS DISTINCT FROM '00000000000000000000000030' THEN failures := array_append(failures, 'vacancy 18 snapshots mapping mismatch'); END IF;

    -- FK & aggregate mapping assertions: vacancy & snapshot -> analyses
    IF (SELECT string_agg(id, ',' ORDER BY id) FROM vacancy_analyses WHERE vacancy_id = '00000000000000000000000011') IS DISTINCT FROM '00000000000000000000000031' THEN failures := array_append(failures, 'vacancy 11 analyses mapping mismatch'); END IF;
    IF (SELECT vacancy_snapshot_id FROM vacancy_analyses WHERE id = '00000000000000000000000031') IS DISTINCT FROM '00000000000000000000000021' THEN failures := array_append(failures, 'analysis 31 snapshot mapping mismatch'); END IF;

    IF (SELECT string_agg(id, ',' ORDER BY id) FROM vacancy_analyses WHERE vacancy_id = '00000000000000000000000013') IS DISTINCT FROM '00000000000000000000000032' THEN failures := array_append(failures, 'vacancy 13 analyses mapping mismatch'); END IF;
    IF (SELECT vacancy_snapshot_id FROM vacancy_analyses WHERE id = '00000000000000000000000032') IS DISTINCT FROM '00000000000000000000000023' THEN failures := array_append(failures, 'analysis 32 snapshot mapping mismatch'); END IF;

    IF (SELECT string_agg(id, ',' ORDER BY id) FROM vacancy_analyses WHERE vacancy_id = '00000000000000000000000015') IS DISTINCT FROM '00000000000000000000000033,00000000000000000000000034' THEN failures := array_append(failures, 'vacancy 15 analyses mapping mismatch'); END IF;
    IF (SELECT string_agg(vacancy_snapshot_id, ',' ORDER BY id) FROM vacancy_analyses WHERE vacancy_id = '00000000000000000000000015') IS DISTINCT FROM '00000000000000000000000025,00000000000000000000000028' THEN failures := array_append(failures, 'vacancy 15 analyses snapshot mapping mismatch'); END IF;

    IF (SELECT string_agg(id, ',' ORDER BY id) FROM vacancy_analyses WHERE vacancy_id = '00000000000000000000000017') IS DISTINCT FROM '00000000000000000000000036' THEN failures := array_append(failures, 'vacancy 17 analyses mapping mismatch'); END IF;
    IF (SELECT vacancy_snapshot_id FROM vacancy_analyses WHERE id = '00000000000000000000000036') IS DISTINCT FROM '00000000000000000000000029' THEN failures := array_append(failures, 'analysis 36 snapshot mapping mismatch'); END IF;

    IF (SELECT count(*) FROM vacancy_analyses WHERE vacancy_id = '00000000000000000000000018') IS DISTINCT FROM 0 THEN failures := array_append(failures, 'vacancy 18 should have no analyses'); END IF;

    -- FK & aggregate mapping assertions: snapshot -> LLM runs
    IF (SELECT string_agg(id, ',' ORDER BY id) FROM vacancy_llm_runs WHERE vacancy_snapshot_id = '00000000000000000000000024') IS DISTINCT FROM '00000000000000000000000035' THEN failures := array_append(failures, 'snapshot 24 llm run mapping mismatch'); END IF;
    IF (SELECT string_agg(id, ',' ORDER BY id) FROM vacancy_llm_runs WHERE vacancy_snapshot_id = '00000000000000000000000028') IS DISTINCT FROM '00000000000000000000000037' THEN failures := array_append(failures, 'snapshot 28 llm run mapping mismatch'); END IF;
    IF (SELECT string_agg(id, ',' ORDER BY id) FROM vacancy_llm_runs WHERE vacancy_snapshot_id = '00000000000000000000000030') IS DISTINCT FROM '00000000000000000000000038' THEN failures := array_append(failures, 'snapshot 30 llm run mapping mismatch'); END IF;

    -- Aggregate invariant consistency across all tables
    IF EXISTS (
        SELECT 1 FROM vacancy_snapshots s
        JOIN vacancies v ON v.id = s.vacancy_id
        WHERE s.source_url IS DISTINCT FROM v.source_url OR s.owner_id IS DISTINCT FROM v.owner_id
    ) THEN failures := array_append(failures, 'snapshot source_url or owner_id differs from parent vacancy'); END IF;

    IF EXISTS (
        SELECT 1 FROM vacancy_analyses a
        JOIN vacancy_snapshots s ON s.id = a.vacancy_snapshot_id
        WHERE a.vacancy_id IS DISTINCT FROM s.vacancy_id OR a.owner_id IS DISTINCT FROM s.owner_id
    ) THEN failures := array_append(failures, 'analysis aggregate linkage differs from snapshot'); END IF;

    IF EXISTS (
        SELECT 1 FROM vacancy_llm_runs r
        JOIN vacancy_snapshots s ON s.id = r.vacancy_snapshot_id
        WHERE r.owner_id IS DISTINCT FROM s.owner_id
    ) THEN failures := array_append(failures, 'llm run owner_id differs from snapshot'); END IF;

    IF cardinality(failures) > 0 THEN RAISE EXCEPTION 'migration regressions: %', array_to_string(failures, '; '); END IF;
END;
$$;
