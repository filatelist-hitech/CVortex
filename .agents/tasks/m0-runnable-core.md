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

From a clean local checkout, a developer can run the documented bootstrap/start command and open a real CVortex technical shell through Nginx. Laravel, Next.js, PostgreSQL, Redis and Horizon are running, and health behavior is validated.

Business functionality is out of scope.

## Source of truth

Use:

- `/AGENTS.md`;
- `PROJECT.md`;
- current project state;
- accepted ADRs;
- Phase 06 system design;
- Phase 07 design foundation.

`legacy/phase-08-hardened-pr16.md` is requirement evidence for M0. This file owns the active, smaller M0 boundary.

Use indexes and targeted reads instead of recursively loading all docs/research.

Before pinning runtimes, packages or images, verify current official support and compatibility. Concrete versions are implementation configuration, not permanent architecture invariants.

## Reproducibility

A clean checkout must be reproducible.

Required:

- explicit supported Docker image tags; never `latest`;
- committed `composer.lock`;
- committed `package-lock.json`;
- version choices needed to reproduce the M0 stack documented where relevant;
- dependency installation through normal official ecosystem paths.

Ordinary version pins do not require ADRs by themselves.

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

Create only paths used by M0, conceptually:

```text
compose.yaml
Makefile
.env.example
apps/backend/
apps/frontend/
infra/nginx/
```

Add short scoped `AGENTS.md` under backend/frontend/infra when the root contract is insufficient for that subtree.

Do not create empty speculative `packages/` or `services/` directories. Do not add duplicate Compose/Docker layers only to anticipate production deployment.

## Local development contract

M0 uses a local-development runtime:

```text
host source → bind mounts → container runtime
```

Keep container-specific dependencies such as `vendor/` and `node_modules/` container-managed through container or named-volume storage rather than host macOS installations.

Frontend local runtime uses `next dev` behind Nginx. Backend uses PHP-FPM behind Nginx/FastCGI.

Host prerequisites are Git, Docker/Compose and Make. Do not require host PHP, Composer, Node.js, npm, PostgreSQL or Redis.

A production frontend build is mandatory validation. A production-like frontend/container runtime image is not an M0 completion requirement. Multi-stage Dockerfiles are allowed when they simplify the M0 implementation.

## Backend baseline

Create a current supported Laravel application configured for:

- PostgreSQL;
- Redis cache/queue where appropriate;
- Horizon using Redis;
- private local filesystem abstraction;
- `/api/v1` boundary;
- request correlation and safe logs;
- deterministic test/lint/static-analysis commands.

Do not create CVortex domain migrations or models.

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

Dependency failure returns `503` with sanitized output:

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

Horizon must start and connect to Redis. It is not a readiness dependency and is not exposed through Nginx.

M0 queue topology is only `default`.

## Frontend baseline

Create a current supported Next.js + React application with:

- TypeScript strict;
- ESLint and typecheck;
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

Do not add dashboard, login, fake navigation or business mock data.

## Persistence contract

Persistence is explicit:

- PostgreSQL data is persistent;
- private Laravel local storage is persistent;
- Redis state is disposable in M0.

PostgreSQL and private storage use persistent named volumes and must survive an ordinary Compose down/up cycle. Prove both with deterministic markers or equivalent observable state.

The normal `make down` path must not remove persistent volumes. Destructive volume removal is outside the normal M0 developer workflow.

Redis persistence is not an M0 acceptance requirement.

## Configuration and logging

Use one root local configuration contract:

```text
/.env.example
/.env
```

Real `.env` is gitignored. Compose passes each service only the variables it needs.

Do not introduce parallel app-local env files when the root contract is sufficient. Backend-only secrets must never enter frontend public environment variables or browser bundles.

Use a private persistent local storage volume outside the public web root. Nginx must not serve it directly.

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

`make init` must work from the documented clean-checkout prerequisites. `make down` is non-destructive by default.

## Minimal CI baseline

Add one GitHub Actions quality workflow covering at minimum:

- frontend lint;
- frontend typecheck;
- frontend tests;
- frontend production build;
- backend tests;
- backend lint/format check;
- backend static analysis configured by M0;
- Compose configuration validation.

Do not turn M0 CI into deployment infrastructure or a general orchestration system.

Runtime dependency-failure/recovery and persistence checks remain mandatory M0 validation even if they stay outside this workflow.

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
- production deployment/runtime machinery beyond build validation;
- Kubernetes, microservices, Kafka, vector DB, GraphQL, Elasticsearch, event sourcing or an observability platform.

## Security checks

Verify at minimum:

- only Nginx is host-exposed;
- loopback is the default host binding;
- no production/default secrets are committed;
- private storage is not web-public;
- Horizon/internal metadata is not exposed;
- operational endpoints do not leak debug output;
- frontend receives no backend-only secrets;
- the root configuration contract does not duplicate secrets into app-local env files.

## Validation

M0 cannot PASS by file inspection.

Actually validate:

- clean-checkout bootstrap through the documented Make workflow;
- Compose config/build/start;
- service health;
- Nginx root route;
- backend liveness/readiness;
- PostgreSQL connection;
- Redis connection;
- Horizon startup;
- frontend lint/typecheck/test/production build;
- backend tests/lint/static checks configured by M0;
- root `make` commands;
- PostgreSQL persistence across ordinary down/up;
- private-storage persistence across ordinary down/up;
- readiness failure/recovery for PostgreSQL and Redis;
- only Nginx is host-facing;
- committed lockfiles and explicit non-`latest` image tags;
- the GitHub Actions workflow matches the deterministic quality baseline above.

If the execution environment cannot run Docker, result is `PARTIAL/BLOCKED`, not PASS.

Do not report a check as executed unless it was actually run.

## Completion criteria

- [ ] real stack builds and starts;
- [ ] clean checkout initializes with only Git, Docker/Compose and Make on the host;
- [ ] source uses bind mounts with container-managed dependencies;
- [ ] browser reaches CVortex through Nginx;
- [ ] only Nginx is host-facing;
- [ ] frontend and backend are real applications;
- [ ] PostgreSQL, Redis and Horizon are operational;
- [ ] health failure/recovery behavior passes;
- [ ] PostgreSQL and private storage survive ordinary down/up;
- [ ] Redis is not treated as durable product state;
- [ ] root `.env.example` / `.env` is the single local M0 configuration contract;
- [ ] Make workflow is usable and non-destructive by default;
- [ ] frontend production build succeeds while local runtime remains `next dev`;
- [ ] backend/frontend deterministic quality checks pass;
- [ ] minimal GitHub Actions quality workflow exists;
- [ ] `composer.lock` and `package-lock.json` are committed;
- [ ] Docker images use explicit supported non-`latest` tags;
- [ ] compatibility decisions that constrain runtime, image or package selection are recorded;
- [ ] no business functionality or speculative production deployment leaked into M0;
- [ ] docs/state reflect actual implementation.

## State update

On PASS set `NEXT.md` to:

`m1-1-access-core`

On PARTIAL/BLOCKED, keep M0 as the authorized unfinished task and record only the real blocker or remaining validation.

## Final report

Keep the completion report concise:

1. Result: `PASS`, `PARTIAL` or `BLOCKED`.
2. Files created/changed.
3. Material decisions or ADR changes only.
4. Validation actually executed.
5. Real blockers/limitations only.
6. Completed task and exact next authorized task.

Do not repeat this task specification in the final report.

## STOP

Do not begin authentication or any M1 feature in the same task.
