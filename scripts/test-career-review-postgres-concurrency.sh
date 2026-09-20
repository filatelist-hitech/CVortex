#!/usr/bin/env bash
set -euo pipefail

root="$(git rev-parse --show-toplevel)"
cd "$root"
compose=(docker compose)
pg_user="${POSTGRES_USER:-$("${compose[@]}" exec -T postgres printenv POSTGRES_USER | tr -d '\r\n')}"
database="cvortex_career_review_$$_${RANDOM}"
temp_dir="$(mktemp -d -t cvortex-career-review.XXXXXX)"
output_one="$temp_dir/one"
output_two="$temp_dir/two"

cleanup() {
  rm -rf "$temp_dir"
  "${compose[@]}" exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 \
    -c "DROP DATABASE IF EXISTS \"$database\" WITH (FORCE)" >/dev/null 2>&1 || true
}
trap cleanup EXIT

"${compose[@]}" exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 \
  -c "CREATE DATABASE \"$database\"" >/dev/null
"${compose[@]}" exec -T -e DB_DATABASE="$database" backend php artisan migrate --database=pgsql_admin --force >/dev/null
ids=$("${compose[@]}" exec -T -e DB_DATABASE="$database" backend php tests/Support/prepare_career_review_concurrency.php)
owner_id=$(sed -n '1p' <<<"$ids")
fact_id=$(sed -n '2p' <<<"$ids")
test -n "$owner_id" && test -n "$fact_id"

"${compose[@]}" exec -T -e DB_DATABASE="$database" postgres psql -U "$pg_user" -d "$database" -v ON_ERROR_STOP=1 <<'SQL' >/dev/null
CREATE TABLE career_review_test_barrier (worker_id varchar(16) PRIMARY KEY);
CREATE FUNCTION pause_career_review_update() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF OLD.status = 'PENDING' AND NEW.status <> 'PENDING' THEN PERFORM pg_sleep(2); END IF;
  RETURN NEW;
END $$;
CREATE TRIGGER pause_career_review_update BEFORE UPDATE ON career_facts
FOR EACH ROW EXECUTE FUNCTION pause_career_review_update();
SQL

set +e
("${compose[@]}" exec -T -e DB_DATABASE="$database" backend php tests/Support/career_review_worker.php \
  "$owner_id" "$fact_id" confirm one) >"$output_one" 2>&1 &
pid_one=$!
("${compose[@]}" exec -T -e DB_DATABASE="$database" backend php tests/Support/career_review_worker.php \
  "$owner_id" "$fact_id" reject two) >"$output_two" 2>&1 &
pid_two=$!
deadline=$((SECONDS + 30))
while :; do
  ready_count=$("${compose[@]}" exec -T -e DB_DATABASE="$database" postgres psql -U "$pg_user" -d "$database" -Atqc \
    'SELECT count(*) FROM career_review_test_barrier')
  [[ "$ready_count" == 2 ]] && break
  if (( SECONDS > deadline )); then
    echo 'career review workers did not reach the start barrier' >&2
    cat "$output_one" "$output_two" >&2
    exit 1
  fi
  sleep 0.05
done
wait "$pid_one"; status_one=$?
wait "$pid_two"; status_two=$?
set -e

applied=$(grep -h '^applied ' "$output_one" "$output_two" | wc -l | tr -d ' ')
conflicts=$(grep -h '^conflict ' "$output_one" "$output_two" | wc -l | tr -d ' ')
if [[ "$applied" != 1 || "$conflicts" != 1 ]]; then
  echo "career review race failed: statuses=$status_one/$status_two applied=$applied conflicts=$conflicts" >&2
  cat "$output_one" "$output_two" >&2
  exit 1
fi

"${compose[@]}" exec -T -e DB_DATABASE="$database" postgres psql -U "$pg_user" -d "$database" -v ON_ERROR_STOP=1 \
  -c 'DROP TRIGGER pause_career_review_update ON career_facts; DROP FUNCTION pause_career_review_update()' >/dev/null
"${compose[@]}" exec -T -e DB_DATABASE="$database" backend php tests/Support/verify_career_review_concurrency.php "$owner_id" "$fact_id"
echo 'career-review-postgres-concurrency: PASS (one transition wins, competing transition rejected, one valid Claim)'
