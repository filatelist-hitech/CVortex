#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

compose=(docker compose)
pg_user="${POSTGRES_USER:-$("${compose[@]}" exec -T postgres printenv POSTGRES_USER | tr -d '\r\n')}"
runtime_user="${POSTGRES_RUNTIME_USER:-$("${compose[@]}" exec -T postgres printenv POSTGRES_RUNTIME_USER | tr -d '\r\n')}"
database="cvortex_vacancy_race_$$_${RANDOM}"
database_created=false
temp_dir="$(mktemp -d -t cvortex-vacancy-race.XXXXXX)"
output_one="$temp_dir/one"
output_two="$temp_dir/two"

cleanup() {
  rm -rf "$temp_dir"
  if [[ "$database_created" == true ]]; then
    "${compose[@]}" exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 \
      -c "DROP DATABASE \"$database\" WITH (FORCE)" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

"${compose[@]}" exec -T postgres bash /docker-entrypoint-initdb.d/10-runtime-role.sh >/dev/null
"${compose[@]}" exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 \
  -c "CREATE DATABASE \"$database\"" >/dev/null
database_created=true
"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" migration \
  php artisan migrate --force >/dev/null
owner_id=$("${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" backend \
  php tests/Support/prepare_vacancy_import_concurrency.php | tail -n 1 | tr -d '\r')
test -n "$owner_id"

"${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -v ON_ERROR_STOP=1 \
  --set=runtime_user="$runtime_user" <<'SQL' >/dev/null
CREATE TABLE vacancy_import_test_barrier (worker_id varchar(16) PRIMARY KEY);
SELECT format('GRANT SELECT, INSERT ON vacancy_import_test_barrier TO %I', :'runtime_user') \gexec
SQL

source_url='https://jobs.example.test/concurrent-vacancy'
set +e
("${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" backend php \
  tests/Support/vacancy_import_worker.php "$owner_id" "$source_url" 'Concurrent content A.' one) >"$output_one" 2>&1 &
pid_one=$!
("${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" backend php \
  tests/Support/vacancy_import_worker.php "$owner_id" "$source_url" 'Concurrent content B.' two) >"$output_two" 2>&1 &
pid_two=$!
wait "$pid_one"
status_one=$?
wait "$pid_two"
status_two=$?
set -e

if [[ "$status_one" != 0 || "$status_two" != 0 ]]; then
  echo "vacancy import workers failed: statuses=$status_one/$status_two" >&2
  cat "$output_one" "$output_two" >&2
  exit 1
fi

"${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" backend php \
  tests/Support/verify_vacancy_import_concurrency.php "$owner_id" "$source_url"
echo 'vacancy-postgres-concurrency: PASS (independent concurrent workers converged on one aggregate)'
