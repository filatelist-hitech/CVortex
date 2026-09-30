#!/usr/bin/env bash
set -euo pipefail

base_url="${CVORTEX_BASE_URL:-http://127.0.0.1:${CVORTEX_PORT:-8080}}"
temp_dir="$(mktemp -d)"
trap 'rm -rf "$temp_dir"' EXIT
headers_file="$temp_dir/headers"
logs_file="$temp_dir/nginx.log"
canary="NGINX_QUERY_SECRET_CANARY_$(date +%s)_$$"

status="$(curl -sS --get --data-urlencode "api_key=$canary" \
    -D "$headers_file" -o /dev/null -w '%{http_code}' \
    "$base_url/api/v1/health/live")"
request_id="$(awk 'tolower($1) == "x-request-id:" { gsub("\r", "", $2); print $2; exit }' "$headers_file")"

[[ "$status" == "200" ]] || {
    printf 'nginx-access-log-safety: expected HTTP 200, got %s\n' "$status" >&2
    exit 1
}
[[ -n "$request_id" ]] || {
    printf 'nginx-access-log-safety: response did not include X-Request-ID\n' >&2
    exit 1
}

docker compose logs --no-color --no-log-prefix --since 2m nginx > "$logs_file" 2>&1
if grep -Fq -- "$canary" "$logs_file"; then
    printf 'nginx-access-log-safety: query canary found in Nginx logs\n' >&2
    exit 1
fi
grep -Fq -- '"method":"GET"' "$logs_file"
grep -Fq -- '"path":"/api/v1/health/live"' "$logs_file"
grep -Fq -- "\"status\":$status" "$logs_file"
grep -Fq -- "\"request_id\":\"$request_id\"" "$logs_file"

printf 'nginx-access-log-safety: PASS status=%s request_id=%s\n' "$status" "$request_id"
