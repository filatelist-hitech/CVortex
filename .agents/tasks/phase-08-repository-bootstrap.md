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

Создать первый **реально запускаемый technical baseline CVortex**:

- минимальный monorepo;
- Laravel backend;
- Next.js frontend;
- PostgreSQL;
- Redis;
- Laravel Horizon;
- Nginx;
- Docker Compose;
- private local file storage;
- deterministic developer workflow;
- health/readiness contracts;
- минимальный logging/security baseline;
- реально выполненную M0 validation.

Phase 08 впервые разрешает technical implementation, но **не разрешает business features**.

M0 считается готовым только тогда, когда stack фактически собирается, запускается и проходит обязательные acceptance checks.

# 1. Execution mode

Следуй:

- `/AGENTS.md`;
- `/PROJECT.md`;
- `.agents/policies/resource-usage.md`;
- `.agents/workflows/phase-execution.md`;
- current project state;
- accepted ADR;
- Phase 06 architecture;
- Phase 07 design foundation.

Default execution policy:

- один агент;
- sequential execution;
- task-relevant context only;
- current-version research только там, где решение зависит от актуального ecosystem;
- narrow validation во время работы;
- полный M0 smoke suite перед completion;
- no subagents unless correctness genuinely requires them.

Не перечитывай весь documentation/research corpus.

Используй indexes, documentation map и ссылки для открытия только релевантных sections.

# 2. Instruction precedence

Используй следующий порядок:

```text
/AGENTS.md
→ applicable scoped AGENTS.md
→ accepted ADR
→ PROJECT.md / accepted design documents
→ this Phase 08 task spec
→ implementation details
```

Если этот task spec конфликтует с accepted ADR:

1. не меняй решение молча;
2. зафиксируй конфликт;
3. определи authoritative source;
4. если действительно требуется изменение архитектуры, используй существующий ADR amendment/supersession process.

# 3. Minimum context

Перед реализацией прочитай только необходимое:

1. `/AGENTS.md`;
2. `/PROJECT.md`;
3. `.agents/state/STATUS.md`;
4. `.agents/state/NEXT.md`;
5. `.agents/state/BLOCKERS.md`;
6. accepted ADR по:
   - Laravel;
   - PostgreSQL;
   - Redis/Horizon;
   - API-first;
   - Docker Compose/local-first deployment;
   - monorepo;
   - file storage;
   - documentation;
   - design system;
7. Phase 06 architecture/deployment/API boundaries;
8. Phase 07 design foundation только в части frontend bootstrap и design tokens;
9. relevant technical research через indexes;
10. existing repository conventions.

Не загружай Phase 03–04 research целиком.

# 4. Current-version rule

Перед фиксацией exact runtime/framework/package versions проведи targeted research по актуальным официальным источникам.

Проверить compatibility минимум для:

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

- official documentation / support matrices имеют приоритет;
- не брать версии из старых prompts как источник истины;
- не использовать Docker tag `latest`;
- использовать explicit supported version tags;
- concrete versions фиксировать в implementation/configuration;
- Composer dependencies фиксировать через `composer.lock`;
- npm dependencies фиксировать через `package-lock.json`;
- Docker image digests в Phase 08 не обязательны, если существующий supply-chain ADR этого не требует;
- concrete versions не превращать в вечные architecture invariants.

Кратко зафиксировать источники compatibility verification в Phase 08 state/final report.

# 5. Accepted M0 topology

## 5.1 M0 Web Entry Point

CVortex M0 использует **single-origin architecture**.

Единственный host-facing HTTP entry point:

```text
http://localhost:8080
```

Default port configurable через:

```text
CVORTEX_PORT=8080
```

Docker port binding должен быть loopback-only:

```text
127.0.0.1:${CVORTEX_PORT:-8080}:80
```

Не публиковать M0 на `0.0.0.0` по умолчанию.

Conceptual topology:

```text
Browser
   │
   ▼
127.0.0.1:${CVORTEX_PORT}
   │
   ▼
 Nginx
   │
   ├── /             → Next.js
   │
   └── /api/v1/*     → Laravel / PHP-FPM
```

PostgreSQL, Redis, PHP-FPM и Horizon не имеют host-facing ports.

Если accepted API ADR определяет более конкретный routing contract, следуй ему, не создавая параллельную схему.

# 6. Docker networks

Использовать две logical Compose networks:

```text
edge
internal
```

Expected membership:

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

Requirements:

- PostgreSQL не подключать к `edge`;
- Redis не подключать к `edge`;
- Horizon не публиковать наружу;
- backend является controlled bridge между HTTP layer и data layer;
- internal services не имеют host port mappings.

Не создавать дополнительные сети без реальной необходимости.

# 7. Repository structure

Создай минимальную repository structure.

Expected shape:

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
│   │
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

`packages/` и `services/` создавать только если accepted design действительно требует их **сейчас**.

Не создавать пустые директории ради симметрии.

Не создавать дополнительные `Dockerfile.dev`, `Dockerfile.prod`, `docker-compose.override.yml` и аналогичные файлы без доказанной необходимости.

# 8. Compose conventions

Использовать Compose project name:

```yaml
name: cvortex
```

Не задавать вручную `container_name`.

Canonical service names:

```text
nginx
frontend
backend
horizon
postgres
redis
```

Использовать Compose service discovery.

Не hardcode IP addresses.

# 9. Dockerfile strategy

Для backend и frontend использовать по одному Dockerfile.

Multi-stage build использовать там, где он реально упрощает:

- dependencies;
- development;
- build;
- runtime.

Не создавать несколько Dockerfile только ради разделения сред.

Runtime processes запускать non-root where practical.

Root допустим в build/setup stages, если это технически необходимо.

# 10. Host prerequisites

Developer не должен устанавливать локально:

- PHP;
- Composer;
- Node.js;
- npm;
- PostgreSQL;
- Redis.

Ожидаемые host prerequisites:

- Git;
- Docker;
- Docker Compose;
- Make.

Dependency installation выполняется внутри container/build environment.

Использовать:

```text
composer install
npm ci
```

с lock files.

# 11. Development mounts

Local developer runtime должен поддерживать нормальный iterative workflow.

Использовать:

```text
host source
→ bind mount
→ application container
```

При этом container-specific dependencies не должны смешиваться с macOS dependencies.

Использовать container/named volumes для:

```text
vendor/
node_modules/
```

или эквивалентную безопасную схему.

Production-like build проверяется отдельно validation-командами.

# 12. Scoped AGENTS.md

Создай scoped instructions только там, где они реально снижают ambiguity.

Ожидаются:

```text
apps/backend/AGENTS.md
apps/frontend/AGENTS.md
infra/AGENTS.md
```

Они должны быть короткими router/contracts и не дублировать root `/AGENTS.md`.

## Backend AGENTS

Минимум:

- Laravel conventions;
- API-first;
- deterministic before AI;
- future multi-user authorization expectations;
- tests/security/docs;
- no direct LLM provider coupling;
- domain migrations только в разрешающих phases;
- no speculative domain implementation.

## Frontend AGENTS

Минимум:

- Next.js / React;
- TypeScript strict;
- Phase 07 design system as source;
- accessibility;
- responsive PWA-first;
- no invented backend contracts;
- API client boundary;
- no secrets in frontend.

## Infra AGENTS

Минимум:

- local-first Docker Compose;
- configuration/secrets separation;
- no production secrets in repository;
- no Kubernetes without ADR;
- reproducibility;
- health/readiness;
- least exposed ports.

# 13. Backend bootstrap

Создай supported Laravel application, compatible с актуальным project/runtime constraints.

Runtime:

```text
Nginx
→ FastCGI
→ PHP-FPM
→ Laravel
```

Не использовать `php artisan serve` как architectural runtime.

Configure minimum:

- environment/config separation;
- PostgreSQL default application database;
- Redis;
- Redis-backed Laravel queue;
- Horizon;
- local private filesystem abstraction;
- `/api/v1` routing according to accepted API contract;
- request correlation;
- safe logging/error handling;
- deterministic test/lint commands.

Не добавлять demo/domain functionality.

# 14. Framework starter artifacts

После Laravel bootstrap удалить starter artifacts, относящиеся к будущей authentication/domain functionality, если они не нужны M0 runtime.

Phase 08 не должна вводить CVortex auth/domain schema.

В частности не оставлять только потому, что framework scaffold их создал:

- premature User/auth domain implementation;
- unnecessary users migration;
- password reset domain artifacts;
- authentication flows.

Framework-internal artifacts допустимы только если реально необходимы для выбранного supported runtime.

`make migrate` должен работать, но Phase 08 не должна содержать CVortex domain migrations.

# 15. Health contract

Создать:

```text
GET /api/v1/health/live
GET /api/v1/health/ready
```

## Liveness

`/api/v1/health/live`

Проверяет только:

> способен ли Laravel process обработать HTTP request.

Не проверяет:

- PostgreSQL;
- Redis;
- Horizon;
- filesystem.

Success:

```http
200
```

Body:

```json
{
  "status": "live"
}
```

## Readiness

`/api/v1/health/ready`

Проверяет обязательные synchronous M0 dependencies:

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

Failure:

```http
503
```

```json
{
  "status": "not_ready"
}
```

Не возвращать:

- DSN;
- usernames;
- passwords;
- hostnames;
- stack traces;
- exception messages;
- configuration;
- dependency versions;
- sensitive diagnostics.

Dependency probes должны иметь bounded timeout.

Не фиксируй произвольное конкретное timeout value без проверки framework capabilities.

# 16. Readiness failure semantics

Обязательный observable behavior:

## PostgreSQL down

```text
live  → 200
ready → 503
```

## Redis down

```text
live  → 200
ready → 503
```

После восстановления зависимости:

```text
ready → 200
```

без необходимости restart backend, если используемый framework/runtime сам не требует этого.

Raw exception не должен попадать в health response.

# 17. Horizon / Queue

Использовать один backend image для разных runtime roles:

```text
backend
→ php-fpm

horizon
→ php artisan horizon
```

На Phase 08 очередь только:

```text
default
```

Не создавать преждевременно:

- critical;
- llm;
- research;
- documents;
- imports;
- сложные retry topologies.

Horizon process должен реально стартовать и подключаться к Redis.

Horizon dashboard:

```text
runtime     → yes
public UI   → no
```

`/horizon` не должен быть доступен пользователю через host-facing Nginx routing.

Не считать Horizon обязательной dependency Laravel readiness endpoint.

Horizon/container health проверяется отдельно.

# 18. Frontend bootstrap

Создай supported Next.js + React frontend.

Requirements:

- TypeScript strict;
- application boots;
- base layout/routing;
- ESLint;
- typecheck;
- Vitest;
- React Testing Library;
- минимум один реальный baseline frontend test;
- environment-safe API access;
- Phase 07 foundational tokens доступны в минимальной maintainable форме;
- no business pages;
- no fake navigation;
- no auth screens;
- no vacancy/application UI.

Frontend local runtime:

```text
next dev
```

через Nginx.

Обязательная final validation отдельно выполняет production build.

# 19. M0 frontend page

Route `/` должен быть только minimal branded technical shell.

Допустимое содержимое концептуально:

```text
CVortex

Your career, in context.

Technical baseline is running.
```

Использовать существующие Phase 07 foundations/tokens.

Не создавать:

- Dashboard;
- login;
- vacancies UI;
- application cards;
- fake data;
- API status dashboard;
- speculative product navigation.

API connectivity проверяется automated smoke validation, а не декоративным indicator.

# 20. Frontend API boundary

Browser API base:

```text
/api/v1
```

Использовать same-origin relative URLs.

Не передавать frontend:

```text
http://backend:...
http://localhost:8000
internal Docker hostnames
```

Не создавать публичную переменную backend host, если она не нужна.

Frontend не должен знать Docker service topology.

# 21. CORS / sessions baseline

Phase 08 не реализует authentication.

Single-origin architecture означает:

- не включать permissive `Access-Control-Allow-Origin: *`;
- не проектировать cross-origin architecture;
- не создавать authentication cookies;
- не проектировать session auth заранее.

Использовать safe framework defaults только там, где они реально нужны.

# 22. PostgreSQL

Configure local PostgreSQL.

Requirements:

- persistent named volume;
- healthcheck;
- credentials из environment;
- explicit supported image version;
- UTF-8/default framework-compatible setup;
- no public host port;
- connectivity реально validated.

Не создавать CVortex domain schema.

# 23. Redis

Configure Redis для:

- cache/queue where appropriate;
- Laravel queue;
- Horizon.

Phase 08 Redis state считается **disposable**.

Не требуется persistence acceptance для Redis.

Requirements:

- no host port;
- explicit supported image version;
- backend connectivity;
- Horizon connectivity;
- secrets не выводятся в logs.

Не добавлять persistence только ради симметрии с PostgreSQL.

# 24. Private Local Storage

Создать framework storage abstraction с initial local backend.

Использовать persistent named Docker volume.

Properties:

- private;
- не находится в public web root;
- не обслуживается Nginx;
- survives `docker compose down`;
- path не строится напрямую из arbitrary user input;
- future per-user ownership design не блокируется.

Не реализовывать:

- resume uploads;
- generated document domain;
- download API;
- document rendering workflow.

LibreOffice/document renderer в Phase 08 **не запускать**.

Зафиксировать его как deferred dependency будущей document phase.

# 25. Nginx

Nginx — единственный local web entry point.

Routing:

```text
/            → frontend
/api/v1/*    → backend
```

Использовать корректные proxy/FastCGI headers согласно выбранному runtime.

Requirements:

- no directory listing;
- `server_tokens off`;
- no accidental private storage access;
- no internal metadata exposure;
- bounded/documented request body defaults;
- no production TLS/CDN configuration.

Минимальные security headers, где совместимо:

- `X-Content-Type-Options: nosniff`;
- sensible `Referrer-Policy`;
- frame protection.

Не вводить сложную Content-Security-Policy без реального анализа Next.js runtime.

# 26. Request correlation

Добавить простой backend request correlation.

Behavior:

```text
incoming X-Request-ID
        │
        ├── valid → reuse
        │
        └── absent/invalid → generate
```

`request_id`:

- присутствует в application logs;
- возвращается response header;
- не содержит sensitive data.

Не добавлять:

- OpenTelemetry stack;
- distributed tracing backend;
- monitoring platform.

# 27. Debug behavior

Local development может использовать:

```text
APP_DEBUG=true
```

если это соответствует Laravel local-development conventions.

Но:

- health endpoints всегда sanitized;
- operational responses не содержат stack traces/secrets;
- `.env.example` явно показывает, что debug — local setting;
- final validation выполняется в контролируемой environment.

# 28. Configuration ownership

Canonical local configuration contract:

```text
/.env.example
/.env
```

`.env`:

- gitignored;
- создаётся локально;
- не содержит committed production secrets.

Docker Compose allowlists variables в конкретные services.

Backend-only secrets не должны попадать в frontend.

Frontend получает только действительно public configuration.

Не создавать конкурирующие configuration sources без необходимости:

```text
apps/backend/.env
apps/frontend/.env.local
infra/.env
```

если root configuration contract полностью решает задачу.

# 29. `.env.example`

Должен содержать safe placeholders и необходимые M0 variables.

Минимально документировать:

- application environment;
- debug;
- CVortex local port;
- Laravel application settings;
- database name/user/password placeholders;
- Redis connection;
- filesystem configuration;
- frontend-safe variables, если они действительно нужны.

Не добавлять:

- реальные API keys;
- production credentials;
- BYOK credentials;
- OpenAI keys;
- recruiter/career data.

System-managed/BYOK LLM credentials не реализуются в Phase 08.

# 30. Developer workflow

Root `Makefile` является stable developer interface.

Required:

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

# 31. `make init`

Contract:

```text
make init
├── создаёт .env из .env.example только если .env отсутствует
├── генерирует APP_KEY только если отсутствует
├── build required images
├── устанавливает locked Composer dependencies
├── устанавливает locked npm dependencies
└── подготавливает required dependency/storage volumes
```

Не должен:

- запускать stack;
- удалять persistent data;
- выполнять migrations автоматически;
- выполнять reset;
- перезаписывать существующий `.env` без explicit user action.

Команда должна быть idempotent enough для повторного безопасного использования.

# 32. `make up`

Запускает local stack reproducibly.

Developer runtime frontend:

```text
next dev
```

Backend:

```text
PHP-FPM
```

Не должен выполнять destructive initialization.

# 33. `make down`

Останавливает stack.

Не удаляет persistent volumes по умолчанию.

# 34. `make restart`

Предсказуемо перезапускает stack без удаления persistent state.

# 35. `make test`

Запускает aggregated M0 test suite минимум для:

- backend framework tests;
- backend health tests;
- frontend tests.

Не должен скрывать failures.

# 36. `make lint`

Запускает relevant static/quality checks:

Backend:

- Laravel Pint check или equivalent project formatter validation.

Frontend:

- ESLint;
- TypeScript typecheck.

Не устанавливать additional quality stack только ради количества инструментов.

# 37. `make logs`

Default:

```bash
make logs
```

показывает aggregate stack logs.

Поддержать:

```bash
make logs SERVICE=backend
```

или эквивалентный parameterized interface.

Не создавать отдельную Make target для каждого service без необходимости.

Не выводить secrets.

# 38. `make shell`

Default:

```bash
make shell
```

открывает backend application container shell.

Поддержать parameterized form:

```bash
make shell SERVICE=frontend
make shell SERVICE=postgres
```

или эквивалент.

# 39. `make migrate`

Выполняет framework migrations явным действием.

На Phase 08:

- no CVortex domain migrations;
- command должен быть работоспособным;
- migrations не должны запускаться скрыто через `make up`.

# 40. Testing baseline

## Backend

Использовать существующий supported Laravel testing stack.

Минимум:

- PHPUnit/framework tests;
- health endpoint tests;
- readiness behavior tests where practical;
- request correlation tests where appropriate;
- Pint/check baseline.

Не добавлять на Phase 08 без existing accepted decision:

- PHPStan/Larastan;
- Rector;
- heavyweight architecture testing packages.

## Frontend

Минимум:

- Vitest;
- React Testing Library;
- ESLint;
- TypeScript strict;
- production build;
- минимум один meaningful baseline UI test.

Не добавлять без необходимости:

- Playwright;
- Storybook;
- visual regression framework.

Infrastructure smoke является actual Compose/HTTP validation, а не browser E2E framework.

# 41. Startup policy

Не использовать arbitrary sleep loops:

```text
sleep 10 && ...
```

Services должны стартовать reasonably independently.

Readiness показывает фактическое состояние зависимостей.

Strict dependency gating использовать только там, где service физически не может корректно стартовать без dependency.

Например Horizon может использовать Redis health condition.

Не строить unnecessarily rigid startup chain:

```text
postgres
→ redis
→ backend
→ frontend
→ nginx
```

если она не требуется.

# 42. Persistence acceptance

PostgreSQL и Private Local Storage считаются persistent M0 state.

Redis persistence не входит в M0 acceptance.

Final validation должна фактически проверить persistence.

Scenario:

```text
1. создать временный marker в PostgreSQL
2. создать временный marker в private storage
3. docker compose down
4. docker compose up
5. убедиться, что оба marker сохранились
6. удалить test markers
```

Не считать наличие `volumes:` в YAML достаточным доказательством persistence.

# 43. Clean bootstrap acceptance

Проверить developer journey максимально близко к clean checkout:

```text
clean repository state
→ make init
→ make up
→ stack becomes healthy
```

Не должно существовать undocumented manual bootstrap actions вроде:

```text
"сначала вручную создай этот каталог"
"зайди в container и выполни..."
"один раз скопируй неизвестный config"
```

Все обязательные host prerequisites должны быть задокументированы.

# 44. Security baseline

Проверить минимум:

- единственный host-facing HTTP service — Nginx;
- host binding только loopback;
- PostgreSQL не имеет host port;
- Redis не имеет host port;
- PHP-FPM не имеет host port;
- Horizon не имеет host port;
- Horizon dashboard не доступен с host;
- no real credentials committed;
- no backend secrets exposed to frontend;
- private storage не доступен через Nginx;
- no directory listing;
- no raw exception output from operational endpoints;
- no debug secret dumps;
- no arbitrary `curl | sh` install flow без justification;
- standard/official dependency installation paths;
- no private candidate/recruiter data fixtures;
- logs не содержат secrets.

# 45. Untrusted input

Phase 08 ещё не реализует vacancy/resume imports, но foundation не должна создавать unsafe assumptions.

Помнить project rule:

- vacancy content;
- recruiter messages;
- uploaded documents;
- imported documents;
- external web/API content

являются DATA, а не trusted instructions.

Не создавать в M0 generic execution/evaluation abstractions, которые позже могут случайно интерпретировать external text как code/instruction.

# 46. CI

Новый CI pipeline **не входит в Phase 08**, если accepted project decision не требует его уже сейчас.

Не создавать GitHub Actions или другую CI систему только ради checklist.

Однако developer commands должны быть CI-friendly:

```text
make test
make lint
docker compose config
build commands
smoke commands
```

Будущая CI должна иметь возможность вызывать существующий deterministic interface.

# 47. No premature tooling

Не добавлять без proven need:

- Kubernetes;
- microservices;
- Kafka;
- standalone vector DB;
- GraphQL;
- native mobile;
- Elasticsearch;
- event sourcing;
- fine-tuning;
- service mesh;
- monitoring stack;
- OpenTelemetry stack;
- browser E2E framework;
- Storybook;
- speculative shared packages;
- generic plugin framework.

# 48. Validation strategy

Во время implementation используй narrow checks.

Перед completion выполнить полный M0 validation.

## Configuration

```text
docker compose config
```

должен пройти.

## Build

Проверить:

- backend image build;
- frontend image build;
- frontend production build.

## Backend quality

Выполнить реальные:

- tests;
- formatting/check;
- health contract tests.

## Frontend quality

Выполнить реальные:

- tests;
- lint;
- TypeScript check;
- production build.

## Runtime

Фактически выполнить:

```text
docker compose up
```

и проверить required services.

# 49. Mandatory runtime acceptance

Подтвердить:

```text
✓ Nginx running
✓ frontend running
✓ backend/PHP-FPM running
✓ PostgreSQL running/healthy
✓ Redis running/healthy
✓ Horizon running
```

HTTP:

```text
✓ GET / responds
✓ GET /api/v1/health/live  → 200
✓ GET /api/v1/health/ready → 200
```

Dependencies:

```text
✓ backend can reach PostgreSQL
✓ backend can reach Redis
✓ Horizon can reach Redis
```

# 50. Mandatory failure/recovery acceptance

Фактически проверить, где execution environment это позволяет.

## PostgreSQL failure

```text
PostgreSQL unavailable

/api/v1/health/live
→ 200

/api/v1/health/ready
→ 503
```

Восстановить PostgreSQL:

```text
/api/v1/health/ready
→ 200
```

## Redis failure

```text
Redis unavailable

/api/v1/health/live
→ 200

/api/v1/health/ready
→ 503
```

Восстановить Redis:

```text
/api/v1/health/ready
→ 200
```

Readiness failure должен быть bounded, а не зависать indefinitely.

# 51. Required Make validation

Проверить интерфейс:

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

Где команда имеет observable behavior, выполнить её реально, а не только проверить наличие строки в Makefile.

# 52. PASS contract

Phase 08 имеет статус **PASS только если выполнены все mandatory acceptance criteria**.

## Configuration

- `docker compose config` проходит.

## Build

- backend image строится;
- frontend image строится;
- frontend production build проходит.

## Code quality

- backend tests проходят;
- backend formatting/check проходит;
- frontend tests проходят;
- frontend lint проходит;
- frontend typecheck проходит.

## Runtime

- stack реально запущен;
- Nginx отвечает;
- frontend отвечает;
- backend API отвечает;
- liveness = `200`;
- readiness = `200`;
- PostgreSQL reachable;
- Redis reachable;
- Horizon running.

## Failure semantics

- PostgreSQL down → readiness `503`;
- Redis down → readiness `503`;
- liveness остаётся `200`;
- после recovery readiness возвращается к `200`.

## Persistence

- PostgreSQL survives `down/up`;
- private file storage survives `down/up`.

## Security

- только Nginx host-facing;
- Nginx bound to loopback;
- PostgreSQL/Redis not exposed;
- Horizon UI not exposed;
- frontend receives no backend secrets;
- private storage not publicly reachable.

## Developer UX

- required Make targets существуют и работают согласно contract.

## Scope

- no authentication;
- no invitations;
- no Career domain;
- no Vacancy domain;
- no Application domain;
- no CVortex domain migrations;
- no runtime AI workflows;
- no Phase 09 implementation.

# 53. Result semantics

Допустимы только:

```text
PASS
PARTIAL
BLOCKED
```

## PASS

Все mandatory acceptance criteria выполнены.

Known limitation допустим только если он не нарушает completion criteria.

## PARTIAL

Implementation существует, но:

- обязательная validation failed;
- обязательная validation не была выполнена;
- один или несколько acceptance criteria не доказаны.

Никакого:

```text
PASS with failing tests
```

## BLOCKED

Completion невозможно из-за конкретного external/tool/environment blocker.

Если Docker нельзя фактически запустить в execution environment:

```text
PARTIAL
```

или:

```text
BLOCKED
```

в зависимости от причины.

Никогда не объявлять PASS по одному inspection файлов.

# 54. Documentation

Обновить только документацию, которую Phase 08 реально затрагивает.

Минимально:

- local development setup;
- repository structure;
- environment/configuration reference;
- M0 deployment/runtime description;
- M0 runbook;
- troubleshooting;
- documentation map/index;
- project state.

Не переписывать Phase 06 architecture без реального inconsistency.

# 55. Glossary

В глобальный project glossary добавить только долговечные понятия:

## M0

Первый реально запускаемый technical baseline CVortex без business features.

## M0 Web Entry Point

Единственный host-facing HTTP origin локального M0, реализованный через Nginx.

## Liveness

Способность application process обработать request независимо от состояния PostgreSQL/Redis.

## Readiness

Способность backend обслуживать requests, требующие обязательных M0 dependencies.

## Private Local Storage

Непубличное persistent file storage CVortex, доступное через storage abstraction и не обслуживаемое напрямую web server.

Не добавлять в global glossary implementation trivia вроде network/service names или Make target semantics.

Их описывать в operations documentation.

# 56. ADR policy

Не создавать новые ADR заранее только для фиксации implementation details Phase 08.

Не нужны отдельные ADR для:

- default port 8080;
- npm;
- bind mounts;
- Compose network names;
- Make target defaults;
- Redis disposable state в M0;
- конкретной health response schema.

Новый ADR / ADR amendment требуется только если implementation обнаруживает:

- настоящий architectural conflict;
- необходимость изменить accepted architectural decision;
- долгосрочное решение, которое нельзя корректно зафиксировать обычной implementation/operations documentation.

Не менять accepted architecture молча.

# 57. State update

## Если PASS

Обновить:

### `STATUS.md`

Зафиксировать:

- Phase 08 completed;
- фактически выбранные runtime/framework versions;
- основные runtime/config decisions;
- реально выполненную validation;
- known limitations.

### `NEXT.md`

Exact next phase:

```text
09-auth-multi-user
```

или каноническое имя Phase 09 из current project state.

### `BLOCKERS.md`

Только реальные существующие blockers.

## Если PARTIAL/BLOCKED

Phase 08 остаётся current/incomplete.

`STATUS.md` должен честно отражать текущее состояние.

`NEXT.md` должен указывать **bounded recovery task внутри Phase 08**, а не Phase 09.

`BLOCKERS.md` содержит только фактические blockers.

Не продвигать project state к Phase 09 без PASS.

# 58. Non-goals

На Phase 08 НЕ:

- authentication;
- login/logout;
- invitations;
- admin/user implementation;
- CareerProfile;
- CareerTrack;
- CareerFact;
- Claim Registry;
- Vacancy;
- Company;
- Application;
- Employer Memory;
- resume import;
- resume generation;
- cover letters;
- document rendering;
- LibreOffice runtime;
- runtime LLM integration;
- OpenAI provider;
- LLM credentials;
- job-board integration;
- domain migrations;
- browser extension;
- PWA installability packages purely for checkbox completion;
- dashboard;
- full design system implementation;
- CI unless already required by accepted decision;
- speculative infrastructure;
- Phase 09 work.

Framework-required artifacts допустимы только если реально необходимы для M0 runtime.

# 59. Completion checklist

Перед финальным ответом самостоятельно проверить:

```text
Repository
[ ] minimal monorepo exists
[ ] no speculative empty packages/services
[ ] scoped AGENTS are concise

Versions
[ ] current official compatibility checked
[ ] explicit supported versions used
[ ] no :latest tags
[ ] lock files committed

Networking
[ ] only Nginx exposed
[ ] loopback binding
[ ] edge/internal separation
[ ] PostgreSQL not exposed
[ ] Redis not exposed
[ ] Horizon not exposed

Backend
[ ] Laravel boots
[ ] PHP-FPM works
[ ] PostgreSQL works
[ ] Redis works
[ ] Horizon works
[ ] health/live works
[ ] health/ready works
[ ] request correlation works

Frontend
[ ] Next.js boots
[ ] TypeScript strict
[ ] tests pass
[ ] lint passes
[ ] typecheck passes
[ ] production build passes
[ ] no product UI

Storage
[ ] private storage configured
[ ] storage not web-public
[ ] storage persistent

Failure semantics
[ ] PostgreSQL failure tested
[ ] Redis failure tested
[ ] recovery tested
[ ] liveness remains correct

Developer workflow
[ ] make init
[ ] make up
[ ] make down
[ ] make restart
[ ] make test
[ ] make lint
[ ] make logs
[ ] make shell
[ ] make migrate

Persistence
[ ] PostgreSQL down/up tested
[ ] private storage down/up tested

Security
[ ] no secrets committed
[ ] no backend secrets in frontend
[ ] no Horizon dashboard exposure
[ ] no directory listing
[ ] sanitized operational errors

Documentation
[ ] development setup updated
[ ] operations/runtime docs updated
[ ] glossary updated
[ ] project state updated

Scope
[ ] no auth
[ ] no business domain
[ ] no domain migrations
[ ] no Phase 09
```

# 60. Final Report

Keep concise.

Output only:

## 1. Result

```text
PASS / PARTIAL / BLOCKED
```

## 2. Runtime

- exact runtime/framework versions actually selected;
- brief compatibility source references.

## 3. Changes

- major files/directories created;
- major files/directories changed.

Do not reproduce full file contents.

## 4. Services

List only services actually started successfully.

## 5. Validation

List commands actually executed and PASS/FAIL.

Never claim a command was executed if it was not.

## 6. Security

Briefly list relevant security checks actually performed.

## 7. Limitations / Blockers

Only real limitations/blockers.

## 8. State

- current phase status;
- exact next bounded task.

Do not repeat the contents of generated documentation.

# STOP

After Phase 08 stop.

Do not implement authentication.

Do not create Career/Vacancy/Application domain functionality.

Do not create runtime AI functionality.

Do not start Phase 09.

Do not add speculative infrastructure after M0 acceptance.
