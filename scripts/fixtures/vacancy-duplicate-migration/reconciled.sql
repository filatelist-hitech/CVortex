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
    IF cardinality(failures) > 0 THEN RAISE EXCEPTION 'migration regressions: %', array_to_string(failures, '; '); END IF;
END;
$$;
