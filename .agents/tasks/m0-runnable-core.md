---
title: CVortex M0 — Runnable Core
status: ready
milestone: m0-runnable-core
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [task, m0, bootstrap, backend, frontend, infra]
related:
  - ../../PROJECT.md
  - ../../docs/01-Product/Roadmap.md
  - ../state/STATUS.md
  - legacy/phase-08-hardened-pr16.md
---

# CVortex M0 — Runnable Core

## Observable outcome

From a clean local checkout, a developer can run the documented bootstrap/start command and open a real CVortex technical shell in the browser through Nginx. Laravel, Next.js, PostgreSQL, Redis and Horizon are genuinely running and health behavior is validated.

This is the first implementation milestone. It does not implement business functionality.

## Source of truth

Use `/AGENTS.md`, `PROJECT.md`, current state, accepted ADRs, Phase 06 system design and Phase 07 design foundation.

The final hardened Phase 08 spec preserved at `legacy/phase-08-hardened-pr16.md` is requirement evidence for M0. This active spec owns the smaller milestone boundary.

Before pinning runtimes/packages/images, verify current official support/compatibility. No `latest` image tags.

## Fixed M0 topology

Single local origin:

```text
http://localhost:${CVORTEX_PORT:-8080}
```

Default host binding is loopback-only.

```text
Browser
  ↓
Nginx
  ├── /         → Next.js
  └── /api/v1/* → Laravel/PHP-FPM
```

Compose services:

```text
nginx
frontend
backend
horizon
postgres
redis
```

Only Nginx exposes a host-facing port.

Networks:

```text
edge: nginx, frontend, backend
internal: backend, horizon, postgres, redis
```

PostgreSQL, Redis and Horizon are not host-exposed. Do not use `container_name` or hardcoded container IPs.

Backend and Horizon use the same backend image with different runtime commands. Laravel runs behind PHP-FPM, not `artisan serve`.

## Required repository shape

Create only immediately used paths, conceptually:

```text
compose.yaml
Makefile
.env.example
apps/backend/
apps/frontend/
infra/nginx/
```

Add short scoped `AGENTS.md` under backend/frontend/infra where useful.

Do not create empty speculative `packages/` or `services/` directories.

## Backend baseline

Create current supported Laravel application configured for:

- PostgreSQL;
- Redis cache/queue where appropriate;
- Horizon using Redis;
- private local filesystem abstraction;
- `/api/v1` boundary;
- request correlation/safe logs;
- deterministic test/lint commands.

No CVortex domain migrations/models.

Health endpoints:

```text
GET /api/v1/health/live
GET /api/v1/health/ready
```

Liveness checks only the application process/request path.

Success:

```json
{"status":"live"}
```

Readiness checks PostgreSQL and Redis with bounded probes.

Success:

```json
{"status":"ready"}
```

Dependency failure returns `503` with sanitized:

```json
{"status":"not_ready"}
```

Required behavior:

```text
PostgreSQL down → live 200, ready 503
Redis down      → live 200, ready 503
restored        → ready 200 without unnecessary app restart
```

Health output must not leak DSNs, hosts, versions, stack traces or credentials.

Horizon must start and connect to Redis, but is not a readiness dependency and is not exposed through Nginx.

M0 queue topology is just `default`.

## Frontend baseline

Create current supported Next.js + React application with:

- TypeScript strict;
- ESLint/typecheck;
- Vitest + React Testing Library baseline;
- at least one meaningful test;
- Phase 07 tokens consumed in a maintainable minimal way;
- same-origin API base `/api/v1`;
- no backend/internal service names in browser code.

Root route is a branded technical shell only:

```text
CVortex
Your career, in context.
Technical baseline is running.
```

No dashboard, login, fake navigation or business mock data.

## Storage/config/logging

Use a private persistent local storage volume outside public web root. It must survive ordinary `docker compose down` and must not be directly served by Nginx.

Root `.env.example` documents only required M0 settings. Real `.env` is gitignored. Backend secrets never enter frontend public environment variables.

Implement `X-Request-ID` reuse/generation with safe validation, response header and application-log correlation. Do not add a tracing/monitoring platform.

## Developer interface

Provide stable root commands:

```text
make init
make up
make down
make restart
make test
make lint
make logs
make shell
make migrate
```

Host prerequisites are only Git, Docker/Compose and Make. PHP/Composer/Node/npm/PostgreSQL/Redis are container/build concerns.

`make down` does not destroy persistent data by default.

## Non-goals

Do not implement:

- auth/invitations;
- Career/Vacancy/Application;
- runtime LLM integration;
- provider credentials;
- document rendering/LibreOffice;
- job-board adapters;
- domain migrations;
- dashboard/business screens;
- PWA polish that requires speculative packages;
- Kubernetes/microservices/Kafka/vector DB/GraphQL/Elasticsearch/event sourcing/observability platform.

## Security checks

At minimum verify:

- only Nginx is host-exposed;
- loopback is default host binding;
- no production/default secrets are committed;
- private storage is not web-public;
- Horizon/internal metadata is not exposed;
- debug output does not leak through operational endpoints;
- frontend receives no backend-only secrets;
- dependency installation uses normal official ecosystem paths.

## Validation

M0 cannot PASS by file inspection.

Actually validate:

- Compose config/build/start;
- service health;
- Nginx root route;
- backend liveness/readiness;
- PostgreSQL connection;
- Redis connection;
- Horizon startup;
- frontend build/type/lint/test;
- backend tests/lint/static checks actually configured;
- `make` central commands;
- persistence across restart/down-up;
- readiness failure/recovery for PostgreSQL and Redis.

If the execution environment cannot run Docker, result is `PARTIAL/BLOCKED`, not invented PASS.

## Completion criteria

- [ ] real stack builds and starts;
- [ ] browser reaches CVortex through Nginx;
- [ ] only Nginx is host-facing;
- [ ] frontend and backend are both real applications;
- [ ] PostgreSQL/Redis/Horizon are operational;
- [ ] health failure/recovery behavior passes;
- [ ] private storage persists;
- [ ] Make workflow is usable;
- [ ] current-version compatibility decisions are recorded where needed;
- [ ] no business functionality leaked into M0;
- [ ] docs/state reflect actual implementation.

## State update

On PASS set `NEXT.md` to:

`m1-1-access-core`

## STOP

Do not begin authentication or any M1 feature in the same task.
