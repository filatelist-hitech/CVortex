#!/usr/bin/env bash
set -euo pipefail

# Inspect only Compose structure. This deliberately never prints credential values.
runtime_config=$(docker compose --env-file .env.example config --format json)
migration_config=$(docker compose --env-file .env.example --profile tools config --format json)

for service in backend horizon; do
  jq -e --arg service "$service" '
    .services[$service].environment as $env
    | $env.DB_USERNAME == "cvortex_app"
    and $env.DB_RUNTIME_ROLE == "cvortex_app"
    and ($env | has("DB_ADMIN_USERNAME") | not)
    and ($env | has("DB_ADMIN_PASSWORD") | not)
  ' <<<"$runtime_config" >/dev/null
done

jq -e '
  .services.migration.environment as $env
  | $env.DB_USERNAME == "cvortex"
  and $env.DB_RUNTIME_ROLE == "cvortex_app"
' <<<"$migration_config" >/dev/null

echo 'runtime-db-credentials: backend/horizon use cvortex_app; migration is separate'
