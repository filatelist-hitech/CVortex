#!/usr/bin/env bash
set -euo pipefail

base_url="${CVORTEX_BASE_URL:-http://localhost:18080}"
origin="${CVORTEX_ORIGIN:-http://localhost:18080}"
cookie_file="$(mktemp -t cvortex-auth-cookies.XXXXXX)"
header_file="$(mktemp -t cvortex-auth-headers.XXXXXX)"
email="m1-1-runtime-$(date +%s)@example.test"
password='a very long safe passphrase'

cleanup() {
  rm -f -- "$cookie_file" "$header_file"
}
trap cleanup EXIT

status_of() { sed -n '1s/.*STATUS://p'; }
cookie_present() { awk -v name="$1" '$6 == name {found=1} END {exit found ? 0 : 1}' "$cookie_file"; }
xsrf_header() {
  awk '$6 == "XSRF-TOKEN" {print $7}' "$cookie_file" |
    python3 -c 'import sys, urllib.parse; print(urllib.parse.unquote(sys.stdin.read().strip()))'
}
assert_status() {
  test "$1" = "$2" || { echo "${3}: expected ${2}, got ${1}" >&2; exit 1; }
}

csrf="$(curl -sS -D "$header_file" -o /dev/null -w '%{http_code}' -c "$cookie_file" \
  -H 'Accept: application/json' -H "Origin: $origin" "$base_url/sanctum/csrf-cookie")"
assert_status "$csrf" 204 csrf-cookie
cookie_present XSRF-TOKEN && cookie_present cvortex-session

token="$(docker compose exec -T backend php artisan invitation:create --expires=7 | sed -n 's#.*token=##p')"
xsrf="$(xsrf_header)"
register="$(curl -sS -o /dev/null -w '%{http_code}' -b "$cookie_file" -c "$cookie_file" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' -H "Origin: $origin" \
  -H "X-XSRF-TOKEN: $xsrf" \
  --data "{\"invitation_token\":\"$token\",\"email\":\"$email\",\"password\":\"$password\",\"password_confirmation\":\"$password\"}" \
  "$base_url/api/v1/auth/register")"
assert_status "$register" 201 register

csrf="$(curl -sS -o /dev/null -w '%{http_code}' -b "$cookie_file" -c "$cookie_file" \
  -H 'Accept: application/json' -H "Origin: $origin" "$base_url/sanctum/csrf-cookie")"
assert_status "$csrf" 204 csrf-refresh
xsrf="$(xsrf_header)"
login="$(curl -sS -o /dev/null -w '%{http_code}' -b "$cookie_file" -c "$cookie_file" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' -H "Origin: $origin" \
  -H "X-XSRF-TOKEN: $xsrf" --data "{\"email\":\"$email\",\"password\":\"$password\"}" \
  "$base_url/api/v1/auth/login")"
assert_status "$login" 200 login

me="$(curl -sS -o /dev/null -w '%{http_code}' -b "$cookie_file" -H 'Accept: application/json' -H "Origin: $origin" "$base_url/api/v1/me")"
assert_status "$me" 200 me
xsrf="$(xsrf_header)"
logout="$(curl -sS -o /dev/null -w '%{http_code}' -b "$cookie_file" -c "$cookie_file" \
  -H 'Accept: application/json' -H "Origin: $origin" -H "X-XSRF-TOKEN: $xsrf" \
  -X POST "$base_url/api/v1/auth/logout")"
assert_status "$logout" 204 logout
after="$(curl -sS -o /dev/null -w '%{http_code}' -b "$cookie_file" -H 'Accept: application/json' -H "Origin: $origin" "$base_url/api/v1/me")"
assert_status "$after" 401 me-after-logout
echo 'same-origin-auth: PASS (statuses 204/201/204/200/204/401)'
