#!/usr/bin/env bash
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
pg_user="$(docker compose exec -T postgres printenv POSTGRES_USER | tr -d '\r\n')"
test_db="cvortex_chatgpt_test_$$_${RANDOM}"
counter="/tmp/${test_db}.counter"
log_prefix="/tmp/${test_db}"
created=false
cleanup() {
  docker compose exec -T backend rm -f "$counter" || true
  rm -f "${log_prefix}.refresh-a" "${log_prefix}.refresh-b" "${log_prefix}.draft-a" \
    "${log_prefix}.draft-b" "${log_prefix}.approval-a" "${log_prefix}.approval-b"
  if [[ "$created" == true ]]; then
    docker compose exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 \
      -c "DROP DATABASE \"$test_db\" WITH (FORCE)" >/dev/null
  fi
}
trap cleanup EXIT

worker() {
  local mode="$1" argument="${2:-$counter}"
  docker compose exec -T -e DB_DATABASE="$test_db" -e APP_ENV=testing backend php \
    -d zend.assertions=1 -d assert.exception=1 tests/Support/chatgpt_connection_boundary.php "$mode" "$argument"
}
wait_worker() {
  local pid="$1" output="$2" boundary="$3"
  if ! wait "$pid"; then
    echo "$boundary failed" >&2
    cat "$output" >&2
    exit 1
  fi
  cat "$output"
}
wait_pg_state() {
  local app_name="$1" event_type="$2" event="$3" deadline=$((SECONDS + 30))
  until [[ "$(docker compose exec -T postgres psql -U "$pg_user" -d "$test_db" -Atqc \
    "SELECT count(*) FROM pg_stat_activity WHERE application_name = '$app_name' AND wait_event_type = '$event_type' AND ('$event' = '' OR wait_event = '$event')")" -gt 0 ]]; do
    if (( SECONDS >= deadline )); then
      echo "approval boundary did not reach PostgreSQL state $app_name/$event_type/$event" >&2
      exit 1
    fi
    sleep 0.1
  done
}

docker compose exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 \
  -c "CREATE DATABASE \"$test_db\"" >/dev/null
created=true
docker compose run --rm --no-deps -e DB_DATABASE="$test_db" migration php artisan migrate --force >/dev/null

worker setup
worker refresh >"${log_prefix}.refresh-a" 2>&1 &
first_pid=$!
worker refresh >"${log_prefix}.refresh-b" 2>&1 &
second_pid=$!
wait_worker "$first_pid" "${log_prefix}.refresh-a" 'first concurrent token refresh'
wait_worker "$second_pid" "${log_prefix}.refresh-b" 'second concurrent token refresh'
worker verify

worker draft >"${log_prefix}.draft-a" 2>&1 &
first_pid=$!
worker draft >"${log_prefix}.draft-b" 2>&1 &
second_pid=$!
wait_worker "$first_pid" "${log_prefix}.draft-a" 'first concurrent draft save'
wait_worker "$second_pid" "${log_prefix}.draft-b" 'second concurrent draft save'
worker draft-verify

# Install the temporary delay as the migration/table owner in this disposable database.
docker compose run --rm --no-deps -e DB_DATABASE="$test_db" -e APP_ENV=testing migration \
  php tests/Support/chatgpt_connection_boundary.php approval-setup

worker approval a >"${log_prefix}.approval-a" 2>&1 &
approval_a_pid=$!
wait_pg_state cvortex-chatgpt-approval-a Timeout PgSleep
worker approval b >"${log_prefix}.approval-b" 2>&1 &
approval_b_pid=$!
wait_pg_state cvortex-chatgpt-approval-b Lock ''
wait_worker "$approval_a_pid" "${log_prefix}.approval-a" 'first PostgreSQL approval'
wait_worker "$approval_b_pid" "${log_prefix}.approval-b" 'overlapping PostgreSQL approval'
approval_a="$(grep '^APPROVAL ' "${log_prefix}.approval-a")"
approval_b="$(grep '^APPROVAL ' "${log_prefix}.approval-b")"
if [[ "$approval_a" != "$approval_b" ]]; then
  echo 'concurrent approval attempts returned different canonical results' >&2
  cat "${log_prefix}.approval-a" "${log_prefix}.approval-b" >&2
  exit 1
fi
worker approval-verify
worker approval repeat
worker approval-verify
printf '%s\n' 'Normal, repeated and overlapping PostgreSQL approval: PASS'

docker compose run --rm --no-deps -e DB_DATABASE="$test_db" migration php artisan migrate:rollback --step=3 --force >/dev/null
docker compose run --rm --no-deps -e DB_DATABASE="$test_db" migration php artisan migrate --force >/dev/null
printf '%s\n' 'ChatGPT migrations rollback/reapply: PASS'
