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

Создать первый реально запускаемый technical baseline CVortex: рабочий monorepo, local-first Docker Compose environment и M0, на котором последующие bounded phases смогут безопасно реализовывать продукт.

M0 считается готовым только если stack фактически поднимается и базовые connectivity/health checks проходят.

Phase 08 впервые разрешает product-adjacent technical implementation, но не business features.

## Execution mode

Следуй `/AGENTS.md`, `.agents/policies/resource-usage.md`, `.agents/workflows/phase-execution.md`, accepted ADR и Phase 06–07 design outputs.

Default:

- один агент;
- sequential implementation;
- task-relevant context only;
- current-version research только для конкретных dependency/runtime choices;
- narrow validation while iterating;
- full M0 smoke validation before completion;
- no subagents unless correctness genuinely requires parallel work.

## Minimum context

Прочитай:

1. `PROJECT.md`.
2. current `STATUS.md`, `NEXT.md`, `BLOCKERS.md`.
3. accepted ADR, относящиеся к Laravel, PostgreSQL, Redis/Horizon, API-first, Docker Compose, monorepo, file storage, documentation and design tokens.
4. Phase 06 architecture/deployment/API boundaries.
5. Phase 07 design foundation только настолько, насколько она влияет на frontend bootstrap/tokens.
6. relevant technical research через indexes, только для version/package decisions.
7. existing repository tooling, `.editorconfig`, Git workflow, Make/scripts conventions.

Не перечитывай весь research corpus.

## Current-version rule

Перед фиксацией exact runtime/framework/package versions проверь актуальные official support/compatibility data.

Не брать версии из старых prompts как источник истины.

Зафиксируй выбранные версии в implementation/configuration только после compatibility verification.

Не превращай конкретные версии в вечные architecture invariants.

# Scope

Разрешено создать реальную monorepo structure и technical bootstrap.

Ожидаемый shape conceptually:

```text
apps/
  backend/
  frontend/
packages/          # only if immediately used
services/          # only if an accepted design requires one now
infra/
docs/
research/
brand/
```

Не создавай пустые каталоги ради symmetry. `services/` и `packages/` появляются только при реальной Phase 08 необходимости.

## Required bootstrap

- Laravel backend;
- Next.js frontend;
- React/TypeScript strict;
- PostgreSQL;
- Redis;
- Laravel Horizon;
- Nginx;
- Docker Compose;
- local filesystem storage abstraction baseline;
- health/readiness endpoints;
- deterministic developer workflow;
- basic logging/error handling baseline;
- scoped `AGENTS.md` where directory-specific rules are materially useful.

# Non-goals

На Phase 08 НЕ:

- реализовывать authentication flows;
- реализовывать invitations;
- создавать Career/Vacancy/Application business models;
- реализовывать runtime AI workflows;
- подключать job boards;
- создавать domain migrations;
- реализовывать Employer Memory;
- создавать resume/cover letter generation;
- добавлять microservices;
- добавлять Kubernetes;
- добавлять Kafka;
- добавлять standalone vector DB;
- добавлять GraphQL;
- реализовывать native mobile;
- реализовывать speculative generic platform/framework abstractions;
- начинать Phase 09.

Framework-required/internal bootstrap artifacts допустимы только если действительно нужны для запуска выбранного supported stack. Не использовать их как скрытый способ начать Phase 09.

# Monorepo Structure

Создай минимальную понятную structure.

## `apps/backend`

Laravel core application/API.

Responsibilities at M0:

- boot framework;
- `/api/...` health/readiness endpoint according to accepted API conventions;
- database connectivity;
- Redis connectivity;
- queue/Horizon baseline;
- filesystem abstraction configuration;
- safe configuration handling;
- deterministic test/lint commands.

Не добавлять domain modules раньше Phase 09+.

## `apps/frontend`

Next.js/React frontend/PWA foundation.

At M0:

- application boots;
- TypeScript strict enabled;
- base routing/layout only;
- health/API connectivity surface if needed;
- consume Phase 07 design tokens/foundations in the minimum non-speculative way;
- no business pages.

Не строить dashboard, auth screens или vacancy UI заранее.

## `infra`

Container/development infrastructure only.

Минимально:

- Docker Compose;
- Nginx config;
- local service configuration;
- development container/build files;
- documented volume/network strategy;
- no production orchestration platform.

## `packages` / `services`

Создавать только если Phase 06/07 accepted design прямо требует shared runtime package at bootstrap.

Runtime AI Skills canonical directory из Phase 06 должен быть respected, но не наполняй его product skills раньше соответствующей phase.

# Scoped AGENTS.md

Создай scoped instructions только там, где они снижают future ambiguity.

Ожидаемые candidates:

```text
apps/backend/AGENTS.md
apps/frontend/AGENTS.md
infra/AGENTS.md
```

Каждый scoped file должен быть коротким router/contract, а не дублировать root `AGENTS.md`.

## Backend scoped instructions

Покрыть минимум:

- Laravel conventions;
- API-first;
- multi-user authorization expectations для будущих phases;
- tests/security/docs requirement;
- no direct provider coupling;
- migrations only in phases that permit them;
- deterministic before AI.

## Frontend scoped instructions

Покрыть минимум:

- Next.js/React/TypeScript strict;
- Phase 07 design system source;
- accessibility;
- no invented backend contracts;
- API client boundary;
- no secrets in frontend;
- responsive PWA-first.

## Infra scoped instructions

Покрыть минимум:

- local-first Docker Compose;
- secrets/config separation;
- no production secrets in repo;
- no Kubernetes without ADR;
- health/readiness and reproducibility.

# Backend Bootstrap

## Laravel

Создай Laravel application using currently supported version compatible with project constraints.

Минимально configure:

- environment/config separation;
- PostgreSQL default application DB;
- Redis cache/queue where appropriate;
- Horizon;
- filesystem abstraction with local initial driver;
- CORS/session/API settings only to the extent required by Phase 08 architecture;
- timezone/locale strategy if already decided;
- application name/configuration.

Не включай demo/domain code.

## Health endpoints

Создай deterministic endpoints suitable for local smoke checks.

Раздели при необходимости:

- liveness: process/app responds;
- readiness: required dependencies reachable.

Не возвращай secrets/config details.

Readiness может проверять PostgreSQL/Redis in a bounded safe way.

Определи stable response schema and status behavior.

## Queue / Horizon

Bootstrap Redis-backed Laravel queues and Horizon according to accepted ADR.

Минимально:

- connection works;
- worker/Horizon can start;
- no business jobs required;
- queue names/topology remain minimal;
- no speculative complex retries/topologies.

Horizon access/control considerations должны быть documented; не exposing administrative dashboard publicly by accident.

# Frontend Bootstrap

Создай supported Next.js application with React and TypeScript strict.

Минимально:

- app boots;
- lint/type-check/test baseline;
- environment-aware API base configuration;
- no server/client secret leakage;
- base error/loading boundaries as appropriate;
- Phase 07 foundational tokens available in a maintainable form;
- no full component library implementation.

Если PWA tooling требует dependency choice not yet accepted, не добавляй package purely for checkbox completion. Document deferred PWA installability work if M0 does not require it.

# PostgreSQL

Configure local PostgreSQL service.

Requirements:

- persistent volume;
- healthcheck;
- application credentials from environment/secrets mechanism;
- no hardcoded production-like password;
- no public exposure unless local developer need is explicitly justified;
- UTF-8/default settings appropriate to framework;
- connectivity validated.

Не создавай domain schema/migrations.

# Redis / Horizon

Configure Redis for local stack.

Requirements:

- persistence choice documented;
- local network exposure minimized;
- backend connectivity validated;
- queue/Horizon connectivity validated;
- no secrets printed in logs.

# Nginx

Nginx должен быть local web entry point according to accepted deployment design.

Configure only needed routing, e.g. frontend/backend paths as architecture specifies.

Requirements:

- no accidental directory listing;
- bounded body size defaults/documented future upload implications;
- security headers baseline where appropriate;
- correct proxy headers;
- no internal service exposure through unintended routes.

Не over-engineer production TLS/CDN config in local M0.

# Docker Compose

Compose должен запускать минимально required local stack.

Expected services according to design may include:

- nginx;
- frontend;
- backend/php runtime;
- PostgreSQL;
- Redis;
- Horizon/queue worker.

LibreOffice/document renderer включай в M0 только если Phase 06 deployment baseline requires it running now; иначе подготовь clearly bounded container/service foundation/documentation for later document phase. Не создавай fake health success for a service that is not actually used.

## Container principles

- reproducible builds;
- explicit working directories;
- non-root where practical;
- least exposed ports;
- named volumes;
- environment files not committed with secrets;
- predictable service names;
- healthchecks;
- startup dependency assumptions documented rather than hidden in sleep loops.

# Developer Workflow

Provide stable developer commands via `Makefile` or existing project convention.

Required interface:

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

Commands must have clear semantics.

## `make init`

Bootstrap local prerequisites/config without silently destroying existing data.

## `make up`

Start stack reproducibly.

## `make down`

Stop stack. Do not delete persistent volumes by default.

## `make restart`

Predictable restart.

## `make test`

Run relevant backend/frontend tests or aggregated baseline checks.

## `make lint`

Run formatter/lint/type/static checks available at M0.

## `make logs`

Useful aggregate logs without exposing secrets.

## `make shell`

Document which service shell it opens, or provide explicit target/argument if needed.

## `make migrate`

Run framework migrations. At Phase 08 there should be no CVortex domain migrations. Command exists for future phases and framework necessities.

# Configuration and Secrets

Define `.env.example`/equivalent with safe placeholders.

Requirements:

- no real API keys;
- no credentials committed;
- document required variables;
- backend-only secrets never exposed through public frontend prefixes;
- system-managed/BYOK LLM credentials are NOT implemented in Phase 08;
- secret handling path remains compatible with future Phase 09/AI design.

# File Storage Foundation

Implement accepted storage abstraction baseline with local initial backend only to the extent needed for framework readiness.

Do not implement resume uploads or document workflows.

Define/configure:

- local storage root;
- separation from public web root where private files will live;
- future per-user ownership expectation;
- generated file path strategy at conceptual/config level;
- no user-controlled raw filesystem path.

# API Boundary

Respect Phase 06 API-first design.

At M0 only health/readiness and minimum bootstrap API behavior should exist.

If `/api/v1` is accepted convention, follow it. Do not invent a parallel prefix.

Document error response baseline only as needed; do not implement speculative endpoint framework.

# Logging / Observability Baseline

Configure minimally useful structured/application logs.

Requirements:

- environment-aware level;
- no secrets;
- no raw credentials;
- no private candidate/recruiter fixtures;
- request correlation concept if simple and justified;
- startup/dependency failures observable.

Do not add full monitoring stack in M0.

# Security

Treat bootstrap as security-sensitive foundation.

Check minimum:

- services not unnecessarily exposed to host/public interfaces;
- no default credentials suitable for production committed;
- no debug secret dumps;
- frontend env cannot receive backend secrets;
- Horizon/admin surfaces not publicly reachable without future authorization;
- local file storage not web-public by default for private assets;
- Nginx proxy configuration does not expose internal metadata/files;
- dependency install scripts are standard/verified official paths;
- no arbitrary curl|sh unless justified and reviewed.

# Tests and Validation

M0 is complete only after actual execution, not file inspection alone.

Use narrow checks during development, then final smoke suite.

## Backend

Run actual framework tests/lint/static checks available.

Validate:

- application boots;
- health endpoint responds;
- readiness behavior correct when dependencies up;
- safe failure behavior if dependency unavailable where practical;
- PostgreSQL connection;
- Redis connection;
- queue/Horizon boot.

## Frontend

Run actual:

- install/build or equivalent;
- lint;
- TypeScript check;
- baseline tests if configured;
- server startup/response.

## Compose / Infrastructure

Run actual:

- config validation;
- image build;
- stack start;
- service health state;
- Nginx route smoke check;
- backend API smoke;
- frontend smoke;
- PostgreSQL smoke;
- Redis smoke;
- Horizon/worker smoke.

Do not claim M0 ready if stack was not actually started in available environment.

If environment/tool restriction prevents running Docker, mark Phase `PARTIAL/BLOCKED` rather than inventing success.

## Make commands

Execute required Make targets where safe and meaningful, at minimum validate command syntax/help and run central targets used for M0 acceptance.

# Documentation

Update only affected docs:

- local development setup;
- repository structure;
- architecture/deployment docs if implementation reveals necessary non-architectural detail;
- environment/configuration reference;
- M0 runbook/troubleshooting;
- documentation map/index;
- state.

Do not rewrite Phase 06 design without a real inconsistency.

# Scoped architecture changes

If implementation discovers that an accepted ADR cannot be implemented as written:

- stop that decision path;
- record evidence;
- do not silently choose alternative;
- use ADR amendment/supersession flow only if the architecture truly changes.

Normal package/version/config choices do not each require ADR.

# Completion Criteria

Phase 08 PASS only if:

## Repository

- monorepo structure exists and is minimal;
- backend/frontend/infra locations match accepted architecture;
- unnecessary empty `packages/services` directories not created;
- scoped AGENTS exist where useful and do not duplicate root rules.

## Backend

- supported Laravel app boots;
- PostgreSQL configured and reachable;
- Redis configured and reachable;
- Horizon/queue baseline starts;
- local filesystem storage baseline configured;
- health/readiness endpoint(s) work;
- no domain features implemented.

## Frontend

- supported Next.js/React app boots;
- TypeScript strict enabled;
- lint/type-check/build baseline works;
- Phase 07 foundation is consumable without implementing full product UI.

## Infrastructure

- Docker Compose config valid;
- stack actually builds/starts in available execution environment;
- Nginx entry point works;
- persistent volumes defined appropriately;
- internal services exposure minimized;
- healthchecks provide useful status.

## Developer UX

- required `make` targets exist with predictable semantics;
- setup documentation is sufficient for a clean local bootstrap;
- `.env.example` safe and complete enough for M0.

## Security

- no secrets committed;
- no real/private career data fixtures;
- frontend receives no backend secrets;
- local storage/Horizon/internal services not unintentionally public.

## Validation

- backend tests/checks actually executed;
- frontend checks actually executed;
- stack smoke actually executed;
- failures resolved or Phase not declared complete.

## Scope

- no auth functionality beyond framework-neutral bootstrap;
- no Career/Vacancy/Application domain implementation;
- no domain migrations;
- no Phase 09 work started.

# State update

After PASS:

- `STATUS.md`: Phase 08 completed, exact versions/config choices, validation actually run, known limitations;
- `NEXT.md`: canonical Phase 09, expected `09-auth-multi-user`;
- `BLOCKERS.md`: only real blockers.

If M0 cannot actually start, do not advance state to completed.

# Final Report

Keep concise:

1. Result: PASS/PARTIAL/BLOCKED.
2. Files/directories created/changed.
3. Runtime/framework versions actually selected and source of compatibility verification, briefly.
4. Services successfully started.
5. Validation commands actually executed and result.
6. Known limitations/blockers.
7. Exact next phase.

# STOP

After Phase 08 stop.

Do not implement authentication.
Do not create Career/Vacancy/Application features.
Do not start Phase 09.
Do not add speculative infrastructure after M0 acceptance.