---
title: M0 Runtime
status: completed
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, m0, docker, runtime]
related:
  - "[[Architecture-Baseline]]"
  - "[[Phase-06-System-Design]]"
  - "[[../10-Operations/Local-Development|Local Development]]"
  - "[[../10-Operations/M0-Runbook|M0 Runbook]]"
---

# M0 Runtime

## Boundary

M0 is a local-development runtime and technical shell. It implements no CVortex business model, authentication, runtime AI, document rendering or production deployment machinery.

```text
Browser → 127.0.0.1:${CVORTEX_PORT:-8080} → Nginx
                                            ├─ /         → Next.js dev server
                                            └─ /api/v1/* → Laravel / PHP-FPM

Laravel + Horizon → Redis
Laravel           → PostgreSQL
```

Only Nginx publishes a host port, and the default binding is loopback-only. The `edge` network contains Nginx, frontend and backend. The Docker-internal `internal` network contains backend, Horizon, PostgreSQL and Redis. Backend and Horizon share one image and use distinct commands.

## Runtime and dependency baseline

The selection was verified against upstream support information on 2026-09-12. Exact application dependency resolution remains authoritative in `composer.lock` and `package-lock.json`.

| Layer | M0 selection | Constraint/evidence |
|---|---|---|
| PHP-FPM | `php:8.4.25-fpm-bookworm` | Laravel 13 supports PHP 8.3–8.5 ([Laravel release policy](https://laravel.com/docs/13.x/releases)) |
| Laravel | `13.31.0` locked | Current Laravel 13 line |
| Horizon | `5.49.0` locked | Redis-backed queue supervisor; M0 uses only `default` ([Horizon documentation](https://laravel.com/docs/13.x/horizon)) |
| phpredis | `6.3.0` | Stable PECL release ([PECL redis](https://pecl.php.net/package/redis)) |
| Node.js | `node:24.21.0-bookworm-slim` | Node 24 LTS ([Node.js releases](https://nodejs.org/en/about/previous-releases)) |
| Next.js / React | `16.3.5` / `19.2.8` locked | Next.js 16 requires Node 20.9+ and TypeScript 5.1+ ([installation](https://nextjs.org/docs/app/getting-started/installation)) |
| PostgreSQL | `postgres:18.6-bookworm` | Supported PostgreSQL 18 line ([versioning policy](https://www.postgresql.org/support/versioning/)) |
| Redis | `redis:8.8.2-alpine3.23` | Explicit official-image tag; persistence deliberately disabled for M0 |
| Nginx | `nginx:1.31.5-alpine3.24` | Explicit mainline tag ([Nginx downloads](https://nginx.org/en/download.html)) |

Composer is supplied by `composer:2.8.12`. Frontend quality tooling is locked by npm, including TypeScript 5.9.3, ESLint 10.10.0 and Vitest 5.0.0. ESLint 10 is the current supported line; v9 reached end of life before this baseline ([ESLint version support](https://eslint.org/version-support/)). Next's granular flat-config plugin form avoids the legacy plugin compatibility boundary and follows its documented configuration path ([Next.js ESLint configuration](https://nextjs.org/docs/app/api-reference/config/eslint)). Concrete versions are reproducibility configuration, not permanent architecture decisions.

## Configuration, persistence and exposure

- Root `.env.example` is the template; ignored root `.env` is the only local secret-bearing configuration file.
- `make init` creates `.env` once and replaces placeholder values with a random PostgreSQL password and Laravel application key. It never overwrites an existing `.env`.
- Host source is bind-mounted. `vendor`, `node_modules`, Next build state, PostgreSQL data and private Laravel storage are container/named-volume managed.
- `postgres_data` and `private_storage` survive normal `make down` / `make up`. Redis is disposable cache/queue state and has persistence disabled.
- Private storage is mounted at `storage/app/private`, outside Laravel's public directory, and has no Nginx route.
- Nginx does not expose Horizon. PHP and Next.js powered-by headers are disabled; framework health responses contain only the documented status and `X-Request-ID`.

## Health and correlation

`GET /api/v1/health/live` validates only the HTTP/application path. `GET /api/v1/health/ready` performs bounded PostgreSQL and Redis probes. Dependency failure returns HTTP 503 with `{"status":"not_ready"}`; recovery is detected on a later request without restarting the application.

Laravel reuses a syntactically safe incoming `X-Request-ID`, otherwise generates a UUID. It shares that identifier with application logging context and returns it in the response header. No tracing platform is introduced by M0.

## Decision record

M0 applies the accepted architecture and introduces no new irreversible or architecture-significant decision. Therefore no ADR was added or changed.
