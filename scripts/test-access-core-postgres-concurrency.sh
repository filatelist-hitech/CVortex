#!/usr/bin/env bash
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

compose=(docker compose)
pg_user="${POSTGRES_USER:-$("${compose[@]}" exec -T postgres printenv POSTGRES_USER | tr -d '\r\n')}"
database="cvortex_access_core_concurrency_$$_${RANDOM}"
disable_database="cvortex_access_core_disable_$$_${RANDOM}"
password='a very long safe passphrase'
output_one="$(mktemp -t cvortex-bootstrap-one.XXXXXX)"
output_two="$(mktemp -t cvortex-bootstrap-two.XXXXXX)"
register_one="$(mktemp -t cvortex-register-one.XXXXXX)"
register_two="$(mktemp -t cvortex-register-two.XXXXXX)"
disable_one="$(mktemp -t cvortex-disable-one.XXXXXX)"
disable_two="$(mktemp -t cvortex-disable-two.XXXXXX)"

cleanup() {
  rm -f "$output_one" "$output_two" "$register_one" "$register_two" "$disable_one" "$disable_two"
  docker compose exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 -c "DROP DATABASE IF EXISTS \"$disable_database\" WITH (FORCE)" >/dev/null 2>&1 || true
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

docker compose exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 -c "CREATE DATABASE \"$disable_database\"" >/dev/null
docker compose exec -T -e DB_DATABASE="$disable_database" backend php artisan migrate --force >/dev/null
disable_ids=$(docker compose exec -T -e DB_DATABASE="$disable_database" backend php tests/Support/prepare_disable_concurrency.php)
disable_id_one=$(sed -n '1p' <<<"$disable_ids")
disable_id_two=$(sed -n '2p' <<<"$disable_ids")

set +e
(docker compose exec -T -e DB_DATABASE="$disable_database" backend php artisan user:disable "$disable_id_one") >"$disable_one" 2>&1 &
disable_pid_one=$!
(docker compose exec -T -e DB_DATABASE="$disable_database" backend php artisan user:disable "$disable_id_two") >"$disable_two" 2>&1 &
disable_pid_two=$!
wait "$disable_pid_one"
disable_status_one=$?
wait "$disable_pid_two"
disable_status_two=$?
set -e

disable_successes=$( (grep -l -F 'User disabled.' "$disable_one" "$disable_two" || true) | wc -l | tr -d ' ')
disable_rejections=$( (grep -l -F 'sole active admin cannot be disabled' "$disable_one" "$disable_two" || true) | wc -l | tr -d ' ')
active_admins=$(docker compose exec -T -e DB_DATABASE="$disable_database" postgres psql -U "$pg_user" -d "$disable_database" -Atqc "SELECT count(*) FROM users WHERE role = 'admin' AND status = 'ACTIVE'")
disabled_admins=$(docker compose exec -T -e DB_DATABASE="$disable_database" postgres psql -U "$pg_user" -d "$disable_database" -Atqc "SELECT count(*) FROM users WHERE role = 'admin' AND status = 'DISABLED'")
disable_audits=$(docker compose exec -T -e DB_DATABASE="$disable_database" postgres psql -U "$pg_user" -d "$disable_database" -Atqc "SELECT count(*) FROM audit_events WHERE event_type = 'user.disabled'")
remaining_sessions=$(docker compose exec -T -e DB_DATABASE="$disable_database" postgres psql -U "$pg_user" -d "$disable_database" -Atqc "SELECT count(*) FROM sessions")

if test "$disable_successes" -ne 1 || test "$disable_rejections" -ne 1 || test "$active_admins" -ne 1 || test "$disabled_admins" -ne 1 || test "$disable_audits" -ne 1 || test "$remaining_sessions" -ne 1 || test "$disable_status_one" -eq "$disable_status_two"; then
  echo "admin disable concurrency failed: statuses=$disable_status_one/$disable_status_two successes=$disable_successes rejections=$disable_rejections active=$active_admins disabled=$disabled_admins audits=$disable_audits sessions=$remaining_sessions" >&2
  cat "$disable_one" "$disable_two" >&2
  exit 1
fi

rejected_id=$disable_id_one
if grep -q -F 'User disabled.' "$disable_one"; then
  rejected_id=$disable_id_two
fi
rejected_sessions=$(docker compose exec -T -e DB_DATABASE="$disable_database" postgres psql -U "$pg_user" -d "$disable_database" -Atqc "SELECT count(*) FROM sessions WHERE user_id = '$rejected_id'")
rejected_audits=$(docker compose exec -T -e DB_DATABASE="$disable_database" postgres psql -U "$pg_user" -d "$disable_database" -Atqc "SELECT count(*) FROM audit_events WHERE subject_id = '$rejected_id'")
rejected_status=$(docker compose exec -T -e DB_DATABASE="$disable_database" postgres psql -U "$pg_user" -d "$disable_database" -Atqc "SELECT status FROM users WHERE id = '$rejected_id'")
if test "$rejected_sessions" -ne 1 || test "$rejected_audits" -ne 0 || test "$rejected_status" != 'ACTIVE'; then
  echo "rejected disable mutated state: status=$rejected_status sessions=$rejected_sessions audits=$rejected_audits" >&2
  exit 1
fi

echo 'access-core-postgres-concurrency: PASS (bootstrap, invitation registration, concurrent admin disable)'
