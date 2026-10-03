---
title: M0 Runbook
status: active
owner: project
created: 2026-09-12
updated: 2026-10-03
tags: [operations, runbook, m0, health]
related:
  - "[[Local-Development]]"
  - "[[../02-Architecture/M0-Runtime|M0 Runtime]]"
---

# M0 Runbook

## Fast diagnosis

```sh
docker compose ps
health_authority="$(docker compose port nginx 80)"
curl -i "http://${health_authority}/api/v1/health/live"
curl -i "http://${health_authority}/api/v1/health/ready"
make logs SERVICE=backend
make logs SERVICE=horizon
```

Expected healthy bodies are `{"status":"live"}` and `{"status":"ready"}`. Liveness confirms the Nginx/FastCGI/Laravel request path. Readiness additionally checks PostgreSQL and Redis with short timeouts. A dependency failure returns 503 and only `{"status":"not_ready"}`.

## Common recovery

- **Port bind fails:** another process owns 8080. Set a free `CVORTEX_PORT` and matching `APP_URL` in root `.env`, then run `make up` again. Do not stop an unrelated process blindly.
- **Readiness is 503:** inspect `docker compose ps`, then PostgreSQL, Redis and backend logs. Restore the failed dependency and retry readiness; backend restart is normally unnecessary.
- **PostgreSQL is healthy but readiness is 503:** verify that `POSTGRES_DB` in the ignored root `.env` names a database present in the existing PostgreSQL volume. A Docker healthcheck confirms the configured server/database probe, not Laravel's application connection. Follow the non-destructive database-name recovery in [Local Development](Local-Development.md).
- **Horizon is not running:** check Redis health and `make logs SERVICE=horizon`; after Redis returns, confirm with `docker compose exec horizon php artisan horizon:status`.
- **Dependencies are absent/corrupt:** run `make init` again. It reuses root `.env` and restores locked container dependencies.
- **A clean service restart is needed:** use `make restart`, which preserves persistent volumes and recreates services in dependency-safe order.

## Quality and build validation

```sh
docker compose --env-file .env.example config --quiet
make lint
make test
docker compose build frontend
```

The CI workflow performs the same deterministic quality baseline. Dependency failure/recovery and named-volume persistence are runtime acceptance checks and remain manual M0 validation.

## Persistence boundaries

Normal `make down` does not remove volumes. PostgreSQL and `storage/app/private` are durable across down/up; Redis state is deliberately disposable. Never use volume-removal commands unless local data destruction is explicitly intended and approved.
