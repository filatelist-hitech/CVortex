#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

compose=(docker compose)
pg_user="${POSTGRES_USER:-$("${compose[@]}" exec -T postgres printenv POSTGRES_USER | tr -d '\r\n')}"
database="cvortex_application_boundary_$$_${RANDOM}"
database_created=false

cleanup() {
  if [[ "$database_created" == true ]]; then
    "${compose[@]}" exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 \
      -c "DROP DATABASE \"$database\" WITH (FORCE)" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

runtime_user="${POSTGRES_RUNTIME_USER:-$(${compose[@]} exec -T postgres printenv POSTGRES_RUNTIME_USER | tr -d '\r\n')}"
if [[ ! "$runtime_user" =~ ^[a-z_][a-z0-9_]*$ ]]; then
  echo 'POSTGRES_RUNTIME_USER must be a simple PostgreSQL role name' >&2
  exit 1
fi
role_flags=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d postgres -Atqc \
  "SELECT rolsuper::int || ':' || rolbypassrls::int FROM pg_roles WHERE rolname = '$runtime_user'")
test "$role_flags" = '0:0'
"${compose[@]}" exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 \
  -c "CREATE DATABASE \"$database\"" >/dev/null
database_created=true

echo 'application-postgres-boundary: fresh administrative migration'
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration php artisan migrate --force

echo 'application-postgres-boundary: runtime-role security suite'
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" backend \
  php artisan test --filter=ApplicationPostgresSecurityTest

before_users=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM users')
before_facts=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM career_facts')
before_vacancies=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM vacancies')

echo 'application-postgres-boundary: rollback application preparation migration'
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration php artisan migrate:rollback --step=1 --force
application_tables=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc \
  "SELECT count(*) FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE 'application_%'")
test "$application_tables" = 0

after_users=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM users')
after_facts=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM career_facts')
after_vacancies=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM vacancies')
test "$after_users" = "$before_users"
test "$after_facts" = "$before_facts"
test "$after_vacancies" = "$before_vacancies"

echo 'application-postgres-boundary: migrate up again and rerun security suite'
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration php artisan migrate --force
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" backend \
  php artisan test --filter=ApplicationPostgresSecurityTest

echo "application-postgres-boundary: PASS (preserved users=$after_users facts=$after_facts vacancies=$after_vacancies)"
