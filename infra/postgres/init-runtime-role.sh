#!/usr/bin/env bash
set -euo pipefail

runtime_user="${POSTGRES_RUNTIME_USER:-cvortex_app}"
runtime_password="${POSTGRES_RUNTIME_PASSWORD:-${POSTGRES_PASSWORD:-}}"

if [[ ! "$runtime_user" =~ ^[a-z_][a-z0-9_]*$ ]]; then
  echo 'POSTGRES_RUNTIME_USER must be a simple PostgreSQL role name' >&2
  exit 1
fi
if [[ -z "$runtime_password" ]]; then
  echo 'POSTGRES_RUNTIME_PASSWORD must not be empty' >&2
  exit 1
fi

psql --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" --set=ON_ERROR_STOP=1 \
  --set=runtime_user="$runtime_user" --set=runtime_password="$runtime_password" <<'SQL'
SELECT format(
  'CREATE ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS PASSWORD %L',
  :'runtime_user', :'runtime_password'
)
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'runtime_user') \gexec

SELECT format(
  'ALTER ROLE %I WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS PASSWORD %L',
  :'runtime_user', :'runtime_password'
) \gexec
SQL
