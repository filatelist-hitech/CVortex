---
title: CVortex Research Plan
status: draft
owner: project
created: 2026-09-12
updated: 2026-09-12
tags:
  - research
  - planning
  - architecture
  - cvortex
related:
  - README.md
  - RESEARCH-INDEX.md
---

# CVortex Research Plan

## Purpose

Research backlog, который должен быть закрыт до соответствующих architecture/implementation decisions.

Обозначения:

- **P0** — architecture blocker;
- **P1** — implementation blocker;
- **P2** — optimization / later;
- freshness: **CRITICAL / HIGH / MEDIUM / LOW**.

---

# 01. Backend / Runtime

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R01-01 | Какие PHP releases сейчас поддерживаются и каков их support lifecycle? | Определяет безопасную runtime baseline. | PHP version policy | php.net releases/support | HIGH | P0 | yes |
| R01-02 | Какая PHP version совместима с актуальной Laravel release line? | Нельзя выбирать версии независимо. | Runtime compatibility matrix | Laravel + PHP official docs | HIGH | P0 | yes |
| R01-03 | Какие PHP extensions реально нужны выбранному stack? | Влияет на container image и deployment. | PHP image/extensions | official docs, package requirements | MEDIUM | P1 | yes |
| R01-04 | Есть ли ограничения Docker images/tooling на Apple Silicon arm64? | MVP запускается локально на Mac. | Local runtime/container strategy | official image docs, upstream repos | MEDIUM | P1 | yes |
| R01-05 | Какие различия local и будущего Linux/VPS runtime необходимо избежать? | Local-first не должен создать тупик миграции. | Runtime portability constraints | PHP/Docker/Nginx docs | LOW | P1 | no |

# 02. Laravel Ecosystem

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R02-01 | Какая Laravel release line является актуальной и поддерживаемой? | Определяет baseline framework. | Laravel version policy | Laravel official docs/releases | HIGH | P0 | yes |
| R02-02 | Каков официальный support/security lifecycle Laravel? | Нужен upgrade horizon. | Upgrade policy | Laravel official policy | HIGH | P0 | yes |
| R02-03 | Какие first-party возможности покрывают queues, validation, authorization, encryption, rate limiting и filesystem? | Не следует тащить packages для уже решённых framework задач. | Dependency policy | Laravel official docs | HIGH | P1 | yes |
| R02-04 | Какие ecosystem packages действительно потребуются, и насколько они поддерживаются? | Снижает supply-chain и maintenance risk. | Dependency shortlist | official repos, Packagist, releases | HIGH | P1 | yes |
| R02-05 | Есть ли framework-level ограничения для long-running workers/Horizon deployment? | Влияет на queue/runtime operation. | Worker lifecycle | Laravel/Horizon docs | MEDIUM | P1 | yes |

# 03. PostgreSQL

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R03-01 | Какие PostgreSQL versions поддерживаются сейчас? | Нужна поддерживаемая baseline. | PostgreSQL version policy | postgresql.org | HIGH | P0 | yes |
| R03-02 | Какие PostgreSQL capabilities подходят для JSON metadata, full-text search и flexible research data? | Может устранить преждевременные внешние сервисы. | Data modelling/search strategy | PostgreSQL docs | MEDIUM | P1 | no |
| R03-03 | Когда pgvector оправдан, а когда обычного PostgreSQL достаточно? | Проект запрещает отдельную vector DB без необходимости. | Future semantic search strategy | pgvector/PostgreSQL primary docs | HIGH | P2 | no |
| R03-04 | Нужен ли PostgreSQL RLS как дополнительный tenant isolation layer или достаточно application authorization? | Multi-user isolation критичен. | Defense-in-depth strategy | PostgreSQL docs, Laravel patterns | MEDIUM | P1 | no |
| R03-05 | Какая backup/restore стратегия нужна local-first MVP и будущему VPS? | Career/application history нельзя терять. | Backup architecture | PostgreSQL docs | MEDIUM | P1 | no |

# 04. Redis / Horizon

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R04-01 | Какие Redis releases совместимы с выбранным Laravel/Horizon stack? | Queue baseline должен быть совместим. | Redis version | Redis + Laravel docs | HIGH | P0 | yes |
| R04-02 | Какие queue semantics Laravel обеспечивает для retries, timeout и failed jobs? | LLM/import/document jobs должны быть предсказуемыми. | Job contract | Laravel Queue docs | HIGH | P1 | yes |
| R04-03 | Как проектировать idempotency для retried background jobs? | Retry не должен дублировать документы, runs или imports. | Background job conventions | Laravel docs + engineering sources | MEDIUM | P1 | yes |
| R04-04 | Как Horizon управляет несколькими queues/supervisors? | Планируются `critical/default/llm/documents/research/imports`. | Queue topology | Horizon official docs | HIGH | P1 | yes |
| R04-05 | Какие Redis persistence требования нужны для local MVP? | Redis не должен случайно стать source of truth. | Redis persistence/usage policy | Redis docs | MEDIUM | P1 | no |

# 05. Authentication / Authorization

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R05-01 | Какой first-party Laravel auth approach подходит для same-origin Next.js PWA + API? | Нужна минимальная безопасная схема. | Authentication architecture | Laravel docs | HIGH | P0 | yes |
| R05-02 | Session/cookie или token-based auth нужен MVP? | Влияет на CSRF, CORS и client architecture. | Auth transport | Laravel security docs, standards | HIGH | P0 | yes |
| R05-03 | Как безопасно реализовать invite-only registration? | Это утверждённое product requirement. | Invitation lifecycle | Laravel docs, OWASP patterns | MEDIUM | P1 | yes |
| R05-04 | Как реализовать admin/user roles без лишнего RBAC framework? | MVP имеет только две роли. | Authorization model | Laravel policies/gates docs | MEDIUM | P1 | yes |
| R05-05 | Какие automated authorization invariants обязательны для tenant isolation? | Cross-user access является critical threat. | Authorization test policy | OWASP, Laravel docs | MEDIUM | P0 | yes |

# 06. API Architecture

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R06-01 | Какие REST conventions использовать для `/api/v1`? | Frontend/mobile/extension должны использовать один API. | API contract conventions | HTTP/RFC/OpenAPI specs | LOW | P0 | yes |
| R06-02 | Как стандартизировать validation/errors? | Клиент не должен парсить controller folklore. | Error envelope | RFC/standards + framework docs | MEDIUM | P1 | yes |
| R06-03 | Как стандартизировать pagination/filtering/sorting? | Vacancies/applications/research потребуют list endpoints. | Collection API | standards, Laravel docs | MEDIUM | P1 | yes |
| R06-04 | Как представлять asynchronous operations через API? | LLM, imports, documents и research выполняются background jobs. | Async API contract | HTTP/API standards | MEDIUM | P1 | yes |
| R06-05 | Где нужна idempotency на write endpoints? | Browser/import retries не должны создавать дубликаты. | Mutation semantics | HTTP specs, payment/API patterns | MEDIUM | P1 | no |
| R06-06 | Как генерировать/поддерживать OpenAPI без contract drift? | API-first требует надёжного контракта. | OpenAPI tooling | OpenAPI spec, Laravel ecosystem | HIGH | P1 | yes |

# 07. Files / Documents

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R07-01 | Какие maintained PHP libraries подходят для deterministic DOCX generation/templates? | Resume pipeline зависит от DOCX. | DOCX renderer | official repos/docs/licenses | HIGH | P0 | yes |
| R07-02 | Template-based или programmatic DOCX generation предпочтительнее для CV templates? | Влияет на редактируемость и complexity. | Document architecture | library docs + Office format docs | MEDIUM | P1 | yes |
| R07-03 | Как надёжно запускать LibreOffice headless в container environment? | DOCX → PDF является утверждённым направлением. | Document worker runtime | LibreOffice official docs | HIGH | P1 | yes |
| R07-04 | Какие проверки нужны после DOCX/PDF rendering? | Успешный exit code не гарантирует нормальный CV. | Document validation | format specs/tool docs | MEDIUM | P1 | no |
| R07-05 | Какие upload limits/MIME/signature checks нужны? | Uploaded documents считаются hostile input. | Upload security | OWASP, file format docs | HIGH | P0 | yes |
| R07-06 | Какой abstraction boundary нужен для local filesystem → S3/MinIO? | Требуется portability без переписывания domain layer. | StorageProvider contract | Laravel filesystem docs | HIGH | P1 | yes |

# 08. OpenAI API / Model Catalog / Pricing

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R08-01 | Какой OpenAI API surface является рекомендуемым сейчас? | Нельзя строить provider вокруг устаревающего API. | OpenAIProvider interface | OpenAI official docs | CRITICAL | P0 | yes |
| R08-02 | Какие модели доступны через API и каковы их capabilities? | ModelPolicy должен опираться на реальные возможности. | Capability tiers | OpenAI model docs | CRITICAL | P0 | yes |
| R08-03 | Какова текущая цена input/output/cached/reasoning tokens? | Cost-aware routing является core principle. | Cost model | official pricing | CRITICAL | P0 | yes |
| R08-04 | Какие модели поддерживают Structured Outputs / JSON Schema? | Extraction должен быть schema validated. | Structured output policy | OpenAI docs | CRITICAL | P0 | yes |
| R08-05 | Какие reasoning controls доступны и как они тарифицируются? | Нельзя абстрагировать несуществующие knobs. | ModelPolicy schema | OpenAI docs | CRITICAL | P1 | yes |
| R08-06 | Какие rate limits / usage tiers действуют? | Влияет на queue/backoff architecture. | Provider throttling | OpenAI official docs/account docs | CRITICAL | P1 | yes |
| R08-07 | Какие usage fields API возвращает для token/cost accounting? | `llm_runs` должен опираться на реальные telemetry fields. | LLM run accounting | OpenAI API docs | CRITICAL | P1 | yes |
| R08-08 | Какие data retention/privacy options применимы к API? | Через LLM могут проходить PII и career data. | Data handling policy | OpenAI official privacy/API docs | CRITICAL | P0 | yes |

# 09. Provider Abstraction

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R09-01 | Какой минимальный capability set должен иметь `LlmProvider`? | Слишком широкий interface привяжет domain к одному vendor. | Provider contract | provider official APIs + internal requirements | HIGH | P0 | yes |
| R09-02 | Какие capabilities нельзя безопасно нормализовать между providers? | Lowest-common-denominator abstraction тоже вреден. | Capability negotiation | official provider docs | HIGH | P1 | yes |
| R09-03 | Как представить structured output/tool use/streaming/usage в provider-neutral domain? | Эти функции отличаются между vendors. | Response/request DTOs | official APIs | HIGH | P1 | yes |
| R09-04 | Где заканчивается Provider и начинается ModelPolicy/Skill/Agent? | Это фундаментальное разделение CVortex AI architecture. | AI boundaries | project requirements + API research | MEDIUM | P0 | yes |
| R09-05 | Как обрабатывать vendor-specific errors/retries/rate limits? | Domain logic не должна знать SDK exception zoo. | Error normalization | provider docs | HIGH | P1 | yes |

# 10. Prompt Caching / Batch

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R10-01 | Как сейчас работает OpenAI prompt caching? | Может существенно снизить стоимость stable prefixes. | Prompt assembly | OpenAI official docs | CRITICAL | P1 | yes |
| R10-02 | Какие требования существуют к cacheable prefix? | Prompt Registry должен учитывать их заранее. | Prompt layout | official docs | CRITICAL | P1 | yes |
| R10-03 | Как cached tokens отражаются в usage/pricing? | Нужен корректный cost accounting. | `llm_runs` accounting | official docs/pricing | CRITICAL | P1 | yes |
| R10-04 | Какие Batch API capabilities, limits, SLA и pricing действуют? | Bulk research/evals могут быть дешевле interactive calls. | Batch workload strategy | OpenAI official docs | CRITICAL | P1 | no |
| R10-05 | Какие CVortex workloads подходят для Batch, а какие обязаны быть interactive? | Batch нельзя превращать в UX-тормоз. | Workload classification | product requirements + API findings | HIGH | P1 | no |

# 11. Figma / MCP / Design Tokens

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R11-01 | Какие capabilities имеет официальный Figma MCP сейчас? | Нельзя планировать write workflow по старым возможностям. | Figma integration | Figma official developer docs | CRITICAL | P0 | yes |
| R11-02 | Какие MCP capabilities read-only, а какие могут изменять canvas? | Влияет на Phase 07 workflow. | Design automation strategy | official Figma docs | CRITICAL | P1 | yes |
| R11-03 | Как работают Variables и их API/MCP exposure? | Design tokens должны согласовываться с Figma. | Token architecture | Figma official docs | HIGH | P1 | yes |
| R11-04 | Какую роль сейчас играет Code Connect? | Требуется понять design ↔ code contract. | Component mapping strategy | Figma official docs | HIGH | P1 | no |
| R11-05 | Какой canonical token format/tooling использовать? | Нельзя вручную синхронизировать Figma и CSS. | Token source of truth | W3C/community spec + maintained tools | HIGH | P0 | yes |
| R11-06 | Нужна ли двусторонняя token sync или однонаправленный pipeline? | Bidirectional sync может создать conflict hell. | Token synchronization | official/tool docs | MEDIUM | P1 | yes |

# 12. Job Source APIs and Restrictions

Для каждого значимого source необходимо исследовать один и тот же capability matrix:

```text
official API
public/private
authentication
OAuth
feeds
documented rate limits
allowed retrieval methods
robots
Terms of Service
data reuse restrictions
realistic fallback
```

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R12-01 | Какие официальные integration возможности предоставляет HeadHunter? | Ключевой RU vacancy source. | HH adapter scope | HH official developer/ToS | CRITICAL | P0 | yes |
| R12-02 | Что официально разрешают LinkedIn и Indeed? | Scraping assumptions создают legal/technical risk. | Source strategy | official API/ToS/robots | CRITICAL | P0 | yes |
| R12-03 | Какие возможности имеют Habr Career, SuperJob, Glassdoor, Wellfound? | Определяет direct adapters vs manual import. | Source adapter backlog | official docs/ToS | CRITICAL | P1 | yes |
| R12-04 | Какие public job endpoints/feeds предоставляют Greenhouse, Lever, Ashby, SmartRecruiters, Teamtailor, Personio? | ATS-hosted career pages могут быть удобнее job boards. | ATS source adapters | vendor official docs | CRITICAL | P1 | yes |
| R12-05 | Что разрешают RemoteOK, We Work Remotely, Dice, ZipRecruiter? | Нельзя предполагать public API или feed. | Source adapters | official docs/ToS | CRITICAL | P1 | yes |
| R12-06 | Как безопасно обрабатывать generic company career pages? | Universal crawler создаёт SSRF/ToS/security risk. | GenericWebAdapter scope | site ToS/robots + security standards | CRITICAL | P0 | yes |
| R12-07 | Какой fallback использовать, когда automated retrieval запрещён или отсутствует? | Product всё равно должен позволять импорт вакансии. | Paste/file/manual UX | findings across sources | HIGH | P0 | yes |

# 13. Recruitment / ATS / Hiring Practices

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R13-01 | Что современные ATS действительно извлекают из CV? | Нельзя строить продукт на ATS-мифологии. | Resume recommendations | ATS vendor docs + primary studies | HIGH | P0 | yes |
| R13-02 | Какие resume formatting practices стабильно улучшают machine parsing? | Влияет на ATS template. | Resume rendering rules | vendor docs, primary research | HIGH | P1 | yes |
| R13-03 | Как работодатели используют automated screening и AI ranking? | Нужно понимать реальную роль keywords/matching. | Matching/recommendation KB | vendor docs + research | HIGH | P1 | no |
| R13-04 | Насколько cover letters важны в разных рынках/типах компаний? | Нужны разные стратегии, а не один культ письма. | Cover strategy | recruiting studies + employer guidance | HIGH | P1 | no |
| R13-05 | Какие различия существуют между RU/EU/US/UK CV conventions? | CVortex поддерживает разные рынки. | Regional formatting/rules | government/employer/recruitment primary sources | HIGH | P1 | yes |
| R13-06 | Чем различаются fintech/startup/enterprise hiring workflows? | Recruitment KB должна быть contextual. | Research rules dimensions | primary surveys/vendor research | MEDIUM | P2 | no |
| R13-07 | Какие hiring recommendations имеют сильное evidence, а какие лишь recruiter folklore? | Truth-first относится и к Recruitment KB. | Evidence grading | systematic/primary research | MEDIUM | P1 | no |
| R13-08 | Как часто Recruitment KB rules должны пересматриваться? | Hiring practices меняются. | `review_after` policy | evidence freshness analysis | MEDIUM | P1 | no |

# 14. Security / Threat Model

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R14-01 | Какие trust boundaries есть между browser, API, queue, DB, filesystem и LLM providers? | Основа threat model. | Security architecture | OWASP + project data flows | MEDIUM | P0 | yes |
| R14-02 | Как защищать vacancy URL fetch от SSRF? | Vacancy URL является untrusted input. | URL ingestion architecture | OWASP SSRF guidance | HIGH | P0 | yes |
| R14-03 | Как sandbox/validate uploaded DOCX/PDF? | Documents могут быть malicious. | Upload pipeline | OWASP/vendor/file format docs | HIGH | P0 | yes |
| R14-04 | Как предотвращать prompt injection из vacancies/messages/web research? | External text попадает в AI context. | AI security boundary | OWASP LLM guidance + provider docs | HIGH | P0 | yes |
| R14-05 | Как предотвращать stored/reflected XSS из imported content? | В UI будет чужой HTML/text. | Rendering/sanitization rules | OWASP | HIGH | P0 | yes |
| R14-06 | Как гарантировать per-user ownership и защиту от IDOR? | Multi-user isolation обязательна. | Authorization model | OWASP API security + Laravel docs | HIGH | P0 | yes |
| R14-07 | Как хранить BYOK/system API credentials? | Secret leakage критичен. | Credential storage | framework encryption docs + OWASP | HIGH | P0 | yes |
| R14-08 | Какие данные запрещено писать в logs/traces? | Career/PII/secrets могут утечь через observability. | Logging policy | OWASP/privacy guidance | MEDIUM | P1 | yes |
| R14-09 | Какой retention/deletion policy нужен для PII, uploaded documents и recruiter messages? | Данные чувствительные и долгоживущие. | Data lifecycle | privacy/legal/security sources | HIGH | P1 | no |
| R14-10 | Какие supply-chain checks нужны dependencies/containers? | Проект зависит от npm/composer/images. | CI security controls | official registries, OWASP/SLSA | MEDIUM | P1 | no |

# 15. Testing / Tooling

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R15-01 | PHPUnit или Pest лучше соответствует backend test strategy? | Не нужно одновременно плодить два стиля без причины. | Backend test framework | official docs/repos | HIGH | P1 | yes |
| R15-02 | Какие static-analysis levels/tooling реалистичны для Laravel? | Quality gates должны быть полезными, а не декоративными. | PHPStan/Larastan policy | official repos/docs | HIGH | P1 | yes |
| R15-03 | Какие formatter/linter tools нужны PHP и TypeScript? | CI должен иметь deterministic quality gates. | Formatting policy | official tooling docs | HIGH | P1 | yes |
| R15-04 | Какой frontend component test stack актуален? | Версии React/Next могут менять рекомендации. | Frontend tests | official framework/testing docs | HIGH | P1 | yes |
| R15-05 | Подходит ли Playwright как E2E baseline? | PWA workflow нуждается в browser-level validation. | E2E tooling | Playwright official docs | HIGH | P1 | yes |
| R15-06 | Как тестировать API contracts/OpenAPI drift? | API-first без contract tests быстро превращается в лозунг. | Contract testing | OpenAPI/tool docs | MEDIUM | P1 | no |
| R15-07 | Как строить LLM evals без exact-string assertions? | Model outputs недетерминированы. | Eval architecture | provider eval docs + engineering research | HIGH | P0 | yes |
| R15-08 | Какие security checks разумно включить в MVP CI? | Security должна быть частью DoD. | CI security gates | OWASP/tool official docs | HIGH | P1 | no |

# 16. PWA / Frontend

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R16-01 | Какие Next.js/React versions и support matrix актуальны? | Frontend baseline должен быть совместим. | Frontend versions | official Next.js/React docs | HIGH | P0 | yes |
| R16-02 | Какова актуальная роль Tailwind и его integration path с Next.js? | Stack direction уже задан, детали нет. | Styling setup | Tailwind/Next official docs | HIGH | P1 | yes |
| R16-03 | Как лучше реализовать PWA в текущем Next.js ecosystem? | Старые PWA recipes быстро устаревают. | PWA strategy | Next/browser official docs | HIGH | P0 | yes |
| R16-04 | Какие ограничения PWA действуют на iOS/Safari? | iPhone является целевой платформой MVP. | Mobile UX/offline expectations | Apple/WebKit docs | HIGH | P0 | yes |
| R16-05 | Нужен ли MVP offline mode или достаточно installable/responsive PWA? | Offline complexity может быть преждевременной. | Service-worker scope | product requirements + browser docs | MEDIUM | P1 | no |
| R16-06 | Какие component primitive libraries подходят accessibility-first design system? | Не следует строить каждый dialog заново. | UI primitive shortlist | official docs, accessibility evidence | HIGH | P1 | yes |
| R16-07 | Как синхронизировать API server-state без ненужной state-management платформы? | Нужно избежать frontend overengineering. | Client data layer | framework/library official docs | HIGH | P1 | no |

# 17. Licensing / Terms

| ID | Research question | Why it matters | Decision influenced | Preferred sources | Freshness | Priority | Blocking |
|---|---|---|---|---|---|---|---|
| R17-01 | Совместимы ли licenses выбранных Composer/npm dependencies с продуктом? | Нельзя обнаруживать license conflict после реализации. | Dependency acceptance | LICENSE files, SPDX, official repos | HIGH | P0 | yes |
| R17-02 | Какие обязательства создаёт LibreOffice licensing/distribution model? | LibreOffice будет частью document pipeline. | Packaging/deployment | LibreOffice official licensing | MEDIUM | P1 | yes |
| R17-03 | Какие license условия имеют выбранные fonts/icons/assets? | Brand/UI assets должны быть легально распространяемы. | Design asset policy | official license texts | MEDIUM | P1 | yes |
| R17-04 | Какие ограничения имеют Figma API/MCP/Code Connect terms? | Integration не должна нарушать platform terms. | Figma automation scope | Figma official terms/docs | CRITICAL | P1 | yes |
| R17-05 | Какие OpenAI API terms/data terms важны для CVortex? | CV/PII/BYOK затрагивают external provider. | Provider policy | OpenAI official terms/docs | CRITICAL | P0 | yes |
| R17-06 | Какие ToS/data-use ограничения каждого job source влияют на storage/reuse? | Получить данные и иметь право хранить их — разные вещи. | Vacancy ingestion/storage | official ToS/API terms | CRITICAL | P0 | yes |
| R17-07 | Нужен ли automated dependency license scan в CI? | License discipline должна масштабироваться. | CI/tooling | package manager/tool docs | MEDIUM | P2 | no |

---

# Cross-category research questions

Следующие вопросы должны проверяться поперёк нескольких отчётов.

## X-01 — Local-first without local-only

Каждое runtime/storage/tooling решение проверить на возможность переноса:

```text
Mac local Docker Compose
→ Linux VPS/cloud
```

без переписывания business logic.

## X-02 — Deterministic before AI

Для каждой предполагаемой LLM capability проверить:

> Можно ли выполнить задачу надёжнее обычным code / SQL / schema validation / rules?

Если да, LLM не является default solution.

## X-03 — Multi-user isolation

Любая выбранная библиотека, filesystem pattern, queue payload и API contract должны учитывать `user ownership`.

## X-04 — Observability without leakage

Проверять, возможно ли диагностировать:

- request;
- job;
- LLM run;
- import;
- document generation;

без логирования secrets и лишнего PII.

## X-05 — Portability

Provider-specific или vendor-specific capabilities не должны незаметно проникать в core domain model.

---

# Exit criteria for Phase 02

Phase 02 может считаться завершённым, если:

- определены research categories;
- каждому вопросу присвоен стабильный ID;
- определён decision impact;
- указаны preferred sources;
- определена freshness sensitivity;
- установлена priority;
- определён blocking status;
- вопросы распределимы между Phase 03 и Phase 04;
- ни один research question не был молча превращён в architecture decision;
- concrete LLM models/prices не зафиксированы как архитектурные константы;
- product code не создан.