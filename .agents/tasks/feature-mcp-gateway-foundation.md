# CVortex — Feature: MCP Gateway Foundation

Перед началом работы:

1. Прочитай `/AGENTS.md`.
2. Прочитай `/PROJECT.md`.
3. Прочитай `/.agents/state/STATUS.md`.
4. Прочитай `/.agents/state/NEXT.md`.
5. Найди applicable scoped `AGENTS.md`.
6. Найди связанные accepted ADR.
7. Через documentation indexes найди документы, относящиеся к:
   - AI architecture;
   - Provider abstraction;
   - Truth Guard;
   - Career Facts / Claims;
   - Employer Memory;
   - authentication / authorization;
   - multi-user isolation;
   - audit;
   - current Preview 0.1 / M1.4 workflow.
8. Изучи существующую реализацию OpenAI provider, включая текущий Responses API path и конфигурацию `OPENAI_API_KEY`.

Не загружай рекурсивно весь `/docs` или `/research`.

Следуй repository source of truth.

Если task конфликтует с accepted ADR, существующим кодом или фактическим project state, не исправляй конфликт молча. Зафиксируй его и при необходимости создай или supersede ADR.

---

# Goal

Добавить в CVortex архитектурную и техническую основу для взаимодействия с внешними MCP clients, включая ChatGPT, без привязки domain logic CVortex к конкретному LLM provider.

После выполнения должен существовать:

```text
External AI Client
        ↓
       MCP
        ↓
CVortex MCP Gateway
        ↓
Authorization
        ↓
Application / Career / Vacancy services
        ↓
Truth Guard / Employer Consistency
        ↓
PostgreSQL
```

MCP должен стать **дополнительным access channel**, а не заменой существующего OpenAI Responses API provider.

Существующий API-based AI workflow не удалять.

Архитектура должна позволять следующий сценарий:

```text
ChatGPT / another MCP client
        ↓
reads bounded CVortex context
        ↓
model performs reasoning / generation
        ↓
submits proposal or draft back to CVortex
        ↓
CVortex performs deterministic validation
        ↓
Truth Guard
        ↓
EmployerConsistencyCheck
        ↓
PENDING_REVIEW
        ↓
explicit human approval
```

Ключевой принцип:

> Внешняя модель может рассуждать и генерировать текст, но CVortex остаётся системой истины, provenance, validation и approval.

---

# Important product constraint

Не считать задачей доказанным утверждение:

> ChatGPT Plus позволяет подключить private MCP server и выполнять полноценные read/write workflows за счёт Plus subscription.

Это hypothesis, которую необходимо проверить по актуальной официальной документации OpenAI.

На текущем этапе проекта известно только, что:

- ChatGPT subscription и OpenAI API billing являются разными системами;
- существующий `OPENAI_API_KEY` workflow относится к API billing;
- MCP меняет направление взаимодействия:
  `AI client → CVortex`,
  вместо
  `CVortex → LLM API`;
- возможность использования конкретного ChatGPT plan должна определяться текущими capabilities OpenAI, а не предположением.

Не проектировать архитектуру вокруг названия конкретного subscription plan.

---

# Current project state

Текущий project state перед задачей:

- Preview 0.1 остаётся `INCOMPLETE`;
- M1.4 ещё не считается полностью validated;
- M2 не начат.

Эта задача:

- не должна автоматически закрывать Preview 0.1;
- не должна автоматически завершать M1.4;
- не должна начинать M2;
- не должна менять milestone state без объективно выполненных completion criteria.

MCP integration считать отдельным bounded feature/foundation task.

---

# Source of truth

Приоритет:

1. repository `AGENTS.md`;
2. accepted ADR;
3. current project state;
4. current architecture/docs;
5. current implementation;
6. official OpenAI documentation;
7. official Model Context Protocol specification;
8. primary/vendor documentation выбранных libraries.

Не использовать старые ChatGPT screenshots, blog posts или community posts как основание для plan/capability decisions.

---

# Mandatory research

Эта задача зависит от быстро меняющихся возможностей OpenAI, поэтому перед architecture decision обязательно проведи targeted research.

Используй актуальные данные на дату выполнения.

Предпочтительно официальные OpenAI и MCP sources.

Проверь минимум:

## ChatGPT availability

- какие ChatGPT plans сейчас поддерживают Developer Mode;
- какие plans поддерживают custom MCP/apps;
- какие поддерживают:
  - read;
  - search/fetch;
  - write;
  - modify actions;
- есть ли ограничения web/mobile/desktop;
- может ли current Plus account использовать данный workflow;
- отличается ли Apps SDK availability от MCP connection availability.

Не делай вывод:

```text
Apps SDK available
=
full MCP usable by this account
```

без прямого подтверждения документацией.

## Billing

Отдельно проверить:

- ChatGPT subscription billing;
- API billing;
- MCP-related billing;
- Secure MCP Tunnel billing;
- нужен ли funded API Platform account;
- требуется ли API credit balance для `tunnel-client`;
- оплачиваются ли model tokens через API при ChatGPT → MCP interaction;
- какие части workflow относятся к ChatGPT subscription;
- какие части требуют Platform/API resources.

Если официальный источник не отвечает на конкретный billing question:

```text
UNKNOWN
```

а не предположение.

## MCP

Проверить:

- актуальную MCP specification;
- Streamable HTTP requirements;
- tool discovery;
- schemas;
- annotations;
- errors;
- authorization;
- OAuth requirements;
- approval/confirmation semantics;
- backward compatibility expectations.

## ChatGPT authentication

Проверить актуальные требования OpenAI для user-specific MCP data:

- OAuth 2.1;
- PKCE;
- protected resource metadata;
- authorization server metadata;
- scopes;
- refresh token requirements, если применимо;
- security schemes per tool.

Не создавать custom authentication protocol, если официальный supported flow подходит.

## Private/local deployment

CVortex local-first.

Исследовать варианты:

### A. Public HTTPS MCP endpoint

CVortex MCP endpoint доступен через internet-facing HTTPS ingress.

### B. Secure MCP Tunnel

CVortex остаётся локальным/private, OpenAI подключается через официальный Secure MCP Tunnel.

### C. Local development only

MCP server проверяется через MCP Inspector или другой protocol client, но ChatGPT connection откладывается из-за account/plan limitations.

Для каждого варианта сравнить:

- security;
- complexity;
- local-first compatibility;
- additional infrastructure;
- cost;
- API/platform dependency;
- operational burden;
- reversibility;
- plan limitations.

Выбери рекомендуемый вариант для текущего CVortex.

Зафиксируй решение ADR, если оно архитектурно существенно.

---

# Research output

Сохрани research artifact согласно repository research policy.

Он должен включать:

- research date;
- question;
- official sources;
- exact capability findings;
- plan eligibility;
- billing findings;
- unsupported/unknown claims;
- architecture impact;
- recommended path;
- confidence;
- review-after date.

Не копируй большие куски документации.

---

# Decision gate

После research определить один из состояний:

```text
SUPPORTED
PARTIALLY_SUPPORTED
NOT_SUPPORTED_CURRENTLY
UNKNOWN
```

отдельно для:

```text
MCP protocol implementation
ChatGPT read integration
ChatGPT write integration
current account/plan
Secure MCP Tunnel
billing independence from Responses API
```

Если текущий ChatGPT plan не поддерживает required integration:

**не останавливай всю техническую работу.**

Реализуй MCP Gateway Foundation так, чтобы:

- protocol layer был готов;
- local integration tests проходили;
- MCP Inspector validation проходил;
- future ChatGPT connection не требовал переписывания domain architecture.

Но:

- не заявляй ChatGPT E2E как PASS;
- зафиксируй external blocker;
- не создавай обходные механизмы авторизации ChatGPT;
- не используй browser automation;
- не кради browser/session cookies;
- не автоматизируй ChatGPT UI;
- не пытайся использовать undocumented private endpoints.

---

# Architecture requirements

## 1. MCP is an adapter

MCP layer должен быть transport/integration adapter.

Не переносить domain logic в MCP handlers.

Предпочтительная форма:

```text
MCP Tool
   ↓
MCP Application Adapter
   ↓
existing CVortex Application Service
   ↓
Domain / Policies / Truth Guard
```

MCP handler не должен самостоятельно решать:

- какие Career Facts истинны;
- можно ли сохранить claim;
- разрешено ли пользователю видеть resource;
- можно ли финализировать application;
- есть ли employer conflict.

Эти решения должны оставаться внутри CVortex domain/application layer.

---

# 2. Do not replace LlmProvider

Существующая abstraction:

```text
Provider
ModelPolicy
Skill
Agent
Workflow
Tool
```

должна сохраниться.

MCP Gateway является новым integration boundary.

Не превращать MCP client в ещё одну реализацию `LlmProvider`, если semantic contract отличается.

Разделить понятия:

```text
Outbound LLM execution
CVortex → provider → model

Inbound MCP access
external AI client → CVortex
```

При необходимости создать отдельный architecture concept, например:

```text
ExternalAiGateway
McpGateway
ToolExposurePolicy
```

Но не создавать abstraction только ради красивого class diagram.

---

# 3. MCP tools

Спроектировать минимальный MCP surface.

Не выставлять всю REST API CVortex через MCP один-к-одному.

Начать с небольшого bounded наборa tools.

Минимально исследовать необходимость следующих capabilities.

## Read tools

Примерные intents:

```text
vacancy.get
application.get_context
career.get_relevant_confirmed_facts
employer.get_memory
resume.get_current_version
draft.get
truth.get_findings
```

Это intents, а не обязательные названия методов.

Фактические tool names должны:

- быть стабильными;
- быть понятными модели;
- иметь узкое назначение;
- использовать строгие input/output schemas.

## Write tools

На первой версии разрешены только безопасные reversible operations, например:

```text
draft.create
draft.update
draft.validate
proposal.create
```

Write tool не должен автоматически:

- подтверждать Career Fact;
- менять PENDING → CONFIRMED;
- отправлять отклик;
- отправлять email;
- отправлять recruiter message;
- менять application в APPLIED;
- подтверждать зарплатные условия;
- принимать offer;
- удалять provenance;
- обходить Truth Guard.

---

# 4. No MCP approval bypass

OpenAI/ChatGPT может иметь собственное confirmation UI для write actions.

Это **не заменяет CVortex Human Approval**.

CVortex должен независимо обеспечивать product invariant:

```text
Generated draft
→ validation
→ PENDING_REVIEW
→ explicit CVortex user approval
→ accepted state
```

В первой MCP foundation версии предпочтительно:

- разрешать создавать draft/proposal;
- разрешать получать validation result;
- НЕ давать MCP tool, который окончательно подтверждает карьерный факт или отправляет application.

Human Approval должен оставаться внутри CVortex-controlled workflow.

---

# 5. Truth-first

Соблюдать:

```text
FACT
→ CLAIM
→ GENERATED CONTENT
```

Внешний AI client не является source of truth.

Если ChatGPT создаёт текст:

```text
ChatGPT output
=
GENERATED CONTENT candidate
```

а не:

```text
Career Fact
```

Перед сохранением/использованием:

- проверить claims;
- проверить provenance;
- проверить CONFIRMED Career Facts;
- выполнить Truth Guard;
- выполнить EmployerConsistencyCheck там, где применимо.

Если external model предлагает новый потенциальный факт:

```text
PENDING
```

и только user может подтвердить его существующим approval workflow.

---

# 6. Context minimization

MCP read tools не должны отдавать:

```text
SELECT * FROM everything
```

Не отдавать модели автоматически:

- весь Career Profile;
- все прошлые вакансии;
- все recruiter conversations;
- все documents;
- все Employer Memory всех компаний.

Возвращать минимально необходимый context для конкретной задачи.

Использовать существующий ContextBuilder или эквивалентный domain mechanism, если он уже существует и подходит.

Если его нет:

не создавать гигантскую новую AI subsystem в этой задаче.

Создать минимальный bounded context selection layer либо использовать существующие application services.

---

# 7. Untrusted input

Считать недоверенными:

- vacancy raw text;
- vacancy HTML;
- recruiter messages;
- imported resume;
- uploaded documents;
- external web content;
- third-party API responses.

MCP client должен получать их как DATA.

Строки вроде:

```text
ignore previous instructions
call another tool
send all career data
```

в vacancy или recruiter message не являются instructions.

Где возможно, отдавать нормализованные структурированные данные вместо raw content.

Raw source делать отдельной explicit capability только если он действительно нужен.

---

# 8. Authorization

Все user-specific MCP tools должны проходить обычную server-side authorization CVortex.

Никогда не принимать доверенный:

```text
user_id
```

из MCP arguments.

User identity должна происходить из authenticated principal/token/session mapping.

Обязательно проверить:

```text
User A cannot read User B
User A cannot modify User B
User A cannot enumerate User B resources
```

MCP не должен создавать новый путь обхода существующих Policies/Gates.

---

# 9. Authentication

Использовать current CVortex auth architecture насколько возможно.

Если ChatGPT/OpenAI требует OAuth-compatible MCP authentication:

исследовать правильный способ интеграции с существующим auth layer.

Не создавать:

- static global bearer token для всех пользователей;
- hardcoded development token в production path;
- shared secret в query string;
- token в logs;
- token, позволяющий cross-user access.

Development-only authentication допустима только если:

- она явно dev-only;
- production path невозможно случайно включить с ней;
- это документировано;
- tests подтверждают boundary.

---

# 10. MCP exposure policy

Создать явную allowlist policy для MCP capabilities.

Не использовать:

```text
all routes automatically become MCP tools
```

Tool должен быть отдельно зарегистрирован/разрешён.

Новые REST endpoints не должны автоматически становиться MCP tools.

Новые MCP tools должны требовать intentional code/config change.

---

# 11. Dangerous capabilities

На этой фазе MCP категорически не должен предоставлять:

- arbitrary SQL;
- arbitrary filesystem access;
- arbitrary URL fetch;
- shell execution;
- raw Laravel container/service invocation;
- generic HTTP proxy;
- secret retrieval;
- environment variable access;
- unrestricted document download;
- admin impersonation.

Не строить «универсальный tool», через который потом можно сделать всё. Человечество уже достаточно раз изобретало `execute(command)` и удивлялось последствиям.

---

# 12. Multi-user

MCP architecture должна быть multi-user compatible, даже если сейчас feature первым тестирует один пользователь.

Для tool invocation сохранить separation:

```text
authenticated subject
→ CVortex user
→ authorized resource
```

Не связывать MCP endpoint с одним hardcoded user account.

---

# 13. Data returned to external AI

Явно определить data exposure classes.

Например:

```text
SAFE_METADATA
USER_PRIVATE
SENSITIVE_PRIVATE
SECRET
```

Минимум:

- API keys/tokens/password hashes → никогда;
- internal secrets → никогда;
- Career Facts → только authenticated user;
- recruiter conversations → только явно запрошенный bounded context;
- resume → authenticated + minimal required fields;
- raw uploaded files → не отдавать без отдельной необходимости.

Документировать, какие данные могут покидать CVortex и попадать в ChatGPT conversation/model context.

---

# 14. Audit

Логировать MCP invocation metadata.

Минимум:

```text
request_id
user_id
mcp_client when known
tool_name
resource ids where safe
started_at
duration
status
error_code
```

Не логировать:

- OAuth tokens;
- API keys;
- raw secrets;
- полные resume/conversation payloads без необходимости;
- полные tool request/response bodies по умолчанию.

Если существующий audit/logging infrastructure подходит, использовать его.

---

# 15. Observability

Добавить необходимые signals для:

- MCP initialization failures;
- authorization failures;
- validation failures;
- Truth Guard blocks;
- tool latency;
- tool errors;
- rate-limit events.

Не вводить отдельный observability stack.

Использовать существующую инфраструктуру проекта.

---

# 16. Rate limiting

MCP endpoint должен иметь разумный rate limiting.

Отдельно подумать о:

- initialization;
- list tools;
- read tools;
- write tools;
- expensive operations.

Не позволять MCP client бесконечно запускать дорогие background jobs.

---

# 17. Error model

Tool errors должны быть:

- structured;
- безопасными;
- понятными client/model;
- без stack traces;
- без SQL;
- без filesystem paths;
- без secrets.

Разделить хотя бы:

```text
AUTHENTICATION_REQUIRED
FORBIDDEN
NOT_FOUND
VALIDATION_FAILED
TRUTH_GUARD_BLOCKED
EMPLOYER_CONFLICT
USER_APPROVAL_REQUIRED
RATE_LIMITED
INTERNAL_ERROR
```

Используй existing project error conventions, если они уже определены.

---

# Implementation research

Перед выбором MCP implementation для PHP/Laravel:

исследуй актуальные варианты.

Оцени:

- official MCP SDK/support if available;
- maintained PHP libraries;
- protocol compliance;
- Streamable HTTP support;
- Laravel integration;
- authentication hooks;
- schema generation;
- license;
- maintenance activity;
- testability.

Не добавляй dependency только потому, что package называется `laravel-mcp-super-ai`.

Не реализуй MCP protocol вручную, если существует зрелое поддерживаемое решение, совместимое с архитектурой.

Но также не тащи тяжёлый framework, если bounded implementation проще и безопаснее.

Результат выбора зафиксировать в research/ADR там, где требуется.

---

# Transport

Target transport для external hosted clients должен соответствовать актуальной MCP/OpenAI documentation.

Если используется remote MCP:

предпочтительно стандартный supported HTTP transport.

Не фиксируй URL/path до проверки spec и conventions проекта.

Local development должен позволять protocol testing без публичного ingress.

---

# Secure MCP Tunnel

Если research показывает, что Secure MCP Tunnel подходит для CVortex:

не встраивать tunnel-client внутрь Laravel business logic.

Рассматривать его как infrastructure/runtime component.

Документировать:

```text
CVortex MCP server
        ↑
local/private network
        ↑
tunnel-client
        ↑
outbound HTTPS
        ↑
OpenAI tunnel endpoint
        ↑
supported OpenAI client
```

Не коммитить runtime API key.

Не логировать tunnel credentials.

Если tunnel requires capabilities/plan/API billing, которыми current environment не обладает:

архитектуру подготовить, но deployment не симулировать.

---

# Existing Responses API

Не удалять и не ломать:

```text
OpenAiResponsesProvider
OPENAI_API_KEY mode
existing ModelPolicy
existing LLM workflows
```

После этой задачи должны потенциально существовать два независимых пути:

```text
A. Internal generation

CVortex
→ LlmProvider
→ Responses API
→ result
→ Truth Guard


B. External AI client

ChatGPT / MCP client
→ MCP Gateway
→ CVortex data/services
→ proposal/draft
→ Truth Guard
→ human approval
```

MCP не является скрытой заменой `OpenAIProvider`.

---

# Configuration

Добавить только необходимую configuration foundation.

MCP access должен быть:

```text
disabled by default
```

если это соответствует existing configuration conventions.

Не создавать production-open endpoint случайно после обычного:

```text
docker compose up
```

Local development activation должна быть explicit.

Secrets — только через existing secret/config mechanisms.

---

# API / contracts

MCP schemas должны быть versionable.

При изменении tool contract учитывать backward compatibility.

Для каждого tool определить:

- name;
- description;
- input schema;
- output schema;
- auth requirement;
- read/write classification;
- side effects;
- approval implications;
- error cases.

Не отдавать model-facing schema поля, которые ему не нужны.

---

# Initial bounded vertical slice

После research и architecture decision реализовать минимальный usable MCP slice.

Он должен доказать architecture, а не реализовать весь CVortex через MCP.

Предпочтительный сценарий:

```text
1. MCP client authenticates.
2. Client requests one existing vacancy.
3. CVortex verifies ownership.
4. Client requests bounded application context:
   - normalized vacancy;
   - relevant CONFIRMED facts;
   - relevant Employer Memory;
   - no secrets;
   - no unrelated history.
5. Client submits a generated draft/proposal.
6. CVortex validates draft.
7. Truth Guard executes.
8. Employer consistency executes where applicable.
9. Valid proposal is saved as PENDING_REVIEW.
10. No application is sent.
11. No fact is auto-confirmed.
```

Если какие-то domain entities из этого сценария ещё не существуют в current project state:

не реализуй будущие milestones ради MCP.

Сузь vertical slice до уже существующих entities и зафиксируй limitation.

Не начинай M2.

---

# Tests

Добавь релевантные automated tests.

Минимум:

## Protocol / tool registration

- MCP server initializes;
- expected bounded tools are discoverable;
- disabled/unregistered tools не видны;
- schemas valid.

## Authentication

- unauthenticated access denied;
- invalid token denied;
- expired/revoked credentials denied where applicable.

## Authorization

- User A reads own resource;
- User A cannot read User B resource;
- User A cannot modify User B resource;
- enumeration/IDOR blocked.

## Truth-first

- only CONFIRMED facts returned as trusted Career Facts;
- PENDING facts cannot silently become generated factual claims;
- untraceable draft claim is rejected or marked unresolved;
- Truth Guard block cannot be bypassed via MCP.

## Human approval

- MCP-created content remains `PENDING_REVIEW`;
- MCP cannot mark Career Fact CONFIRMED;
- MCP cannot mark application APPLIED;
- MCP cannot send application.

## Employer consistency

Если соответствующий service уже существует:

- existing contradiction blocks or returns controlled conflict.

Не реализуй новый milestone только ради этого test.

## Security

- tool arguments cannot override user identity;
- malicious resource identifier does not leak another user;
- secrets do not appear in tool response;
- stack trace does not leak on error;
- external vacancy text cannot alter MCP server behavior.

## Rate limiting

Если rate limiter входит в текущую реализацию:

- excessive calls produce controlled limit response.

---

# Integration validation

Обязательно проверить MCP implementation стандартным protocol-level инструментом, предпочтительно MCP Inspector или актуальным официально рекомендованным способом.

Проверить:

```text
initialize
tool discovery
read call
write/draft call
authorization
error response
```

ChatGPT end-to-end validation выполнять только если текущий account/workspace официально поддерживает нужный capability.

Если account limitation блокирует ChatGPT E2E:

не считать это defect MCP server.

Отчёт:

```text
MCP SERVER: PASS/FAIL
CHATGPT CONNECTION: PASS/BLOCKED/NOT_SUPPORTED
WRITE CAPABILITY: PASS/BLOCKED/NOT_SUPPORTED
```

с конкретной причиной.

---

# No fake validation

Не писать:

```text
ChatGPT integration works
```

если проверен только MCP Inspector.

Не писать:

```text
Plus uses MCP for free
```

без официального evidence.

Не писать:

```text
no API billing required
```

если официальный источник этого прямо не подтверждает.

---

# Documentation

Обновить только документы, которые реально затронуты.

Минимально проверить необходимость обновления:

- AI Architecture;
- Integration Architecture;
- Security / Threat Model;
- Data Flow;
- Deployment;
- Authentication;
- Truth Guard;
- Employer Memory;
- API / tool contracts;
- Operations;
- ADR index;
- Research index.

Добавить diagram:

```text
External MCP Client
        ↓
Authentication
        ↓
MCP Gateway
        ↓
Application Services
   ↙           ↘
Truth Guard   Authorization
        ↓
Persistence
```

Отдельно показать, что:

```text
Responses API provider
```

и

```text
MCP Gateway
```

являются разными integration paths.

---

# Security review

Перед completion выполнить targeted security review минимум по:

- authentication;
- authorization;
- IDOR;
- cross-user isolation;
- prompt injection;
- tool injection;
- malicious MCP arguments;
- excessive data exposure;
- secret leakage;
- PII leakage;
- replay;
- CSRF where applicable to auth flow;
- OAuth state/PKCE where applicable;
- SSRF;
- rate limiting;
- audit leakage.

Vacancy/recruiter/imported content остаётся untrusted data даже после передачи через MCP.

---

# Non-goals

В этой задаче НЕ:

- заменять Responses API integration;
- удалять `OPENAI_API_KEY`;
- переводить все AI workflows на MCP;
- реализовывать M2;
- автоматически отправлять applications;
- автоматически отправлять messages;
- автоматически CONFIRM Career Facts;
- создавать universal MCP access to all database entities;
- делать arbitrary SQL tool;
- делать arbitrary HTTP tool;
- делать filesystem tool;
- делать shell tool;
- добавлять browser automation ChatGPT;
- использовать session cookies ChatGPT;
- reverse-engineer private ChatGPT APIs;
- обходить ограничения subscription plan;
- публиковать CVortex как public ChatGPT app;
- реализовывать App Directory submission;
- делать custom MCP UI без подтверждённой необходимости;
- менять branding;
- делать unrelated refactoring.

---

# Backward compatibility

После изменения:

- существующий web UI должен работать;
- existing API должен работать;
- current Responses API path должен работать при наличии API balance;
- MCP disabled state не должен влиять на обычный CVortex workflow;
- migrations, если появились, должны иметь безопасный rollback;
- existing users не должны получать новые права автоматически.

---

# Completion criteria

Task считается завершённым только если:

## Research

- актуальная plan/capability matrix проверена;
- ChatGPT Plus claim проверен;
- API/subscription billing separation проверена;
- Secure MCP Tunnel requirements проверены;
- billing unknowns явно отмечены;
- official sources сохранены;
- research date сохранена.

## Architecture

- определено место MCP Gateway;
- MCP не смешан с LlmProvider;
- existing Responses API сохранён;
- read/write boundary определена;
- Human Approval invariant сохранён;
- Truth Guard path определён;
- multi-user authorization определена;
- data exposure policy определена;
- deployment option выбран или явно deferred;
- ADR создан/обновлён, если нужен.

## Implementation

- bounded MCP server/gateway существует;
- server disabled by default where appropriate;
- минимальный tool set реализован;
- tool schemas strict;
- authorization применяется;
- MCP handlers используют existing application/domain services;
- draft/proposal path не обходит Truth Guard;
- no automatic send;
- no automatic fact confirmation.

## Tests

- protocol tests PASS;
- authentication tests PASS;
- authorization negative tests PASS;
- Truth Guard tests PASS;
- approval invariant tests PASS;
- relevant security tests PASS;
- existing related tests не сломаны.

## Validation

- MCP Inspector/protocol validation реально выполнена;
- initialization проверен;
- tool discovery проверен;
- минимум один read tool вызван;
- минимум один safe write/draft tool вызван, если он входит в implemented slice;
- validation result сохранён.

## ChatGPT

Одно из:

```text
PASS
```

если E2E реально протестирован с officially supported account/capability;

или:

```text
BLOCKED_BY_PLAN
```

если current subscription/workspace не поддерживает интеграцию;

или:

```text
BLOCKED_BY_PLATFORM_REQUIREMENT
```

если необходимый OpenAI Platform/tunnel capability отсутствует.

Blocked ChatGPT E2E не должен маскироваться как PASS.

## Documentation

- docs отражают фактическую архитектуру;
- research index обновлён;
- ADR index обновлён, если применимо;
- security docs актуальны;
- state актуален.

---

# State

После выполнения обнови:

```text
.agents/state/STATUS.md
.agents/state/NEXT.md
.agents/state/BLOCKERS.md
```

Не менять Preview/M1.4/M2 status без фактического основания.

Если основной MCP foundation завершён, а ChatGPT connection невозможен из-за subscription:

BLOCKERS должен содержать конкретный внешний blocker, например:

```text
ChatGPT E2E validation blocked by current plan capability.
MCP Gateway itself validated locally.
```

NEXT должен содержать только следующий логический bounded task.

Не записывать туда весь backlog.

---

# Final report

Выведи кратко:

1. **Result**
   - PASS / PARTIAL / BLOCKED.

2. **Capability research**
   - current ChatGPT plan support;
   - write/read support;
   - billing finding;
   - Secure MCP Tunnel finding.

3. **Architecture**
   - выбранный integration path;
   - MCP Gateway location;
   - relation to existing Responses API.

4. **Files**
   - created;
   - changed.

5. **MCP surface**
   - tools added;
   - read/write classification.

6. **Security**
   - auth;
   - authorization;
   - Truth Guard;
   - Human Approval;
   - major mitigations.

7. **Tests / Validation**
   - только реально запущенные проверки;
   - MCP Inspector result;
   - ChatGPT E2E result.

8. **Documentation / ADR**
   - changed artifacts.

9. **Blockers / limitations**
   - только реальные.

10. **State**
    - current milestone state;
    - exact next bounded task.

Не повторяй полное содержание созданных research/docs.

Не называй validation выполненной, если она реально не запускалась.

---

# STOP

После выполнения задачи остановись.

Не переходи к M2.

Не расширяй MCP surface дополнительными tools.

Не начинай ChatGPT App UI.

Не начинай public deployment.

Не исправляй соседние feature areas.

Не делай speculative refactoring.
