#!/usr/bin/env bash
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

compose=(docker compose)
pg_user="${POSTGRES_USER:-$("${compose[@]}" exec -T postgres printenv POSTGRES_USER | tr -d '\r\n')}"
database="cvortex_access_core_concurrency_$$_${RANDOM}"
password='a very long safe passphrase'
output_one="$(mktemp -t cvortex-bootstrap-one.XXXXXX)"
output_two="$(mktemp -t cvortex-bootstrap-two.XXXXXX)"
register_one="$(mktemp -t cvortex-register-one.XXXXXX)"
register_two="$(mktemp -t cvortex-register-two.XXXXXX)"

cleanup() {
  rm -f "$output_one" "$output_two" "$register_one" "$register_two"
  "${compose[@]}" exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 -c "DROP DATABASE IF EXISTS \"$database\" WITH (FORCE)" >/dev/null 2>&1 || true
}
trap cleanup EXIT

"${compose[@]}" exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 -c "CREATE DATABASE \"$database\"" >/dev/null
"${compose[@]}" exec -T -e DB_DATABASE="$database" backend php artisan migrate --force >/dev/null

set +e
(printf '%s\n' "$password" | "${compose[@]}" exec -T -e DB_DATABASE="$database" backend php artisan user:bootstrap-admin first-admin@example.test) >"$output_one" 2>&1 &
pid_one=$!
(printf '%s\n' "$password" | "${compose[@]}" exec -T -e DB_DATABASE="$database" backend php artisan user:bootstrap-admin second-admin@example.test) >"$output_two" 2>&1 &
pid_two=$!
wait "$pid_one"
status_one=$?
wait "$pid_two"
status_two=$?
set -e

successes=$( (grep -l -F 'First admin created.' "$output_one" "$output_two" || true) | wc -l | tr -d ' ' )
rejections=$( (grep -l -F 'An admin account already exists.' "$output_one" "$output_two" || true) | wc -l | tr -d ' ' )
admins=$("${compose[@]}" exec -T -e DB_DATABASE="$database" postgres psql -U "$pg_user" -d "$database" -Atqc "SELECT count(*) FROM users WHERE role = 'admin'")

if test "$successes" -ne 1 || test "$rejections" -ne 1 || test "$admins" -ne 1 || test "$status_one" -eq "$status_two"; then
  echo "bootstrap concurrency failed: statuses=$status_one/$status_two successes=$successes rejections=$rejections admins=$admins" >&2
  grep -E 'First admin created|An admin account already exists|ERROR|Exception' "$output_one" "$output_two" >&2 || true
  exit 1
fi

invitation_output=$("${compose[@]}" exec -T -e DB_DATABASE="$database" backend php artisan invitation:create --expires=7)
token=$(sed -n 's#.*token=##p' <<<"$invitation_output")
test "${#token}" -eq 64

set +e
(printf '%s\n' "$token" | "${compose[@]}" exec -T -e DB_DATABASE="$database" backend php tests/Support/register_invitation.php first-user@example.test) >"$register_one" 2>&1 &
register_pid_one=$!
(printf '%s\n' "$token" | "${compose[@]}" exec -T -e DB_DATABASE="$database" backend php tests/Support/register_invitation.php second-user@example.test) >"$register_two" 2>&1 &
register_pid_two=$!
wait "$register_pid_one"
register_status_one=$?
wait "$register_pid_two"
register_status_two=$?
set -e

registered=$( (grep -l -F 'registered' "$register_one" "$register_two" || true) | wc -l | tr -d ' ' )
failed=$( (grep -l -F 'Illuminate\Validation\ValidationException' "$register_one" "$register_two" || true) | wc -l | tr -d ' ' )
users=$("${compose[@]}" exec -T -e DB_DATABASE="$database" postgres psql -U "$pg_user" -d "$database" -Atqc "SELECT count(*) FROM users WHERE role = 'user'")
uses=$("${compose[@]}" exec -T -e DB_DATABASE="$database" postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT uses FROM invitations')

if test "$registered" -ne 1 || test "$failed" -ne 1 || test "$users" -ne 1 || test "$uses" -ne 1 || test "$register_status_one" -eq "$register_status_two"; then
  echo "invitation concurrency failed: statuses=$register_status_one/$register_status_two registered=$registered failed=$failed users=$users uses=$uses" >&2
  grep -E 'registered|ValidationException|ERROR|Exception' "$register_one" "$register_two" >&2 || true
  exit 1
fi

echo 'access-core-postgres-concurrency: PASS (one bootstrap admin, one invitation registration)'
