#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

compose=(docker compose)
pg_user="${POSTGRES_USER:-$("${compose[@]}" exec -T postgres printenv POSTGRES_USER | tr -d '\r\n')}"
database="cvortex_vacancy_duplicate_migration_$$_${RANDOM}"
database_created=false

cleanup() {
  if [[ "$database_created" == true ]]; then
    "${compose[@]}" exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 \
      -c "DROP DATABASE \"$database\" WITH (FORCE)" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

"${compose[@]}" exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 \
  -c "CREATE DATABASE \"$database\"" >/dev/null
database_created=true

echo 'vacancy-postgres-duplicate-migration: prepare pre-remediation schema'
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration php artisan migrate --force
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration \
  php artisan migrate:rollback --step=3 --force

echo 'vacancy-postgres-duplicate-migration: seed legacy duplicate aggregates'
"${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -v ON_ERROR_STOP=1 <<'SQL'
INSERT INTO users (id, email, password, role, status, created_at, updated_at)
VALUES ('00000000000000000000000001', 'duplicate-migration@example.test', 'synthetic', 'user', 'ACTIVE', now(), now());

INSERT INTO vacancies (id, owner_id, source_type, source_url, analysis_status, error_code, created_at, updated_at)
VALUES
('00000000000000000000000011', '00000000000000000000000001', 'PASTED_TEXT', 'https://jobs.example.test/pending', 'COMPLETED', NULL, '2024-01-01 00:00:00+00', now()),
('00000000000000000000000012', '00000000000000000000000001', 'PASTED_TEXT', 'https://jobs.example.test/pending', 'PENDING', NULL, '2024-01-02 00:00:00+00', now()),
('00000000000000000000000013', '00000000000000000000000001', 'PASTED_TEXT', 'https://jobs.example.test/failed', 'COMPLETED', NULL, '2024-02-01 00:00:00+00', now()),
('00000000000000000000000014', '00000000000000000000000001', 'PASTED_TEXT', 'https://jobs.example.test/failed', 'FAILED', 'PROVIDER_ERROR', '2024-02-02 00:00:00+00', now()),
('00000000000000000000000015', '00000000000000000000000001', 'PASTED_TEXT', 'https://jobs.example.test/interleaved', 'COMPLETED', NULL, '2024-03-01 00:00:00+00', now()),
('00000000000000000000000016', '00000000000000000000000001', 'PASTED_TEXT', 'https://jobs.example.test/interleaved', 'PENDING', NULL, '2024-03-02 00:00:00+00', now()),
('00000000000000000000000017', '00000000000000000000000001', 'PASTED_TEXT', 'https://jobs.example.test/completed', 'PENDING', NULL, '2024-04-01 00:00:00+00', now());

INSERT INTO vacancy_snapshots (id, owner_id, vacancy_id, version, raw_text, source_url, content_hash, imported_at, created_at, updated_at)
VALUES
('00000000000000000000000021', '00000000000000000000000001', '00000000000000000000000011', 1, 'pending t1', 'https://jobs.example.test/pending', repeat('1', 64), '2024-01-01 00:00:00+00', '2024-01-01 00:00:00+00', now()),
('00000000000000000000000022', '00000000000000000000000001', '00000000000000000000000012', 1, 'pending t2', 'https://jobs.example.test/pending', repeat('2', 64), '2024-01-02 00:00:00+00', '2024-01-02 00:00:00+00', now()),
('00000000000000000000000023', '00000000000000000000000001', '00000000000000000000000013', 1, 'failed t1', 'https://jobs.example.test/failed', repeat('3', 64), '2024-02-01 00:00:00+00', '2024-02-01 00:00:00+00', now()),
('00000000000000000000000024', '00000000000000000000000001', '00000000000000000000000014', 1, 'failed t2', 'https://jobs.example.test/failed', repeat('4', 64), '2024-02-02 00:00:00+00', '2024-02-02 00:00:00+00', now()),
('00000000000000000000000025', '00000000000000000000000001', '00000000000000000000000015', 1, 'chronology t1', 'https://jobs.example.test/interleaved', repeat('5', 64), '2024-03-01 00:00:00+00', '2024-03-01 00:00:00+00', now()),
('00000000000000000000000026', '00000000000000000000000001', '00000000000000000000000016', 1, 'chronology t2', 'https://jobs.example.test/interleaved', repeat('6', 64), '2024-03-02 00:00:00+00', '2024-03-02 00:00:00+00', now()),
('00000000000000000000000027', '00000000000000000000000001', '00000000000000000000000016', 2, 'chronology t3', 'https://jobs.example.test/interleaved', repeat('7', 64), '2024-03-03 00:00:00+00', '2024-03-03 00:00:00+00', now()),
('00000000000000000000000028', '00000000000000000000000001', '00000000000000000000000015', 2, 'chronology t4', 'https://jobs.example.test/interleaved', repeat('8', 64), '2024-03-04 00:00:00+00', '2024-03-04 00:00:00+00', now()),
('00000000000000000000000029', '00000000000000000000000001', '00000000000000000000000017', 1, 'completed current snapshot', 'https://jobs.example.test/completed', repeat('9', 64), '2024-04-01 00:00:00+00', '2024-04-01 00:00:00+00', now());

INSERT INTO vacancy_analyses (id, owner_id, vacancy_id, vacancy_snapshot_id, career_signature, recommendation, key_reasons, material_gaps, uncertainties, analysis_version, created_at, updated_at)
VALUES
('00000000000000000000000031', '00000000000000000000000001', '00000000000000000000000011', '00000000000000000000000021', repeat('a', 64), 'MAYBE', '[]', '[]', '[]', '1.0.0', now(), now()),
('00000000000000000000000032', '00000000000000000000000001', '00000000000000000000000013', '00000000000000000000000023', repeat('b', 64), 'MAYBE', '[]', '[]', '[]', '1.0.0', now(), now()),
('00000000000000000000000033', '00000000000000000000000001', '00000000000000000000000015', '00000000000000000000000025', repeat('c', 64), 'MAYBE', '[]', '[]', '[]', '1.0.0', now(), now()),
('00000000000000000000000034', '00000000000000000000000001', '00000000000000000000000015', '00000000000000000000000028', repeat('d', 64), 'MAYBE', '[]', '[]', '[]', '1.0.0', now(), now()),
('00000000000000000000000036', '00000000000000000000000001', '00000000000000000000000017', '00000000000000000000000029', repeat('e', 64), 'MAYBE', '[]', '[]', '[]', '1.0.0', now(), now());

INSERT INTO vacancy_llm_runs (id, owner_id, vacancy_snapshot_id, workflow, skill_id, skill_version, prompt_version, model_policy, status, validation_result, error_category, created_at, updated_at)
VALUES
('00000000000000000000000035', '00000000000000000000000001', '00000000000000000000000024', 'vacancy_requirement_extraction', 'vacancy.requirement-extraction', '1.0.0', '1.0.0', 'default', 'FAILED', 'NOT_VALIDATED', 'PROVIDER_ERROR', now(), now()),
('00000000000000000000000037', '00000000000000000000000001', '00000000000000000000000028', 'vacancy_requirement_extraction', 'vacancy.requirement-extraction', '1.0.0', '1.0.0', 'default', 'RUNNING', 'NOT_VALIDATED', NULL, now(), now());
SQL

echo 'vacancy-postgres-duplicate-migration: upgrade legacy schema'
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration php artisan migrate --force

echo 'vacancy-postgres-duplicate-migration: assert status, chronology, and preservation'
"${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -v ON_ERROR_STOP=1 <<'SQL'
DO $$
DECLARE
    failures text[] := ARRAY[]::text[];
BEGIN
    IF (SELECT count(*) FROM vacancies) <> 4 THEN failures := array_append(failures, 'duplicate vacancies not converged'); END IF;
    IF (SELECT count(*) FROM vacancy_snapshots) <> 9 THEN failures := array_append(failures, 'snapshots not preserved'); END IF;
    IF (SELECT count(*) FROM vacancy_analyses) <> 5 THEN failures := array_append(failures, 'analyses not preserved'); END IF;
    IF (SELECT count(*) FROM vacancy_llm_runs) <> 2 THEN failures := array_append(failures, 'LLM runs not preserved'); END IF;
    IF (SELECT analysis_status FROM vacancies WHERE source_url = 'https://jobs.example.test/pending') <> 'PENDING' THEN failures := array_append(failures, 'latest pending snapshot status not reconciled'); END IF;
    IF (SELECT error_code FROM vacancies WHERE source_url = 'https://jobs.example.test/pending') IS NOT NULL THEN failures := array_append(failures, 'pending aggregate retained stale error'); END IF;
    IF (SELECT analysis_status FROM vacancies WHERE source_url = 'https://jobs.example.test/failed') <> 'FAILED' THEN failures := array_append(failures, 'latest failed snapshot status not reconciled'); END IF;
    IF (SELECT error_code FROM vacancies WHERE source_url = 'https://jobs.example.test/failed') <> 'PROVIDER_ERROR' THEN failures := array_append(failures, 'latest failed snapshot error not preserved'); END IF;
    IF (SELECT id FROM vacancy_snapshots WHERE vacancy_id = '00000000000000000000000015' ORDER BY version DESC LIMIT 1) <> '00000000000000000000000028' THEN failures := array_append(failures, 'merged snapshot chronology not preserved'); END IF;
    IF (SELECT string_agg(version::text, ',' ORDER BY imported_at) FROM vacancy_snapshots WHERE vacancy_id = '00000000000000000000000015') <> '1,2,3,4' THEN failures := array_append(failures, 'merged versions do not follow global chronology'); END IF;
    IF (SELECT analysis_status FROM vacancies WHERE source_url = 'https://jobs.example.test/interleaved') <> 'RUNNING' THEN failures := array_append(failures, 'active latest run was hidden by an older analysis'); END IF;
    IF (SELECT analysis_status FROM vacancies WHERE source_url = 'https://jobs.example.test/completed') <> 'COMPLETED' THEN failures := array_append(failures, 'latest analyzed snapshot status not reconciled'); END IF;
    IF cardinality(failures) > 0 THEN RAISE EXCEPTION 'migration regressions: %', array_to_string(failures, '; '); END IF;
END;
$$;
SQL

echo 'vacancy-postgres-duplicate-migration: rollback and re-up'
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration \
  php artisan migrate:rollback --step=3 --force
users_after_rollback=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM users')
test "$users_after_rollback" = 1
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration php artisan migrate --force
"${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -v ON_ERROR_STOP=1 <<'SQL'
DO $$
BEGIN
    IF (SELECT count(*) FROM vacancies) <> 4 OR (SELECT count(*) FROM vacancy_snapshots) <> 9 THEN RAISE EXCEPTION 'rollback/re-up lost vacancy data'; END IF;
    IF (SELECT analysis_status FROM vacancies WHERE source_url = 'https://jobs.example.test/pending') <> 'PENDING' THEN RAISE EXCEPTION 'rollback/re-up changed reconciled state'; END IF;
END;
$$;
SQL

echo "vacancy-postgres-duplicate-migration: PASS (isolated database preserved rows through rollback/re-up)"
