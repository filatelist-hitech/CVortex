#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

compose=(docker compose)
pg_user="${POSTGRES_USER:-$("${compose[@]}" exec -T postgres printenv POSTGRES_USER | tr -d '\r\n')}"
database="cvortex_vacancy_revalidation_$$_${RANDOM}"
database_created=false

cleanup() {
  if [[ "$database_created" == true ]]; then
    "${compose[@]}" exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 \
      -c "DROP DATABASE \"$database\" WITH (FORCE)" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

run_suite() {
  local stage="$1"
  echo "vacancy-postgres-revalidation: ${stage} suite"
  "${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" backend \
    php artisan test --filter=VacancyPostgresSecurityTest
}

"${compose[@]}" exec -T postgres bash /docker-entrypoint-initdb.d/10-runtime-role.sh >/dev/null
"${compose[@]}" exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 \
  -c "CREATE DATABASE \"$database\"" >/dev/null
database_created=true

echo 'vacancy-postgres-revalidation: fresh migration'
  "${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration \
  php artisan migrate --force

run_suite first
users_after_first=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM users')
test "$users_after_first" -gt 0

echo 'vacancy-postgres-revalidation: rollback snapshot-history and aggregate-state migrations'
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration \
  php artisan migrate:rollback --step=2 --force
constraint_present=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc "SELECT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'vacancy_snapshots_owner_id_content_hash_unique')")
test "$constraint_present" = f
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration \
  php artisan migrate --force

echo 'vacancy-postgres-revalidation: rollback vacancy remediation migrations'
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration \
  php artisan migrate:rollback --step=3 --force
users_after_rollback=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM users')
test "$users_after_rollback" = "$users_after_first"

echo 'vacancy-postgres-revalidation: migrate remediation up'
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration \
  php artisan migrate --force
users_after_reup=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM users')
test "$users_after_reup" = "$users_after_first"

run_suite second
users_after_second=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM users')
test "$users_after_second" -gt "$users_after_first"

echo "vacancy-postgres-revalidation: PASS (users preserved=${users_after_first}; users after second suite=${users_after_second})"
