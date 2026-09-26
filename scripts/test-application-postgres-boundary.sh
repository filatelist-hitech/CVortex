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
  php artisan test --filter=ApplicationPostgresSecurityTest --display-warnings

echo 'application-postgres-boundary: seed pre-existing parent data before migration rollback/re-up'
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration php artisan tinker --execute='
  $user = App\Models\User::query()->create(["email" => "application-boundary-baseline@example.test", "password" => Illuminate\Support\Facades\Hash::make(Illuminate\Support\Str::random(48))]);
  app(App\Services\CareerFactService::class)->createManual($user, "skill", "Boundary preservation fact.");
  App\Models\Vacancy::query()->create(["owner_id" => $user->id, "source_type" => "PASTED_TEXT", "title" => "Boundary preservation vacancy", "analysis_status" => "PENDING"]);
'

before_users=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM users')
before_facts=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM career_facts')
before_vacancies=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM vacancies')
baseline_user=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc "SELECT count(*) FROM users WHERE email = 'application-boundary-baseline@example.test'")
baseline_fact=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc "SELECT count(*) FROM career_facts f JOIN users u ON u.id = f.owner_id WHERE u.email = 'application-boundary-baseline@example.test' AND f.assertion_approved = 'Boundary preservation fact.'")
baseline_vacancy=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc "SELECT count(*) FROM vacancies v JOIN users u ON u.id = v.owner_id WHERE u.email = 'application-boundary-baseline@example.test'")
test "$baseline_user" = 1
test "$baseline_fact" = 1
test "$baseline_vacancy" = 1

echo 'application-postgres-boundary: rollback OAuth and application preparation migrations'
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration php artisan migrate:rollback --step=6 --force
application_tables=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc \
  "SELECT count(*) FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE 'application_%'")
test "$application_tables" = 0
oauth_tables=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc \
  "SELECT count(*) FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE 'oauth_%'")
test "$oauth_tables" = 0

after_users=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM users')
after_facts=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM career_facts')
after_vacancies=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM vacancies')
test "$after_users" = "$before_users"
test "$after_facts" = "$before_facts"
test "$after_vacancies" = "$before_vacancies"
test "$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc "SELECT count(*) FROM users WHERE email = 'application-boundary-baseline@example.test'")" = "$baseline_user"
test "$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc "SELECT count(*) FROM career_facts f JOIN users u ON u.id = f.owner_id WHERE u.email = 'application-boundary-baseline@example.test' AND f.assertion_approved = 'Boundary preservation fact.'")" = "$baseline_fact"
test "$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc "SELECT count(*) FROM vacancies v JOIN users u ON u.id = v.owner_id WHERE u.email = 'application-boundary-baseline@example.test'")" = "$baseline_vacancy"

echo 'application-postgres-boundary: migrate up again and rerun security suite'
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration php artisan migrate --force
test "$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc "SELECT count(*) FROM users WHERE email = 'application-boundary-baseline@example.test'")" = "$baseline_user"
test "$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc "SELECT count(*) FROM career_facts f JOIN users u ON u.id = f.owner_id WHERE u.email = 'application-boundary-baseline@example.test' AND f.assertion_approved = 'Boundary preservation fact.'")" = "$baseline_fact"
test "$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc "SELECT count(*) FROM vacancies v JOIN users u ON u.id = v.owner_id WHERE u.email = 'application-boundary-baseline@example.test'")" = "$baseline_vacancy"
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" backend \
  php artisan test --filter=ApplicationPostgresSecurityTest --display-warnings

echo "application-postgres-boundary: PASS (preserved users=$after_users facts=$after_facts vacancies=$after_vacancies)"
