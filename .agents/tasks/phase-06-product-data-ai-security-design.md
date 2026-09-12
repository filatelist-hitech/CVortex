---
title: CVortex Phase 06 — Product / Data / AI / Security Design
status: ready
phase: 06-product-data-ai-security-design
owner: project
created: 2026-09-12
updated: 2026-09-12
tags:
  - task
  - product
  - architecture
  - data
  - ai
  - security
related:
  - ../../PROJECT.md
  - ../state/STATUS.md
  - ../state/NEXT.md
  - ../../docs/03-ADR/INDEX.md
---

# CVortex — Phase 06: Product / Data / AI / Security Design

## Goal

Подготовить целостный design baseline CVortex, достаточный для последующих Phase 07 Design Foundation и Phase 08 Repository Bootstrap, не реализуя product code.

После выполнения Phase 06 должны быть согласованы между собой:

- Product model;
- system architecture;
- conceptual data model;
- AI/LLM architecture;
- provenance model;
- Truth Guard;
- security model;
- runtime AI Skills organization;
- deployment model;
- основные cross-component data flows.

Документация должна позволять следующим фазам работать без повторного проектирования фундаментальных частей системы.

## Execution mode

Следуй `/AGENTS.md`, `.agents/policies/resource-usage.md` и `.agents/workflows/phase-execution.md`.

По умолчанию:

- один агент;
- последовательная работа;
- task-relevant context only;
- без subagents, если они не нужны для корректности;
- без рекурсивной загрузки всех `docs/`, `research/` и `.agents/`;
- targeted validation before broad validation;
- targeted research только при реальном пробеле;
- краткий final report без повторного пересказа созданных документов.

Resource efficiency не может отменять correctness, security, Truth-first, accepted ADR или completion criteria.

## Minimum context before work

Обязательно прочитай:

1. `/PROJECT.md`.
2. `/.agents/state/STATUS.md`.
3. `/.agents/state/NEXT.md`.
4. `/docs/03-ADR/INDEX.md`.
5. Только accepted ADR, непосредственно влияющие на Phase 06.
6. Documentation map/index и только существующие Product / Architecture / Data / AI / Security документы, которые будут изменяться или с которыми нужно обеспечить consistency.
7. Phase 03–04 research только через indexes/links и только по тем вопросам, где evidence нужен для design decision или проверки ADR.

Не перечитывай Phase 03–04 research полностью. Не загружай целиком все каталоги Product / Architecture / Data / AI / Security. Используй indexes, filenames, links и targeted search.

Найди applicable scoped `AGENTS.md` только для каталогов, которые реально собираешься изменять.

## Source of truth and conflicts

Следуй repository instruction priority из `/AGENTS.md`.

Accepted ADR являются обязательными архитектурными constraints в рамках их scope. Не противоречь им молча.

Если обнаружен конфликт между текущей task specification, accepted ADR, research, existing docs или project state:

1. явно зафиксируй конфликт;
2. определи authoritative source по repository instruction priority;
3. не подменяй решение собственной догадкой;
4. если accepted architecture действительно должна измениться, используй новый/amending/superseding ADR согласно repository policy;
5. обновляй зависимую документацию только после фиксации решения.

Не переносить старые гипотезы из bootstrap/master prompts как факты, если они не подтверждены current source of truth.

## Product context

CVortex — персональная Job Search OS.

Ключевые invariants:

- Truth-first;
- traceability;
- consistency-first;
- human approval;
- deterministic before AI;
- provider-independent LLM architecture;
- multi-user isolation;
- local-first;
- API-first;
- security-first;
- documentation-first.

Эта фаза переводит принятые Phase 05 decisions в конкретный system design, но не в migrations, Laravel classes, React components или infrastructure code.

# Scope

Разрешено создавать и изменять design/documentation artifacts внутри:

```text
docs/00-Home/**
docs/01-Product/**
docs/02-Architecture/**
docs/03-ADR/**
docs/04-Data/**
docs/05-API/**
docs/06-AI/**
docs/08-Security/**
docs/09-QA/**
docs/10-Operations/**
.agents/state/**
```

При необходимости допускается обновить `PROJECT.md` и `docs/00-Home/Documentation-Map.md`, только если Phase 06 делает существующую информацию неполной или устаревшей.

Изменения вне scope допускаются только если действительно необходимы для Phase 06. Причину такого изменения укажи в final report.

# Non-goals

На этой фазе НЕ:

- создавать Laravel project;
- создавать Next.js project;
- писать backend/frontend product code;
- создавать migrations;
- создавать physical PostgreSQL schema;
- устанавливать dependencies;
- создавать Docker Compose;
- реализовывать API endpoints;
- писать production runtime prompts;
- реализовывать LLM provider;
- подключать OpenAI SDK;
- создавать реальные queues/jobs;
- реализовывать authentication;
- реализовывать Career Foundation;
- реализовывать vacancy ingestion;
- создавать Figma components;
- начинать Phase 07;
- начинать Phase 08;
- добавлять speculative functionality «на будущее».

Conceptual models, interfaces, contracts, diagrams и pseudostructures допустимы. Production implementation запрещена.

# Product Design

## PRD

Создай/актуализируй authoritative PRD.

Опиши:

- проблему;
- пользователей;
- основные jobs-to-be-done;
- цели продукта;
- non-goals;
- ключевые пользовательские сценарии;
- success criteria;
- ключевые ограничения;
- dependencies;
- основные риски.

PRD не должен превращаться в техническую архитектуру.

## Requirements

Раздели требования минимум на:

- Functional Requirements;
- Non-functional Requirements;
- Security Requirements;
- AI Requirements;
- Data Requirements;
- Privacy Requirements;
- Operational Requirements;
- UX Requirements.

Используй стабильные identifiers, например:

```text
FR-001
NFR-001
SEC-001
AI-001
DATA-001
PRIV-001
OPS-001
UX-001
```

Требования должны быть testable или проверяемыми. Не использовать расплывчатые требования вроде «система должна быть современной» или «AI должен работать хорошо».

## User Flows

Зафиксируй основные flows минимум для:

1. initial onboarding;
2. Career Fact Base;
3. import existing resume;
4. Career Fact confirmation;
5. add vacancy;
6. vacancy analysis;
7. should-I-apply;
8. resume recommendations;
9. approve/edit/reject recommendation;
10. cover letter generation;
11. application preparation;
12. manual application confirmation;
13. application tracking;
14. recruiter conversation import;
15. Employer Memory;
16. interview preparation;
17. interview history;
18. outcome tracking.

Используй Mermaid только там, где он улучшает понимание. Не превращай каждый flow в UI mockup.

## MVP Scope

Чётко раздели:

- MVP;
- Post-MVP;
- Explicitly Out of Scope.

Без отдельного accepted ADR не добавлять:

- Kubernetes;
- microservices;
- Kafka;
- standalone vector DB;
- GraphQL;
- native mobile;
- fine-tuning;
- event sourcing.

## Roadmap

Сформируй dependency-oriented roadmap от текущего состояния до MVP.

Минимальная логическая последовательность:

```text
Foundation
→ Infrastructure
→ Auth
→ Career Foundation
→ Vacancies
→ Matching
→ Application Package
→ Employer Memory
→ Interview
→ Analytics / later capabilities
```

Не изменяй утверждённую последовательность фаз без причины и соответствующей документации.

# Architecture Design

## Architecture Overview

Опиши:

- architectural principles;
- system boundaries;
- frontend/backend boundaries;
- synchronous vs asynchronous processing;
- storage boundaries;
- external integrations;
- AI subsystem;
- document subsystem;
- research subsystem;
- trust boundaries;
- multi-user boundary.

## C4 Context

Создай C4 Context diagram.

Минимально отрази:

- User;
- Admin;
- CVortex;
- LLM providers;
- job/vacancy sources;
- external document/web sources;
- Figma как engineering/design dependency, если применимо;
- future browser/mobile clients только как external/future actors, если действительно нужно.

Не изображай container details на Context level.

## C4 Container

Покажи logical containers согласно accepted architecture.

Минимально рассмотри:

- Next.js frontend / PWA;
- Laravel API;
- Laravel queue workers;
- Redis/Horizon;
- PostgreSQL;
- file storage;
- document rendering subsystem;
- Nginx;
- external LLM providers;
- external vacancy/research sources.

Не изобретай microservices ради диаграммы.

## Components

Спроектируй основные application component boundaries минимум для:

- Career;
- Vacancies;
- Companies;
- Applications;
- Resume;
- Cover Letters;
- Employer Memory;
- Conversations;
- Interviews;
- Research;
- Documents;
- AI Orchestration;
- Truth Guard;
- Audit;
- Authentication / Authorization;
- Files.

Для каждого существенного component опиши:

- responsibility;
- inputs;
- outputs;
- dependencies;
- owned data;
- security boundary;
- events/interactions;
- что должно быть deterministic;
- где допустим LLM.

Не проектируй class-by-class implementation.

## Data Flow

Создай ключевые data-flow diagrams.

### Vacancy flow

```text
external/raw vacancy
→ trusted ingestion boundary
→ raw snapshot
→ parsing/normalization
→ requirements
→ matching
→ recommendation
```

### Career Fact flow

```text
source
→ extraction
→ PENDING fact
→ human review
→ CONFIRMED fact
```

### Generation flow

```text
CONFIRMED facts
→ claims
→ context selection
→ generated content
→ Truth Guard
→ human approval
```

### Application flow

```text
vacancy
→ analysis
→ recommendations
→ approved resume
→ approved cover
→ READY_TO_APPLY
→ manual application
→ status history
```

### Employer Memory flow

Покажи, как previous claims, applications и conversations влияют на следующую generation для той же компании.

## Deployment

Спроектируй Local MVP:

```text
Mac
Docker Compose
Nginx
Next.js
Laravel
PostgreSQL
Redis/Horizon
local storage
LibreOffice headless
```

Опиши future VPS/cloud migration path без redesign core architecture.

Отдельно обозначь:

- persistent volumes;
- backups concept;
- secrets;
- environment-specific configuration;
- external provider connectivity;
- queue workers;
- document conversion.

Не проектируй Kubernetes deployment.

# Data Design

Это conceptual/data architecture phase. Не создавай migrations.

## Conceptual ERD

Создай conceptual ERD в Mermaid.

ERD должен отражать domain concepts, а не каждый SQL column.

Минимально проанализируй:

```text
User
Invitation
UserSetting
CareerProfile
CareerTrack
EmploymentHistory
CareerFact
Achievement
Education
Language
Skill
Claim
ClaimEvidence
Company
CompanyContact
CompanyResearch
Vacancy
VacancySource
VacancySnapshot
VacancyRequirement
Application
ApplicationStatusHistory
ResumeTemplate
ResumeVersion
ResumeChange
CoverLetter
Conversation
ConversationMessage
ConversationFact
Interview
InterviewQuestion
SalaryExpectation
ResearchSource
ResearchFinding
ResearchRule
LlmProvider
ModelPolicy
Skill
SkillVersion
Agent
Workflow
PromptVersion
LlmRun
EvalRun
GeneratedFile
FileVersion
AuditEvent
```

Это input для анализа, не обязательные physical tables 1:1. Нормализуй conceptual model, если сущности дублируют смысл.

## Entities

Для каждой ключевой entity опиши минимум:

- purpose;
- owner;
- lifecycle;
- important state/status;
- provenance requirements;
- mutability;
- retention considerations;
- relation to audit;
- privacy classification.

## Relationships

Зафиксируй:

- cardinality;
- ownership;
- aggregate boundaries, где полезно;
- lifecycle dependency;
- deletion implications;
- cross-user restrictions.

Не использовать cascade delete как магический ответ на всё.

## Ownership Model

CVortex является multi-user системой.

Для каждой private entity определить:

- direct owner;
- indirect owner;
- authorization boundary;
- допустим ли shared/system-owned object;
- правила доступа admin;
- cross-user restrictions.

Базовый invariant:

```text
User A must never gain access
to private resources owned by User B
through direct IDs, enumeration,
nested resources or indirect relationships.
```

Не доверять `user_id`, полученному от frontend. Ownership определяется server-side.

## Provenance Model

Provenance — first-class concept.

Минимальная цепочка:

```text
SOURCE
→ EXTRACTED FACT
→ CONFIRMATION
→ FACT
→ CLAIM
→ GENERATED CONTENT
```

Система должна уметь ответить: «Почему это утверждение появилось в этом резюме?» до исходного подтверждённого evidence.

Для provenance рассмотри:

- source type;
- source identifier;
- source version/snapshot;
- excerpt/reference;
- extractor/skill version;
- prompt version;
- model policy / actual model used;
- actor;
- timestamps;
- confirmation actor;
- confirmation timestamp.

Не смешивать provenance и audit log.

## Audit Model

Определи audit-worthy события минимум для:

- invitation created/revoked/used;
- Career Fact created/confirmed/edited/rejected;
- Claim created/changed;
- ResumeVersion approved;
- CoverLetter approved;
- Application status changed;
- LLM credential added/rotated/deleted;
- critical authorization/admin operations.

Audit log:

- append-oriented;
- не содержит secrets/raw credentials;
- минимизирует PII;
- содержит actor;
- target;
- action;
- timestamp;
- correlation metadata.

# AI Architecture

Не проектируй одного супер-агента.

Чётко раздели:

- LlmProvider;
- ModelRouter;
- ModelPolicy;
- Skill;
- Agent;
- Workflow;
- Tool;
- ContextBuilder;
- Prompt Registry;
- Eval System;
- LLM Run Accounting;
- Truth Guard.

## LlmProvider

Provider abstraction отвечает только за provider-specific capabilities:

- request execution;
- structured output capability mapping;
- tool invocation capability;
- token usage collection;
- provider errors;
- rate-limit metadata;
- provider-specific request translation.

Business logic не должна зависеть напрямую от конкретного SDK.

## ModelRouter

Routing опирается на logical capabilities/tiers, а не hardcoded model names.

Учитывай:

- task complexity;
- semantic difficulty;
- risk;
- context size;
- latency target;
- cost budget;
- structured-output requirement;
- previous validation failures;
- Skill requirements.

Concrete provider/model mapping остаётся configuration.

## ModelPolicy

Опиши logical policies, например:

```text
low-cost-extraction
standard-semantic
high-confidence-reasoning
research
fallback
```

Конкретные модели не фиксировать как вечную архитектурную константу.

## Skill

Skill — versioned runtime capability.

Минимально:

- stable identifier;
- name;
- purpose;
- version;
- input contract;
- output contract;
- default ModelPolicy;
- prompt reference/version;
- validation strategy;
- deterministic pre/post-processing;
- retry/escalation rules;
- security considerations;
- eval coverage;
- deprecation strategy.

## Agent

Agent нужен только для orchestration нескольких Skills/Tools/workflows.

Проверь необходимость кандидатов:

- VacancyAgent;
- CareerProfileAgent;
- ResumeAgent;
- ApplicationAgent;
- ResearchAgent;
- ConversationAgent;
- InterviewAgent.

Не сохраняй лишний Agent ради симметрии.

## Workflow

Workflow описывает последовательность, branching, validation и human approval.

Минимально спроектируй:

- Career Fact Extraction Workflow;
- Vacancy Analysis Workflow;
- Application Preparation Workflow;
- Resume Generation Workflow;
- Cover Letter Workflow;
- Employer Consistency Workflow;
- Interview Preparation Workflow;
- Research Workflow.

## Tool

Tool — ограниченная deterministic capability.

Концептуальные examples:

- controlled database read/write service;
- file parser;
- document renderer;
- web research adapter;
- vacancy adapter;
- schema validator;
- hashing;
- retrieval.

Не давать runtime LLM unrestricted filesystem/database/arbitrary network access.

## ContextBuilder

ContextBuilder должен:

- выбирать минимальный необходимый context;
- учитывать Skill/Workflow;
- учитывать user ownership;
- учитывать Employer Memory;
- исключать irrelevant data;
- соблюдать context budget;
- разделять trusted instructions и untrusted content;
- маркировать provenance;
- предотвращать cross-user leakage.

Никогда автоматически не отправлять модели всю пользовательскую БД.

## Prompt Registry

Определи:

- canonical source;
- prompt identity;
- versions;
- immutable historical versions;
- active version;
- relation to SkillVersion;
- deployment/update process;
- rollback;
- eval linkage.

Stable static prompt prefix должен позволять provider caching, где возможно. Dynamic user/context data хранится отдельно от stable instructions.

## Eval System

Не использовать exact-string tests как основной способ проверки generative output.

Минимально предусмотреть:

- golden fixtures;
- schema validation;
- invariant checks;
- regression datasets;
- prompt comparison;
- model-policy comparison;
- cost comparison;
- latency comparison;
- truthfulness evaluation;
- provenance validation;
- security/adversarial fixtures.

Ключевые invariants:

- no unsupported candidate fact;
- no CONFIRMED fact created automatically;
- generated claims traceable;
- employer consistency preserved;
- structured output schema valid;
- language constraints preserved;
- salary/critical user values not silently changed;
- cross-user data never enters context.

## LLM Run Accounting

Для каждого runtime LLM invocation концептуально хранить:

- run id;
- user;
- application/vacancy context where applicable;
- workflow;
- agent;
- skill;
- skill version;
- prompt version;
- provider;
- model identifier;
- model policy;
- reasoning/configuration level where available;
- input tokens;
- cached input tokens where available;
- output tokens;
- reasoning tokens where available;
- estimated/actual cost;
- latency;
- status;
- retry count;
- validation result;
- error category;
- timestamps.

Предусмотреть aggregation:

- cost per user/application/vacancy/workflow/skill/provider/model;
- latency by skill;
- validation failure rate;
- escalation rate.

Не логировать API keys/secrets.

# Truth-first invariants

## FACT → CLAIM → CONTENT

Это обязательный system invariant CVortex.

### FACT

Career Fact — атомарное утверждение о кандидате.

Lifecycle минимум:

```text
PENDING
CONFIRMED
REJECTED
DEPRECATED
```

AI extraction может создать только `PENDING`.

AI не имеет права самостоятельно переводить Career Fact в `CONFIRMED`.

### CLAIM

Claim — допустимая формулировка, построенная на одном или нескольких `CONFIRMED` Career Facts.

Claim обязан иметь provenance.

Claim не может повышать степень уверенности относительно evidence. Знакомство с технологией нельзя превращать в production experience.

### GENERATED CONTENT

Candidate-specific generated content может использовать только допустимые Claims.

Каждое candidate-specific statement должно быть traceable:

```text
Generated Content
→ Claim
→ CONFIRMED Career Fact(s)
```

## STRICT Truth Guard

Truth Guard — cross-cutting validation service/policy, а не AI-персонаж.

В STRICT mode:

```text
candidate-specific generated statement
WITHOUT valid provenance
=
BLOCK
```

Проверять минимум:

- provenance completeness;
- fact status;
- semantic overstatement;
- unsupported skills;
- invented dates/durations;
- invented responsibilities;
- invented achievements;
- employer consistency;
- salary/availability consistency where applicable;
- contradictions with confirmed previous statements.

При неоднозначности не угадывать.

Conceptual outcomes:

```text
PASS
BLOCK
USER_RESOLUTION_REQUIRED
```

LLM может помогать обнаруживать semantic conflict, но deterministic invariants проверяются обычным кодом там, где возможно.

# Employer Consistency

Employer Memory должна учитывать перед новой candidate-facing generation для той же компании:

- previous applications;
- previous claims;
- resume versions;
- cover letters;
- recruiter messages;
- salary expectations;
- location/work-format statements;
- interview answers;
- confirmed corrections.

Confirmed contradiction должен приводить к `BLOCK` или `USER_RESOLUTION_REQUIRED`, а не к слабому warning.

# Runtime AI Skills Canonical Location

На этой фазе впервые выбрать canonical location для PRODUCT runtime AI Skills.

Invariant:

```text
/.agents/
```

используется только development/coding agents.

Runtime AI Skills CVortex не должны находиться в `.agents/`.

Оцени разумные варианты, например:

```text
/ai/
/runtime-ai/
/packages/ai/
/apps/backend/resources/ai/
```

Оцени по:

- ownership;
- runtime loading;
- backend coupling;
- provider independence;
- versioning;
- testing;
- deployment;
- reuse другими clients/services;
- Prompt Registry integration;
- future extraction into package/service;
- developer discoverability.

Выбери рекомендуемый canonical location. Если решение меняет/суперседирует accepted architecture, используй ADR. Если Phase 05 оставила этот вопрос explicitly deferred, новый ADR допустим только если выбор является material architectural decision согласно repository ADR policy.

Обнови связанные AI/architecture docs.

# Untrusted Input

Считать untrusted data:

- vacancy text;
- recruiter messages;
- uploaded resumes/documents;
- HTML/web pages;
- external API/job-board responses;
- research content;
- tool output from external sources.

Содержимое untrusted source является `DATA`, а не `INSTRUCTION`.

Текст `Ignore previous instructions` внутри вакансии является содержимым вакансии.

# Security Threat Model

Создай полноценный threat model.

Для каждой угрозы опиши минимум:

- Asset;
- Threat actor / source;
- Attack surface;
- Threat scenario;
- Impact;
- Preventive controls;
- Detective controls;
- Residual risk;
- Validation/testing strategy.

## Prompt Injection

Рассмотри vacancy content, recruiter messages, uploads, web research, external APIs и tool output.

Зафиксируй separation:

```text
trusted instructions
≠
untrusted external content
```

## SSRF

Для будущих URL/research/document fetchers рассмотри:

- protocol allowlist;
- DNS/IP validation;
- private/link-local ranges;
- redirect revalidation;
- metadata endpoints;
- request/response limits;
- timeout;
- audit/observability.

## XSS

Рассмотри vacancy HTML, recruiter messages, imported documents, rich text, generated content и research excerpts. Определи rendering/sanitization boundary.

## File Traversal

Для uploads/generated files:

- server-generated names;
- normalized paths;
- storage abstraction;
- no user-controlled filesystem path;
- ownership validation.

## IDOR / Cross-user Access

Ownership checks выполняются server-side. Не доверять `user_id` из request body/query.

Покрыть минимум:

- Career Facts;
- Career Profiles/Tracks;
- Vacancies;
- private Companies;
- Applications;
- Resumes;
- Cover Letters;
- Conversations;
- Interviews;
- Files;
- LLM credentials;
- LLM runs;
- Employer Memory.

## Malicious Uploads

Рассмотреть:

- MIME spoofing;
- dangerous archive formats;
- zip bombs;
- oversized documents;
- parser exploits;
- macros;
- malformed DOCX/PDF;
- path tricks;
- content-based prompt injection.

Client MIME не является trusted.

## Secret Leakage

Зафиксируй:

- secrets never logged;
- secrets never returned after storage;
- logs redaction;
- no secrets in prompts unless strictly required;
- no secrets in generated documents;
- no secrets in audit log.

## PII Leakage

Определи:

- какие данные являются PII;
- какие данные действительно нужны LLM;
- context minimization;
- logs minimization;
- export/deletion considerations;
- cross-provider implications.

## LLM Credentials

Для system-managed и BYOK credentials спроектируй:

- encrypted at rest;
- masked presentation;
- no frontend round-trip after storage;
- rotation;
- deletion;
- access boundary;
- audit events without secret value;
- log redaction.

# Deterministic Before AI

Для каждого AI component проверь, нельзя ли решить часть надёжнее обычным кодом/schema/SQL/rules engine.

Предпочитай deterministic implementation для:

- ownership;
- authorization;
- status transitions;
- provenance existence;
- hash calculation;
- exact duplicate detection;
- schema validation;
- required fields;
- Claim → Fact linkage;
- file path rules;
- audit events;
- token/cost arithmetic;
- structured contradiction checks.

LLM использовать для semantic tasks, где deterministic approach недостаточен.

# Research

Phase 06 в основном использует завершённые Phase 03–05.

Не повторяй broad research.

Targeted research разрешён только если одновременно:

1. есть реальный design gap;
2. gap влияет на correctness;
3. ответ зависит от актуального внешнего состояния;
4. accepted ADR не закрывает вопрос.

Используй source priority из `.agents/workflows/research.md`.

Если новое evidence противоречит accepted ADR, не меняй ADR молча.

# API Design Boundaries

Не проектируй полный OpenAPI contract без необходимости.

Architecture должна определить:

- API-first boundary;
- versioning direction, если уже принято ADR;
- frontend/backend separation;
- future mobile/browser-extension compatibility;
- authorization boundary;
- async operation representation;
- API ownership model.

Подробные endpoints могут появляться в bounded implementation phases.

# Expected Documentation

Минимально Phase 06 должна покрыть:

```text
docs/01-Product/
  PRD
  Requirements
  User Flows
  MVP Scope
  Roadmap

docs/02-Architecture/
  Overview
  C4 Context
  C4 Container
  Components
  Data Flow
  Deployment

docs/04-Data/
  ERD
  Entities
  Relationships / Data Model
  Ownership
  Provenance
  Audit

docs/06-AI/
  AI Architecture
  Model Routing
  Model Policy
  Skills
  Agents
  Workflows
  Tools
  Context Builder
  Prompt Registry
  Eval System
  LLM Accounting
  Truth Guard

docs/08-Security/
  Threat Model
```

Не создавай отдельный файл для каждого подзаголовка, если это ухудшает navigation. Предпочитай небольшое число хорошо структурированных authoritative documents.

# ADR

Не создавай ADR просто потому, что появился новый document.

Проверить необходимость ADR минимум для Runtime AI Skills canonical location и любого material architecture question, реально не закрытого Phase 05.

Если принятое решение заменяет старое, не переписывай историю: используй amending/superseding ADR и обнови indexes/links.

# Validation

Это documentation/design phase. Не создавай бессмысленные unit tests для Markdown.

## Documentation consistency

Проверь:

- новые authoritative docs доступны из map/index;
- internal links не сломаны;
- frontmatter соответствует policy;
- Mermaid валиден, если repo предоставляет validator;
- terminology согласована с Glossary;
- accepted ADR не противоречат architecture docs;
- Product Requirements согласуются с MVP Scope;
- ERD согласуется с entity definitions;
- AI architecture согласуется с FACT → CLAIM → CONTENT;
- threat model согласуется с trust boundaries.

## Architecture consistency

Проверь:

- C4 Context и Container не противоречат;
- Components соответствуют Container boundaries;
- Data Flow соответствует conceptual ERD;
- Deployment соответствует local-first ADR;
- provider independence сохранена;
- runtime AI Skills отделены от `.agents/`.

## Truth-first consistency

Проверь минимум:

```text
PENDING fact cannot be used as confirmed evidence
Generated Content must trace to Claim
Claim must trace to CONFIRMED fact
AI cannot auto-CONFIRM fact
missing provenance must not silently pass Truth Guard
```

## Security consistency

Проверь coverage threat model по:

- prompt injection;
- SSRF;
- XSS;
- path traversal;
- IDOR;
- cross-user leakage;
- malicious files;
- secrets;
- PII;
- LLM credentials.

Запусти repository-required validation commands, если они существуют. Не заявляй проверку как выполненную, если фактически её не запускал.

# Completion Criteria

Phase 06 считается завершённой только если все применимые пункты можно оценить PASS/FAIL.

## Product

- PRD существует и согласуется с `PROJECT.md`.
- Requirements имеют stable identifiers.
- User Flows покрывают основные MVP workflows.
- MVP Scope отделён от Post-MVP/Out of Scope.
- Roadmap отражает dependency order.

## Architecture

- Architecture Overview существует.
- C4 Context существует.
- C4 Container существует.
- основные components определены.
- ключевые data flows задокументированы.
- local deployment спроектирован.
- future VPS/cloud migration path описан.
- premature distributed architecture не добавлена.

## Data

- conceptual ERD существует.
- key entities определены.
- relationships описаны.
- ownership model существует.
- provenance model существует.
- audit model существует.
- migrations не созданы.
- physical DB schema не реализована.

## AI

- LlmProvider спроектирован.
- ModelRouter спроектирован.
- ModelPolicy спроектирован.
- Skill определён.
- Agent определён.
- Workflow определён.
- Tool определён.
- ContextBuilder определён.
- Prompt Registry определён.
- Eval System определён.
- LLM run accounting определён.
- business logic не зависит от конкретного provider SDK.
- concrete model mapping не зафиксирован как вечная константа.

## Truth-first

- FACT → CLAIM → CONTENT явно зафиксирован.
- Fact lifecycle определён.
- AI extraction создаёт только PENDING.
- AI не может автоматически CONFIRM fact.
- Claim требует provenance к CONFIRMED facts.
- Generated Content требует traceability.
- STRICT Truth Guard определён.
- missing provenance приводит к BLOCK или USER_RESOLUTION_REQUIRED.
- confirmed employer contradiction является blocking invariant.

## Runtime Skills

- canonical directory для runtime PRODUCT AI Skills выбран.
- runtime Skills отделены от `.agents/`.
- выбор документирован.
- ADR создан только если решение действительно требует ADR.

## Security

- threat model существует.
- prompt injection рассмотрен.
- SSRF рассмотрен.
- XSS рассмотрен.
- path traversal рассмотрен.
- IDOR рассмотрен.
- cross-user access рассмотрен.
- malicious uploads рассмотрены.
- secret leakage рассмотрен.
- PII leakage рассмотрен.
- LLM credentials рассмотрены.
- trust boundaries отражены в architecture.

## Consistency

- docs не противоречат accepted ADR.
- C4 / Components / Data Flow / ERD согласованы.
- documentation map/index обновлён.
- ADR index обновлён при необходимости.
- project state обновлён.

## Scope

- product code не создан.
- migrations не созданы.
- dependencies не установлены.
- Phase 07 не начата.
- Phase 08 не начата.

Если обязательный пункт FAIL, не объявляй Phase 06 полностью завершённой.

# State update

После успешного выполнения обнови:

```text
.agents/state/STATUS.md
.agents/state/NEXT.md
.agents/state/BLOCKERS.md
```

`STATUS.md` должен отражать фактические artifacts, ADR, validations и limitations.

`NEXT.md` должен содержать только canonical Phase 07 name. Используй существующее repository naming convention; ожидаемое имя: `07-design-foundation`.

`BLOCKERS.md` содержит только реальные blockers.

# Final Report

Выведи кратко, не повторяя содержание design documents:

1. Result: `PASS`, `PARTIAL` или `BLOCKED`.
2. Files created/changed.
3. Material decisions: только новые/amended/superseded ADR и Runtime AI Skills canonical location.
4. Validation actually executed.
5. Blockers/limitations.
6. State transition: completed phase и exact next phase.

Не заявляй test/validation как successful, если он реально не выполнялся.

# STOP

После завершения Phase 06 остановись.

Не переходи к Phase 07.
Не создавай Figma Design System.
Не создавай repository bootstrap.
Не создавай Laravel/Next.js projects.
Не создавай Docker Compose.
Не создавай migrations.
Не устанавливай dependencies.
Не начинай implementation на основании только что созданного design.

Следующая работа выполняется отдельным bounded task после независимого review Phase 06.