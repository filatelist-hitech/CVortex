---
title: CVortex Phase 08 — Repository Bootstrap + M0
status: ready
phase: 08-repository-bootstrap
owner: project
created: 2026-09-12
updated: 2026-09-12
tags:
  - task
  - bootstrap
  - backend
  - frontend
  - infra
  - m0
related:
  - ../../PROJECT.md
  - ../state/STATUS.md
  - ../../docs/03-ADR/INDEX.md
---

# CVortex — Phase 08: Repository Bootstrap + M0

## Goal

Создать первый реально запускаемый technical baseline CVortex:

- minimal monorepo;
- Laravel backend;
- Next.js frontend;
- PostgreSQL;
- Redis + Laravel Horizon;
- Nginx;
- Docker Compose;
- private persistent local storage;
- deterministic developer workflow;
- liveness/readiness contracts;
- minimal logging/security baseline;
- фактически выполненную M0 validation.

Phase 08 разрешает technical implementation, но не business features.

M0 готов только если stack собирается, запускается и проходит обязательные acceptance checks.

# Source of truth

Перед работой используй:

1. `/AGENTS.md`;
2. `/PROJECT.md`;
3. `.agents/state/STATUS.md`;
4. `.agents/state/NEXT.md`;
5. `.agents/state/BLOCKERS.md`;
6. applicable scoped `AGENTS.md`;
7. accepted ADR по затронутым решениям;
8. Phase 06 architecture/deployment/API boundaries;
9. Phase 07 design foundation только в части frontend bootstrap/tokens;
10. relevant research через существующие indexes.

Не перечитывай весь documentation/research corpus.

Instruction precedence:

```text
/AGENTS.md
→ applicable scoped AGENTS.md
→ accepted ADR
→ PROJECT.md / accepted design documents
→ this task spec
→ implementation details
```

Если task spec конфликтует с accepted ADR:

- не менять решение молча;
- зафиксировать конфликт;
- определить authoritative source;
- при реальном architecture change использовать ADR amendment/supersession flow.

# Execution policy

Default:

- one agent;
- sequential execution;
- task-relevant context only;
- deterministic before AI;
- narrow validation while iterating;
- full M0 validation before completion;
- no subagents unless correctness genuinely requires them.

Research нужен только для решений, зависящих от текущего ecosystem.

Перед фиксацией versions проверь official support/compatibility data для:

- PHP;
- Laravel;
- Laravel Horizon;
- PostgreSQL;
- Redis;
- Node.js;
- Next.js;
- React;
- TypeScript;
- frontend testing packages;
- Docker images.

Rules:

- official sources first;
- explicit supported version tags;
- no Docker `latest`;
- `composer.lock`;
- `package-lock.json`;
- concrete versions являются implementation/configuration, а не вечными architecture invariants.

# Scope

Создай минимальную repository structure:

```text
/
├── compose.yaml
├── Makefile
├── .env.example
│
├── apps/
│   ├── backend/
│   │   ├── AGENTS.md
│   │   └── Dockerfile
│   └── frontend/
│       ├── AGENTS.md
│       └── Dockerfile
│
├── infra/
│   ├── AGENTS.md
│   └── nginx/
│       └── nginx.conf
│
├── docs/
├── research/
└── brand/
```

Не создавай пустые `packages/` или `services/`, если они не нужны Phase 08 прямо сейчас.

Не создавай дополнительные Docker/Compose layers без необходимости:

```text
Dockerfile.dev
Dockerfile.prod
docker-compose.override.yml
```

и аналогичные дублирующие конструкции.

# Non-goals

На Phase 08 НЕ реализовывать:

- authentication;
- invitations;
- admin/user behavior;
- Career domain;
- Vacancy domain;
- Application domain;
- Employer Memory;
- resume import/generation;
- cover letters;
- document workflows;
- LibreOffice runtime;
- runtime AI/LLM integration;
- OpenAI/provider credentials;
- job-board integrations;
- domain migrations;
- browser extension;
- product dashboard;
- native mobile;
- speculative abstractions;
- Phase 09 functionality.

Также без доказанной необходимости не добавлять:

- Kubernetes;
- microservices;
- Kafka;
- standalone vector DB;
- GraphQL;
- Elasticsearch;
- event sourcing;
- service mesh;
- monitoring stack;
- OpenTelemetry stack;
- browser E2E framework;
- Storybook;
- speculative shared packages.

# Runtime contract

## Web entry point

M0 использует single-origin architecture.

Default URL:

```text
http://localhost:8080
```

Port configurable:

```text
CVORTEX_PORT=8080
```

Host binding:

```text
127.0.0.1:${CVORTEX_PORT:-8080}:80
```

Не публиковать M0 на `0.0.0.0` по умолчанию.

Routing:

```text
Browser
   │
   ▼
Nginx
   ├── /             → Next.js
   └── /api/v1/*     → Laravel / PHP-FPM
```

Только Nginx имеет host-facing port.

PostgreSQL, Redis, PHP-FPM и Horizon доступны только внутри Compose networks.

## Networks

Использовать:

```text
edge
internal
```

Membership:

```text
edge
├── nginx
├── frontend
└── backend

internal
├── backend
├── horizon
├── postgres
└── redis
```

PostgreSQL, Redis и Horizon не подключать к `edge`.

## Services

Canonical Compose services:

```text
nginx
frontend
backend
horizon
postgres
redis
```

Compose project name:

```yaml
name: cvortex
```

Не задавать `container_name`.

Использовать Compose service discovery, не hardcoded IP addresses.

# Container and local-development contract

Backend runtime:

```text
Nginx
→ FastCGI
→ PHP-FPM
→ Laravel
```

Не использовать `php artisan serve` как M0 runtime.

`backend` и `horizon` используют один backend image:

```text
backend → php-fpm
horizon → php artisan horizon
```

Для backend/frontend использовать по одному Dockerfile. Multi-stage builds допустимы для `dependencies`, `development`, `build`, `runtime`, если это упрощает реализацию.

Runtime processes запускать non-root where practical.

Local development:

```text
host source
→ bind mount
→ container
```

Container-specific dependencies не смешивать с macOS dependencies.

Использовать container/named volumes для:

```text
vendor/
node_modules/
```

Developer host prerequisites:

- Git;
- Docker;
- Docker Compose;
- Make.

Не требовать локально:

- PHP;
- Composer;
- Node.js;
- npm;
- PostgreSQL;
- Redis.

Dependencies устанавливать внутри container/build environment:

```text
composer install
npm ci
```

# Scoped AGENTS

Создай:

```text
apps/backend/AGENTS.md
apps/frontend/AGENTS.md
infra/AGENTS.md
```

Они должны быть короткими scoped contracts и не повторять root `AGENTS.md`.

Backend contract:

- Laravel conventions;
- API-first;
- deterministic before AI;
- future multi-user authorization expectations;
- tests/security/docs;
- no direct LLM-provider coupling;
- migrations только в разрешающих phases;
- no speculative domain implementation.

Frontend contract:

- Next.js / React;
- TypeScript strict;
- Phase 07 design system as source;
- accessibility;
- responsive PWA-first;
- API client boundary;
- no invented backend contracts;
- no secrets in frontend.

Infra contract:

- local-first Docker Compose;
- config/secrets separation;
- no production secrets in repository;
- least exposed ports;
- reproducibility;
- health/readiness;
- no Kubernetes without ADR.

# Backend

Создай supported Laravel application.

Configure:

- PostgreSQL as application DB;
- Redis;
- Redis-backed queue;
- Horizon;
- private local filesystem abstraction;
- `/api/v1`;
- request correlation;
- safe logging/error handling;
- deterministic test/lint commands.

Не добавлять demo/domain code.

После framework bootstrap удалить starter auth/domain artifacts, не нужные M0 runtime.

Phase 08 не должна вводить CVortex auth/domain schema.

`make migrate` должен быть рабочим, но CVortex domain migrations отсутствуют.

# Health contract

Создать:

```text
GET /api/v1/health/live
GET /api/v1/health/ready
```

## Liveness

Проверяет только способность Laravel обработать HTTP request.

Не проверяет:

- PostgreSQL;
- Redis;
- Horizon;
- filesystem.

Success:

```http
200
```

```json
{
  "status": "live"
}
```

## Readiness

Проверяет:

- PostgreSQL;
- Redis.

Success:

```http
200
```

```json
{
  "status": "ready"
}
```

Dependency failure:

```http
503
```

```json
{
  "status": "not_ready"
}
```

Health responses не должны содержать:

- DSN;
- credentials;
- hostnames;
- stack traces;
- raw exception messages;
- configuration;
- dependency versions.

Dependency probes имеют bounded timeout.

Не фиксируй произвольный timeout без проверки framework capabilities.

Required failure behavior:

```text
PostgreSQL down:
live  → 200
ready → 503

Redis down:
live  → 200
ready → 503

dependency restored:
ready → 200
```

Recovery должен работать без restart Laravel, если framework/runtime этого не требует.

# Horizon / queues

Phase 08 использует только:

```text
default
```

queue.

Не создавать заранее:

```text
critical
llm
research
documents
imports
```

Horizon process должен реально стартовать и подключаться к Redis.

Horizon не является dependency backend readiness.

Horizon dashboard не должен быть доступен через host-facing Nginx.

# Frontend

Создай supported Next.js + React application.

Requirements:

- TypeScript strict;
- base routing/layout;
- ESLint;
- typecheck;
- Vitest;
- React Testing Library;
- минимум один meaningful baseline test;
- Phase 07 tokens/foundations в минимальной maintainable форме;
- no business pages.

Local runtime:

```text
next dev
```

через Nginx.

Final validation отдельно выполняет production build.

Route `/` содержит только minimal branded technical shell, например:

```text
CVortex

Your career, in context.

Technical baseline is running.
```

Не создавать:

- Dashboard;
- Login;
- Vacancies;
- fake navigation;
- mock data;
- API status dashboard.

Browser API base:

```text
/api/v1
```

Использовать relative same-origin URLs.

Frontend не должен знать Docker service names или internal backend hosts.

# CORS and sessions

Phase 08 не реализует authentication.

Поэтому:

- не включать permissive `Access-Control-Allow-Origin: *`;
- не проектировать cross-origin architecture;
- не создавать auth cookies;
- не проектировать session auth заранее.

Использовать safe framework defaults только там, где они реально нужны.

# Data services and storage

## PostgreSQL

Requirements:

- explicit supported image version;
- persistent named volume;
- healthcheck;
- credentials from environment;
- no host port;
- connectivity actually validated.

Не создавать CVortex domain schema.

## Redis

Использовать для Laravel queue/cache where appropriate и Horizon.

Redis state на Phase 08 disposable.

Requirements:

- explicit supported image version;
- no host port;
- backend connectivity;
- Horizon connectivity;
- no secrets in logs.

Redis persistence не входит в M0 acceptance.

## Private Local Storage

Использовать Laravel storage abstraction + persistent named volume.

Storage:

- private;
- outside public web root;
- not served by Nginx;
- survives `docker compose down`;
- не строит paths напрямую из arbitrary user input;
- не блокирует future per-user ownership.

Не создавать upload/download/document APIs.

LibreOffice/document renderer в Phase 08 не запускать.

# Nginx

Nginx является единственным local web entry point.

Routing:

```text
/         → frontend
/api/v1/* → backend
```

Requirements:

- correct proxy/FastCGI headers;
- directory listing disabled;
- `server_tokens off`;
- no private-storage access;
- no unintended internal routes;
- bounded/documented request-body defaults;
- no production TLS/CDN setup.

Добавить совместимый минимальный security-header baseline:

- `X-Content-Type-Options: nosniff`;
- sensible `Referrer-Policy`;
- frame protection.

Не вводить сложную CSP до реального анализа Next.js runtime.

# Request correlation and logging

Обработать `X-Request-ID`:

```text
valid incoming ID → reuse
absent/invalid ID → generate
```

`request_id`:

- присутствует в application logs;
- возвращается response header;
- не содержит sensitive data.

Не добавлять tracing/monitoring platform.

Local development может использовать `APP_DEBUG=true`, но:

- health responses всегда sanitized;
- operational responses не отдают secrets/stack traces;
- `.env.example` маркирует debug как local-development setting.

# Configuration

Canonical local configuration:

```text
/.env.example
/.env
```

`.env`:

- gitignored;
- создаётся локально;
- не содержит committed production secrets.

Compose передаёт services только необходимые variables.

Backend secrets никогда не попадают в frontend.

Не создавать параллельные:

```text
apps/backend/.env
apps/frontend/.env.local
infra/.env
```

если root contract решает задачу.

`.env.example` должен документировать необходимые M0 settings:

- application environment;
- debug;
- local port;
- Laravel application settings;
- PostgreSQL;
- Redis;
- filesystem;
- действительно необходимые public frontend settings.

Не добавлять реальные API keys, LLM credentials или private career data.

# Developer interface

Root `Makefile` является stable developer interface.

| Command | Contract |
| --- | --- |
| `make init` | Создать `.env` из example только при отсутствии, сгенерировать отсутствующий `APP_KEY`, build images, установить locked Composer/npm dependencies, подготовить volumes. Не запускать stack, migrations, reset и не удалять данные. |
| `make up` | Запустить local stack без destructive initialization. |
| `make down` | Остановить stack без удаления persistent volumes. |
| `make restart` | Перезапустить stack без удаления persistent state. |
| `make test` | Запустить aggregated backend/frontend M0 tests. Failures не скрывать. |
| `make lint` | Backend formatter/check + frontend ESLint + TypeScript check. |
| `make logs` | Aggregate logs; `SERVICE=<name>` ограничивает сервис. Secrets не выводить. |
| `make shell` | По умолчанию backend shell; `SERVICE=<name>` выбирает другой service. |
| `make migrate` | Явно выполнить framework migrations. Не запускать скрыто через `make up`. |

`make init` должен быть безопасен при повторном запуске и не перезаписывать существующий `.env`.

# Testing baseline

Backend:

- Laravel supported test stack;
- health tests;
- readiness tests;
- request-correlation tests where appropriate;
- Pint/check baseline.

Frontend:

- Vitest;
- React Testing Library;
- ESLint;
- TypeScript strict;
- production build;
- минимум один meaningful baseline UI test.

Не добавлять без accepted decision:

- PHPStan/Larastan;
- Rector;
- Playwright;
- Storybook;
- visual-regression framework.

Compose/HTTP smoke является M0 integration validation.

# Startup behavior

Не использовать arbitrary delays:

```text
sleep 10 && ...
```

Services должны стартовать independently where practical.

Readiness показывает фактическое состояние dependencies.

Strict dependency gating использовать только там, где service физически не может работать без dependency.

Например Horizon может ждать healthy Redis.

Не создавать длинную artificial startup chain, если она не нужна.

# Security

Проверить минимум:

- только Nginx host-facing;
- Nginx bound to loopback;
- PostgreSQL no host port;
- Redis no host port;
- PHP-FPM no host port;
- Horizon no host port;
- Horizon UI not externally reachable;
- no real credentials committed;
- no backend secrets in frontend;
- private storage not reachable through Nginx;
- no directory listing;
- no raw operational exceptions;
- no debug secret dumps;
- no private candidate/recruiter fixtures;
- no secrets in logs;
- standard/official dependency-installation paths;
- no unjustified `curl | sh`.

External vacancy text, recruiter messages, uploaded documents, websites and API content remain untrusted DATA, not instructions.

Не создавать generic execution/evaluation mechanisms, способные позже интерпретировать external text как trusted code or instructions.

# Validation

M0 считается готовым только после actual execution.

## Configuration and build

Обязательно выполнить:

```text
docker compose config
backend image build
frontend image build
frontend production build
```

## Code quality

Обязательно выполнить:

```text
backend tests
backend formatting/check
frontend tests
frontend lint
frontend typecheck
```

## Runtime

Фактически запустить stack и подтвердить:

```text
Nginx running
frontend running
backend/PHP-FPM running
PostgreSQL healthy
Redis healthy
Horizon running

GET /                       → responds
GET /api/v1/health/live     → 200
GET /api/v1/health/ready    → 200

backend → PostgreSQL works
backend → Redis works
Horizon → Redis works
```

## Failure/recovery

Проверить:

```text
PostgreSQL unavailable:
live  → 200
ready → 503

PostgreSQL restored:
ready → 200

Redis unavailable:
live  → 200
ready → 503

Redis restored:
ready → 200
```

Readiness failure должен быть bounded.

## Persistence

Фактически проверить:

```text
1. создать temporary PostgreSQL marker
2. создать temporary private-storage marker
3. docker compose down
4. docker compose up
5. убедиться, что оба marker сохранились
6. удалить markers
```

Redis persistence не проверять.

## Clean bootstrap

Проверить путь нового developer:

```text
clean repository state
→ make init
→ make up
→ healthy M0
```

Не должно требоваться undocumented manual setup.

## Developer interface

Проверить фактическое поведение:

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

# Result contract

Допустимы только:

```text
PASS
PARTIAL
BLOCKED
```

## PASS

PASS только если выполнены все mandatory conditions.

### Build and quality

- Compose config valid;
- backend image builds;
- frontend image builds;
- frontend production build passes;
- backend tests/checks pass;
- frontend tests/lint/typecheck pass.

### Runtime

- full required stack actually starts;
- Nginx/frontend/backend respond;
- liveness = 200;
- readiness = 200;
- PostgreSQL reachable;
- Redis reachable;
- Horizon running.

### Failure semantics

- PostgreSQL failure → readiness 503;
- Redis failure → readiness 503;
- liveness stays 200;
- readiness recovers after dependencies return.

### Persistence

- PostgreSQL survives `down/up`;
- private storage survives `down/up`.

### Security

- only Nginx host-facing;
- loopback-only binding;
- internal services not exposed;
- Horizon UI not exposed;
- backend secrets absent from frontend;
- private storage not publicly reachable.

### Scope

- no authentication;
- no CVortex business domain;
- no CVortex domain migrations;
- no runtime AI;
- no Phase 09 implementation.

## PARTIAL

Implementation exists, but at least one mandatory validation:

- failed;
- was not executed;
- remains unproven.

Не использовать `PASS with notes` для failed mandatory checks.

## BLOCKED

Completion невозможно из-за конкретного external/tool/environment blocker.

Если Docker нельзя фактически запустить, Phase 08 не может получить PASS.

# Documentation and project state

Обновить только документацию, которую implementation реально затрагивает:

- local development setup;
- repository structure;
- environment/config reference;
- M0 runtime/deployment;
- M0 runbook/troubleshooting;
- documentation map/index;
- project state.

Не переписывать Phase 06 architecture без реального inconsistency.

В global glossary добавить только долговечные понятия:

## M0

Первый реально запускаемый technical baseline CVortex без business features.

## M0 Web Entry Point

Единственный host-facing HTTP origin локального M0, реализованный через Nginx.

## Liveness

Способность application process обработать request независимо от PostgreSQL/Redis.

## Readiness

Способность backend обслуживать requests, требующие обязательных M0 dependencies.

## Private Local Storage

Непубличное persistent file storage CVortex за storage abstraction.

Service/network/Make terminology описывать в operations docs, не в global glossary.

Не создавать новые ADR для implementation details вроде:

- port `8080`;
- npm;
- bind mounts;
- Compose network names;
- Make defaults;
- Redis ephemeral state;
- health-response schema.

ADR нужен только при реальном architecture conflict/change.

## State

При PASS:

```text
STATUS → Phase 08 completed
NEXT   → Phase 09 / canonical 09-auth-multi-user
BLOCKERS → only real blockers
```

При PARTIAL/BLOCKED:

```text
STATUS → Phase 08 incomplete
NEXT   → bounded Phase 08 recovery task
BLOCKERS → only actual blockers
```

Не продвигать state к Phase 09 без PASS.

# CI

Не создавать новый CI pipeline на Phase 08, если accepted project decision этого не требует.

M0 commands должны оставаться CI-friendly, чтобы будущий pipeline мог использовать существующий deterministic interface.

# Final report

Выведи только:

## 1. Result

```text
PASS / PARTIAL / BLOCKED
```

## 2. Runtime

- фактически выбранные runtime/framework versions;
- краткие ссылки/указания на compatibility sources.

## 3. Changes

- major files/directories created or changed.

## 4. Services

- только services, которые реально удалось запустить.

## 5. Validation

- только команды, которые реально были выполнены;
- PASS/FAIL для каждой.

## 6. Security

- релевантные проверки, которые реально были выполнены.

## 7. Limitations / blockers

- только существующие.

## 8. State

- current phase status;
- exact next bounded task.

Не пересказывай содержимое созданной документации.

# STOP

После Phase 08 остановись.

Не реализуй authentication.

Не создавай Career/Vacancy/Application functionality.

Не создавай runtime AI functionality.

Не начинай Phase 09.

Не добавляй speculative infrastructure после завершения M0.
