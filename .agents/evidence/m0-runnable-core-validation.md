---
title: M0 Runnable Core Validation Evidence
status: complete
created: 2026-09-12
updated: 2026-09-12
tags: [m0, validation, evidence, docker]
related:
  - "[[../tasks/m0-runnable-core|M0 Runnable Core]]"
  - "[[../state/STATUS|Project State]]"
---

# M0 Runnable Core Validation Evidence

This artifact records the reproducible M0 validation run for commit `e6af442` on
the clean `feature/m0-runnable-core` checkout. Commands were run on 2026-09-12
with Docker Server `29.6.2`. Secrets from the local `.env` are intentionally not
copied into this report.

## Bootstrap and configuration

| Command | Result |
|---|---|
| `git status --short --branch` | exit 0; no worktree changes |
| `make init` | exit 0; Compose images built, locked Composer/npm dependencies installed |
| `docker compose --env-file .env.example config --quiet` | exit 0 |
| `bash scripts/test-agent-contract.sh` | exit 0 |
| lockfile check for `apps/backend/composer.lock` and `apps/frontend/package-lock.json` | exit 0; both present |
| check for untagged/`:latest` service images in `compose.yaml` | exit 0; none found |

## Runtime and dependency behavior

| Command or scenario | Observed result |
|---|---|
| `curl http://127.0.0.1:<CVORTEX_PORT>/api/v1/health/live` | HTTP 200, `{"status":"live"}` |
| `curl http://127.0.0.1:<CVORTEX_PORT>/api/v1/health/ready` | HTTP 200, `{"status":"ready"}` |
| `curl http://127.0.0.1:<CVORTEX_PORT>/` | HTTP 200, HTML response |
| stop PostgreSQL, then readiness request | HTTP 503 |
| start PostgreSQL, then readiness polling | HTTP 200, `{"status":"ready"}` |
| stop Redis, then readiness request | HTTP 503 |
| start Redis, then readiness polling | HTTP 200, `{"status":"ready"}` |
| `docker compose ps` after recovery | backend/frontend/postgres/redis healthy; Horizon running; Nginx healthy |

## Persistence and exposure

The following markers were created only for this validation and removed after
the checks:

1. A PostgreSQL row in `m0_validation_marker` survived ordinary
   `docker compose down` followed by `docker compose up -d`.
2. A file in `/var/www/html/storage/app/private` survived the same cycle.
3. `docker compose ps --format '{{.Service}}|{{.Ports}}'` showed a host binding
   only for Nginx (`127.0.0.1:<CVORTEX_PORT>->80/tcp`); backend, Horizon,
   PostgreSQL and Redis had container-only ports.

## Deterministic quality checks

| Command | Result |
|---|---|
| `docker compose run --rm --no-deps frontend npm run lint` | exit 0 |
| `docker compose run --rm --no-deps frontend npm run typecheck` | exit 0 |
| `docker compose run --rm --no-deps frontend npm test` | exit 0; 1 file, 1 test passed |
| `docker compose build frontend` | exit 0; production Next.js build completed |
| `docker compose run --rm --no-deps backend composer test` | exit 0; 5 tests, 13 assertions passed |
| `docker compose run --rm --no-deps backend composer lint` | exit 0; Pint passed 24 files |
| `docker compose run --rm --no-deps backend composer analyse` | exit 0; PHPStan reported no errors |
| `make lint` | exit 0 |
| `make test` | exit 0 |

The evidence supports the M0 `PASS` claim. No volume-removal command was used.
