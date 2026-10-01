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
CREATE TABLE vacancy_reanalysis_test_barrier (name varchar(32) PRIMARY KEY, released boolean NOT NULL DEFAULT false);
INSERT INTO vacancy_reanalysis_test_barrier (name) VALUES ('reanalyze-a'), ('import-b'), ('analyze-pause'), ('recover-a'), ('career-recover-a');
SELECT format('GRANT SELECT ON vacancy_reanalysis_test_barrier TO %I', :'runtime_user') \gexec
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

race_worker() {
  "${compose[@]}" run --rm --no-deps -e DB_DATABASE="$database" backend php \
    tests/Support/vacancy_reanalysis_race_worker.php "$1" "$owner_id" "$source_url" "$2"
}

wait_marker() {
  local marker="$1" file="$2" deadline=$((SECONDS + 30))
  until grep -q "$marker" "$file"; do
    if (( SECONDS >= deadline )); then
      echo "race worker marker timed out: $marker" >&2
      cat "$file" >&2
      exit 1
    fi
    sleep 0.1
  done
}

wait_lock() {
  local action="$1" deadline=$((SECONDS + 30))
  until [[ "$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc \
    "SELECT count(*) FROM pg_stat_activity WHERE application_name = 'cvortex-race-$action' AND wait_event_type = 'Lock'")" -gt 0 ]]; do
    if (( SECONDS >= deadline )); then
      echo "expected PostgreSQL row-lock wait for $action" >&2
      exit 1
    fi
    sleep 0.1
  done
}

wait_worker() {
  local pid="$1" file="$2"
  if ! wait "$pid"; then
    cat "$file" >&2
    exit 1
  fi
}

current=$(race_worker current ignored | tail -n 1)
vacancy_id=$(sed -n 's/.*vacancy=\([^ ]*\).*/\1/p' <<<"$current")
old_snapshot=$(sed -n 's/.*snapshot=\([^ ]*\).*/\1/p' <<<"$current")
test -n "$vacancy_id" && test -n "$old_snapshot"
race_worker analyze "$old_snapshot" >/dev/null

# Race A: reanalysis selects the old snapshot while holding the aggregate row.
# A changed-content import must wait, then complete its own new analysis.
race_worker reanalyze-a "$vacancy_id" >"$temp_dir/reanalyze-a" 2>&1 &
reanalyze_pid=$!
wait_marker paused "$temp_dir/reanalyze-a"
race_worker import-a 'Concurrent content C.' >"$temp_dir/import-a" 2>&1 &
import_pid=$!
wait_lock import-a
"${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -v ON_ERROR_STOP=1 \
  -c "UPDATE vacancy_reanalysis_test_barrier SET released = true WHERE name = 'reanalyze-a'" >/dev/null
wait_worker "$reanalyze_pid" "$temp_dir/reanalyze-a"
wait_worker "$import_pid" "$temp_dir/import-a"
new_snapshot=$(sed -n 's/.*snapshot=\([^ ]*\).*/\1/p' "$temp_dir/import-a" | tail -n 1)
test -n "$new_snapshot"
race_worker stale "$old_snapshot"
race_worker verify "$new_snapshot"

# Race B: import owns the aggregate lock when reanalysis begins. The retry
# must select the newly committed snapshot after the import releases the row.
race_worker import-b 'Concurrent content D.' >"$temp_dir/import-b" 2>&1 &
import_pid=$!
wait_marker paused "$temp_dir/import-b"
race_worker reanalyze-b "$vacancy_id" >"$temp_dir/reanalyze-b" 2>&1 &
reanalyze_pid=$!
wait_lock reanalyze-b
"${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -v ON_ERROR_STOP=1 \
  -c "UPDATE vacancy_reanalysis_test_barrier SET released = true WHERE name = 'import-b'" >/dev/null
wait_worker "$import_pid" "$temp_dir/import-b"
wait_worker "$reanalyze_pid" "$temp_dir/reanalyze-b"
imported_snapshot=$(sed -n 's/.*snapshot=\([^ ]*\).*/\1/p' "$temp_dir/import-b" | tail -n 1)
reanalyzed_snapshot=$(sed -n 's/.*snapshot=\([^ ]*\).*/\1/p' "$temp_dir/reanalyze-b" | tail -n 1)
test -n "$imported_snapshot" && test "$imported_snapshot" = "$reanalyzed_snapshot"
race_worker analyze "$reanalyzed_snapshot"
race_worker verify "$reanalyzed_snapshot"

# A second retry during a running job must leave RUNNING intact. The original
# job can then publish COMPLETED instead of losing its unique queued rerun.
race_worker reanalyze-c "$vacancy_id" >/dev/null
race_worker analyze-pause "$reanalyzed_snapshot" >"$temp_dir/analyze-pause" 2>&1 &
analysis_pid=$!
wait_marker paused "$temp_dir/analyze-pause"
retry_one=$(race_worker reanalyze-c "$vacancy_id" | tail -n 1)
retry_two=$(race_worker reanalyze-c "$vacancy_id" | tail -n 1)
[[ "$retry_one" == *"status=RUNNING"* && "$retry_two" == *"status=RUNNING"* ]]
"${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -v ON_ERROR_STOP=1 \
  -c "UPDATE vacancy_reanalysis_test_barrier SET released = true WHERE name = 'analyze-pause'" >/dev/null
wait_worker "$analysis_pid" "$temp_dir/analyze-pause"
race_worker verify "$reanalyzed_snapshot"

# Two concurrent orphan recoveries must serialize on their owner-scoped rows.
recovery_seed=$(race_worker seed-recovery ignored | tail -n 1)
recovery_vacancy=$(sed -n 's/.*vacancy=\([^ ]*\).*/\1/p' <<<"$recovery_seed")
recovery_source=$(sed -n 's/.*career_source=\([^ ]*\).*/\1/p' <<<"$recovery_seed")
test -n "$recovery_vacancy" && test -n "$recovery_source"

race_worker recover-a "$recovery_vacancy" >"$temp_dir/recover-a" 2>&1 &
recover_pid=$!
wait_marker paused "$temp_dir/recover-a"
race_worker recover-b "$recovery_vacancy" >"$temp_dir/recover-b" 2>&1 &
recover_two_pid=$!
wait_lock recover-b
"${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -v ON_ERROR_STOP=1 \
  -c "UPDATE vacancy_reanalysis_test_barrier SET released = true WHERE name = 'recover-a'" >/dev/null
wait_worker "$recover_pid" "$temp_dir/recover-a"
wait_worker "$recover_two_pid" "$temp_dir/recover-b"
[[ "$(cat "$temp_dir/recover-a")" == *"jobs=1"* && "$(cat "$temp_dir/recover-b")" == *"jobs=0"* ]]

race_worker career-recover-a ignored >"$temp_dir/career-recover-a" 2>&1 &
career_recover_pid=$!
wait_marker paused "$temp_dir/career-recover-a"
race_worker career-recover-b ignored >"$temp_dir/career-recover-b" 2>&1 &
career_recover_two_pid=$!
wait_lock career-recover-b
"${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -v ON_ERROR_STOP=1 \
  -c "UPDATE vacancy_reanalysis_test_barrier SET released = true WHERE name = 'career-recover-a'" >/dev/null
wait_worker "$career_recover_pid" "$temp_dir/career-recover-a"
wait_worker "$career_recover_two_pid" "$temp_dir/career-recover-b"
[[ "$(cat "$temp_dir/career-recover-a")" == *"jobs=1"* && "$(cat "$temp_dir/career-recover-b")" == *"jobs=0"* ]]

echo 'vacancy-postgres-concurrency: PASS (import, reanalysis and single-dispatch Vacancy/Career orphan recovery races converged)'
