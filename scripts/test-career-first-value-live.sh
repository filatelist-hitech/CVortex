#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

base_url="${CVORTEX_BASE_URL:-http://localhost:18080}"
origin="${CVORTEX_ORIGIN:-http://localhost:18080}"
cookie_file="$(mktemp -t cvortex-m12-smoke-cookies.XXXXXX)"
body_file="$(mktemp -t cvortex-m12-smoke-body.XXXXXX)"
email="m1-2-runtime-$(date +%s)@example.test"
password='a very long safe passphrase'
user_id=''
invitation_id=''

restore_backend() {
  docker compose up -d --force-recreate --no-deps backend horizon >/dev/null 2>&1 || true
  for _ in $(seq 1 30); do
    health_state="$(docker inspect -f '{{.State.Health.Status}}' cvortex-backend-1 2>/dev/null || true)"
    test "$health_state" = healthy && break
    sleep 1
  done
}

cleanup() {
  restore_backend
  docker rm -f cvortex-m12-openai-mock >/dev/null 2>&1 || true
  if test "${#user_id}" -eq 26; then
    docker compose exec -T postgres psql -U cvortex -d cvortex -v ON_ERROR_STOP=1 -c "
      BEGIN;
      DELETE FROM audit_events WHERE actor_user_id = '$user_id' OR subject_id IN (SELECT id FROM career_facts WHERE owner_id = '$user_id') OR subject_id = '$user_id';
      DELETE FROM claim_evidence WHERE owner_id = '$user_id';
      DELETE FROM claims WHERE owner_id = '$user_id';
      DELETE FROM career_facts WHERE owner_id = '$user_id';
      DELETE FROM llm_runs WHERE owner_id = '$user_id';
      DELETE FROM career_sources WHERE owner_id = '$user_id';
      DELETE FROM career_profiles WHERE owner_id = '$user_id';
      DELETE FROM sessions WHERE user_id = '$user_id';
      DELETE FROM users WHERE id = '$user_id';
      COMMIT;
    " >/dev/null 2>&1 || true
  fi
  if test "${#invitation_id}" -eq 26; then
    docker compose exec -T postgres psql -U cvortex -d cvortex -v ON_ERROR_STOP=1 -c "
      DELETE FROM audit_events WHERE subject_id = '$invitation_id';
      DELETE FROM invitations WHERE id = '$invitation_id';
    " >/dev/null 2>&1 || true
  fi
  rm -f "$cookie_file" "$body_file"
}
trap cleanup EXIT

docker rm -f cvortex-m12-openai-mock >/dev/null 2>&1 || true
docker run -d --name cvortex-m12-openai-mock --network cvortex_internal \
  -v "$repo_root/apps/backend/tests/Support/openai_stub.php:/stub.php:ro" \
  php:8.4.25-cli-bookworm php /stub.php >/dev/null
AI_PROVIDER=openai \
OPENAI_API_KEY=synthetic-runtime-key \
OPENAI_BASE_URL=http://cvortex-m12-openai-mock:8000/v1 \
OPENAI_CAREER_EXTRACTION_MODEL=synthetic-policy-model \
docker compose up -d --force-recreate --no-deps backend horizon >/dev/null
for _ in $(seq 1 30); do
  health_state="$(docker inspect -f '{{.State.Health.Status}}' cvortex-backend-1 2>/dev/null || true)"
  test "$health_state" = healthy && break
  sleep 1
done
test "$(docker inspect -f '{{.State.Health.Status}}' cvortex-backend-1)" = healthy
docker compose exec -T backend php -r '
$client=fsockopen("cvortex-m12-openai-mock",8000,$errorCode,$errorMessage,2);
if(!$client){exit(1);}
fwrite($client,"GET /health HTTP/1.1\r\nHost: cvortex-m12-openai-mock\r\nConnection: close\r\n\r\n");
$status=fgets($client);
fclose($client);
exit(str_contains((string)$status,"200") ? 0 : 1);
'

xsrf() {
  awk '$6 == "XSRF-TOKEN" { print $7 }' "$cookie_file" | tail -1 |
    python3 -c 'import sys, urllib.parse; print(urllib.parse.unquote(sys.stdin.read().strip()))'
}

assert_status() {
  test "$1" = "$2" || { echo "$3: expected $2, got $1" >&2; cat "$body_file" >&2; exit 1; }
}

status_code="$(curl -sS -o /dev/null -w '%{http_code}' -c "$cookie_file" \
  -H 'Accept: application/json' -H "Origin: $origin" "$base_url/sanctum/csrf-cookie")"
assert_status "$status_code" 204 csrf

invitation_output="$(docker compose exec -T backend php artisan invitation:create --expires=1)"
invitation_id="$(sed -n 's/^Invitation ULID: //p' <<<"$invitation_output")"
token="$(sed -n 's#.*token=##p' <<<"$invitation_output")"
status_code="$(curl -sS -o "$body_file" -w '%{http_code}' -b "$cookie_file" -c "$cookie_file" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' -H "Origin: $origin" \
  -H "X-XSRF-TOKEN: $(xsrf)" \
  --data "{\"invitation_token\":\"$token\",\"email\":\"$email\",\"password\":\"$password\",\"password_confirmation\":\"$password\"}" \
  "$base_url/api/v1/auth/register")"
assert_status "$status_code" 201 register
user_id="$(jq -r '.data.id' "$body_file")"

status_code="$(curl -sS -o /dev/null -w '%{http_code}' -b "$cookie_file" -c "$cookie_file" \
  -H 'Accept: application/json' -H "Origin: $origin" "$base_url/sanctum/csrf-cookie")"
assert_status "$status_code" 204 csrf-refresh
status_code="$(curl -sS -o "$body_file" -w '%{http_code}' -b "$cookie_file" -c "$cookie_file" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' -H "Origin: $origin" \
  -H "X-XSRF-TOKEN: $(xsrf)" --data "{\"email\":\"$email\",\"password\":\"$password\"}" \
  "$base_url/api/v1/auth/login")"
assert_status "$status_code" 200 login

source_text='Built a synthetic API. Familiar with PostgreSQL. Improved a synthetic workflow. Documented a synthetic system.'
status_code="$(curl -sS -o "$body_file" -w '%{http_code}' -b "$cookie_file" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' -H "Origin: $origin" \
  -H "X-XSRF-TOKEN: $(xsrf)" --data "{\"source_text\":\"$source_text\"}" \
  "$base_url/api/v1/career/extractions")"
if test "$status_code" != 202; then
  docker compose exec -T postgres psql -U cvortex -d cvortex -P pager=off \
    -c "SELECT provider, model, status, validation_result, error_category FROM llm_runs WHERE owner_id = '$user_id' ORDER BY created_at DESC LIMIT 1" >&2 || true
fi
assert_status "$status_code" 202 extraction
source_id="$(jq -r '.data.id' "$body_file")"
for _ in $(seq 1 60); do
  curl -fsS -b "$cookie_file" -H 'Accept: application/json' -H "Origin: $origin" \
    "$base_url/api/v1/career/sources/$source_id" >"$body_file"
  extraction_status="$(jq -r '.data.extraction_status' "$body_file")"
  test "$extraction_status" = COMPLETED && break
  test "$extraction_status" != FAILED || { cat "$body_file" >&2; exit 1; }
  sleep 1
done
test "$extraction_status" = COMPLETED

curl -fsS -b "$cookie_file" -H 'Accept: application/json' -H "Origin: $origin" "$base_url/api/v1/career" >"$body_file"
test "$(jq '[.data.facts[] | select(.status == "PENDING")] | length' "$body_file")" = 4
confirm_id="$(jq -r '.data.facts[] | select(.assertion_original == "Built a synthetic API.") | .id' "$body_file")"
edit_id="$(jq -r '.data.facts[] | select(.assertion_original == "Familiar with PostgreSQL.") | .id' "$body_file")"
reject_id="$(jq -r '.data.facts[] | select(.assertion_original == "Improved a synthetic workflow.") | .id' "$body_file")"

for pair in \
  "$confirm_id|{\"action\":\"confirm\"}" \
  "$edit_id|{\"action\":\"edit_confirm\",\"assertion\":\"Human-approved PostgreSQL familiarity.\"}" \
  "$reject_id|{\"action\":\"reject\"}"; do
  fact_id="${pair%%|*}"
  payload="${pair#*|}"
  status_code="$(curl -sS -o "$body_file" -w '%{http_code}' -b "$cookie_file" \
    -H 'Accept: application/json' -H 'Content-Type: application/json' -H "Origin: $origin" \
    -H "X-XSRF-TOKEN: $(xsrf)" -X PATCH --data "$payload" \
    "$base_url/api/v1/career/facts/$fact_id/review")"
  assert_status "$status_code" 200 review
done

curl -fsS -b "$cookie_file" -H 'Accept: application/json' -H "Origin: $origin" "$base_url/api/v1/career" >"$body_file"
test "$(jq '[.data.facts[] | select(.status == "PENDING")] | length' "$body_file")" = 1
test "$(jq '[.data.facts[] | select(.status == "CONFIRMED")] | length' "$body_file")" = 2
test "$(jq '[.data.facts[] | select(.status == "REJECTED")] | length' "$body_file")" = 1
test "$(jq '[.data.claims[] | select(.truth_status == "PASS")] | length' "$body_file")" = 2

curl -fsS -b "$cookie_file" -H 'Accept: application/json' -H "Origin: $origin" "$base_url/api/v1/career/sources/$source_id" >"$body_file"
test "$(jq -r '.data.run.status' "$body_file")" = COMPLETED
test "$(jq -r '.data.run.model_policy' "$body_file")" = low_cost_structured_extraction
test "$(jq -r '.data.run.provider' "$body_file")" = openai
test "$(jq -r '.data.run.model' "$body_file")" = synthetic-runtime-model

curl -fsS -b "$cookie_file" -H 'Accept: application/json' -H "Origin: $origin" "$base_url/api/v1/career/trusted" >"$body_file"
test "$(jq '.data.facts | length' "$body_file")" = 2
test "$(jq '.data.claims | length' "$body_file")" = 2

restore_backend
status_code="$(curl -sS -o "$body_file" -w '%{http_code}' -b "$cookie_file" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' -H "Origin: $origin" \
  -H "X-XSRF-TOKEN: $(xsrf)" --data '{"fact_type":"skill","assertion":"Manual fact with AI unavailable."}' \
  "$base_url/api/v1/career/facts/manual")"
assert_status "$status_code" 201 manual
test "$(jq -r '.data.provenance_type' "$body_file")" = user_manual
test "$(jq -r '.data.status' "$body_file")" = CONFIRMED

curl -fsS -b "$cookie_file" -H 'Accept: application/json' -H "Origin: $origin" "$base_url/api/v1/career/trusted" >"$body_file"
test "$(jq '.data.facts | length' "$body_file")" = 3
test "$(jq '.data.claims | length' "$body_file")" = 3

echo 'career-first-value-live: PASS (auth, extraction, evidence, confirm, edit-confirm, reject, leave-pending, trusted query, manual no-AI)'
