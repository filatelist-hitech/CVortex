# CVortex — Observability, Logging & Error Center

Перед началом работы:

1. Прочитай `/AGENTS.md`.
2. Прочитай `/PROJECT.md`.
3. Прочитай `/.agents/state/STATUS.md`.
4. Прочитай `/.agents/state/NEXT.md`.
5. Найди applicable scoped `AGENTS.md`.
6. Найди связанные accepted ADR.
7. Через documentation indexes открой только документы, относящиеся к:
   - architecture;
   - backend;
   - frontend;
   - queues;
   - LLM;
   - security;
   - operations;
   - API conventions.
8. Не загружай рекурсивно весь `/docs` и `/research`.

Следуй существующему source of truth CVortex.

Если требования этой задачи конфликтуют с accepted ADR или уже реализованной архитектурой, не меняй решение молча:

- зафиксируй конфликт;
- определи authoritative source;
- при необходимости предложи ADR amendment/superseding ADR;
- только затем продолжай.

---

# Goal

Создать для CVortex единую, понятную и безопасную систему:

**Logging + Error Handling + Error Correlation + Error Center + Diagnostics**

После выполнения задачи должно быть возможно быстро ответить на вопросы:

> Что сломалось?

> Где сломалось?

> Когда?

> В каком компоненте?

> Для какого HTTP request / background job / LLM run?

> Какой пользовательский action привёл к ошибке?

> Повторяется ли ошибка?

> Это новая ошибка или уже известная?

> Можно ли повторить операцию?

> Как найти связанные события?

Система должна покрывать как минимум:

- Laravel backend;
- Next.js frontend;
- HTTP/API;
- background jobs;
- Laravel Queue / Horizon;
- PostgreSQL-related failures;
- Redis-related failures;
- LLM/provider calls;
- document generation;
- LibreOffice conversion;
- vacancy imports;
- research/import jobs;
- external HTTP integrations;
- authentication/authorization failures;
- unexpected/unhandled exceptions.

При этом пользователь не должен видеть raw stack traces, SQL, secrets или внутренние детали.

---

# Product outcome

Нужно добиться двух разных UX.

## 1. User-facing errors

Обычный пользователь получает:

- понятное описание проблемы;
- понятное действие:
  - Retry;
  - Go back;
  - Reload;
  - Try later;
  - Contact admin;
- стабильный error code;
- diagnostic/reference ID;
- retryability indication там, где это полезно.

Например:

```text
Не удалось проанализировать вакансию.

LLM provider временно недоступен.
Можно повторить попытку.

Error: LLM_PROVIDER_UNAVAILABLE
Reference: req_01K...
```

Никаких stack traces.

Никакого:

```text
SQLSTATE[08006]...
```

Никаких PHP paths.

Никаких API keys.

---

## 2. Developer/Admin Error Center

Администратор должен иметь визуальный интерфейс диагностики ошибок.

Рабочее название:

```text
Diagnostics
```

или

```text
Error Center
```

Следуй существующей navigation/design system.

Не меняй branding ради этой задачи.

---

# Core principle

Logging должен быть предназначен для диагностики системы, а не для накопления случайных строк.

Запрещён подход:

```php
Log::info('here');
Log::info('works');
Log::error($e);
```

без структуры и контекста.

Использовать structured events.

---

# Phase A — Inspect existing implementation

До изменения кода проведи targeted inspection.

Определи:

- как Laravel сейчас обрабатывает exceptions;
- какие log channels существуют;
- куда сейчас пишутся logs;
- используется ли Monolog напрямую;
- какой API error format уже принят;
- существует ли request/correlation ID;
- как устроены jobs;
- как используется Horizon;
- существует ли `llm_runs`;
- как логируются LLM provider failures;
- как frontend показывает API errors;
- существуют ли React/Next.js error boundaries;
- существует ли frontend telemetry/reporting endpoint;
- как работает Docker logging;
- какие `make logs` / diagnostic commands уже существуют;
- какие health endpoints существуют;
- какие audit mechanisms существуют.

Не создавай параллельную logging architecture, если часть нужной инфраструктуры уже существует.

Кратко зафиксируй найденное перед implementation.

---

# Research

Эта задача частично зависит от текущих framework capabilities.

Проведи targeted research только там, где это необходимо.

Проверить по официальной документации актуальных используемых версий проекта:

- Laravel logging;
- Laravel exception handling;
- Laravel contextual logging;
- Laravel Queue failure events;
- Laravel Horizon observability;
- Monolog capabilities;
- Next.js error handling;
- React error boundaries;
- frontend instrumentation capabilities;
- browser `error` / `unhandledrejection` handling;
- source map considerations;
- OpenTelemetry integration possibilities, если она реально рассматривается.

Приоритет источников:

```text
official framework docs
→ official package docs
→ standards
→ vendor docs
→ primary engineering sources
```

Не добавляй dependency только потому, что она популярна.

---

# Architecture constraint

CVortex остаётся:

- local-first;
- API-first;
- Docker Compose;
- Laravel;
- Next.js;
- PostgreSQL;
- Redis/Horizon;
- multi-user;
- security-first.

Не превращай эту задачу в отдельную observability-платформу.

---

# Non-goals

В этой задаче НЕ:

- внедрять Kubernetes;
- строить ELK cluster;
- добавлять Elasticsearch;
- добавлять Kafka;
- поднимать большой Grafana/Loki/Prometheus stack без доказанной необходимости;
- подключать обязательный SaaS logging vendor;
- привязывать CVortex к Sentry или другому vendor;
- строить полноценный distributed tracing platform;
- переписывать существующие domain services;
- логировать каждый SQL query;
- логировать каждый HTTP request body;
- логировать полный LLM prompt/response;
- логировать содержимое CV/резюме без конкретной необходимости;
- создавать logging ради logging;
- исправлять все существующие продуктовые bugs, найденные по пути.

Если внешний observability provider может быть полезен позднее, предусмотреть extension point, но не усложнять MVP.

---

# Logging model

Создай единый structured logging contract.

Каждое событие должно иметь только применимые поля.

Минимальная логическая структура:

```text
timestamp

level
environment

service
component
event_name

message

error_code
exception_class

request_id
job_id
llm_run_id
application_id

user_id
actor_type

route
http_method
http_status

queue
job_class
attempt

provider
operation

duration_ms

retryable

context
```

Не все поля обязательны.

Не записывай `null`-помойку, если существующий logging formatter позволяет этого избежать.

---

# Correlation

Это критически важно.

Использовать существующие идентификаторы CVortex:

```text
request_id
job_id
llm_run_id
application_id
```

Если архитектуре реально требуется отдельный root `correlation_id`, сначала обоснуй это и не создавай второй параллельный механизм без необходимости.

---

# HTTP request correlation

Каждый входящий HTTP request должен иметь `request_id`.

Если доверенный внутренний `request_id` уже передаётся между компонентами, используй существующую policy.

Иначе backend создаёт новый ID.

`request_id` должен быть:

- доступен logger context;
- возвращаться в response header;
- присутствовать в structured API error response;
- передаваться в background job, если job создан этим request;
- использоваться при LLM вызовах, если LLM run создан этим request.

Не доверять произвольному external correlation ID без validation.

---

# Background jobs

При dispatch background job сохранять применимый correlation context.

Для job ошибки должны быть видны:

- job ID;
- queue;
- job class;
- attempt number;
- request ID origin, если есть;
- user;
- application;
- LLM run;
- exception;
- retryability;
- final failure status.

Не дублировать одно падение пятью идентичными error events из разных exception handlers.

Horizon остаётся инструментом queue visibility.

Error Center должен дополнять Horizon, а не копировать весь Horizon UI.

---

# LLM observability

Для каждого LLM run использовать существующую LLM observability/accounting model.

Error diagnostics должны позволять связать:

```text
application
→ request
→ skill/workflow
→ llm_run
→ provider failure
```

Но НЕ логировать по умолчанию:

- API key;
- Authorization headers;
- полный system prompt;
- полный user prompt;
- резюме пользователя;
- recruiter conversation;
- полный provider response.

Для диагностики сохранять metadata:

- provider;
- logical model policy;
- concrete model, если существующая llm_runs schema это допускает;
- skill/workflow;
- attempt;
- HTTP/provider status;
- provider error type;
- latency;
- token metadata, если уже предусмотрено;
- retry;
- sanitized provider request ID, если безопасно и полезно.

---

# Error taxonomy

Создать единый подход к error codes.

Error code должен быть:

- machine-readable;
- стабильным;
- пригодным для frontend;
- пригодным для поиска;
- независимым от текста exception.

Примеры категорий:

```text
AUTH_*
VALIDATION_*

VACANCY_*
IMPORT_*

LLM_*
EXTERNAL_SERVICE_*

DOCUMENT_*
PDF_*

QUEUE_*

DATABASE_*
CACHE_*

PERMISSION_*

INTERNAL_*
```

Не требуется немедленно создать сотни error codes.

Создать mechanism + первоначальный разумный catalog только для реально существующих flows.

---

# Severity

Определить и документировать понятные уровни.

Минимально:

```text
DEBUG
INFO
WARNING
ERROR
CRITICAL
```

Определить правила.

Например:

`WARNING`
- recoverable anomaly;
- retry succeeded;
- degraded functionality.

`ERROR`
- операция пользователя не выполнена;
- job завершился ошибкой;
- provider request окончательно failed.

`CRITICAL`
- системная проблема;
- unavailable core dependency;
- corruption/invariant violation;
- security-sensitive failure.

Не превращать validation error пользователя в `ERROR`, если это штатный 4xx flow.

---

# Exceptions

Создать или упорядочить central exception mapping.

Нужно разделять:

```text
Domain error

Validation error

Authorization error

External dependency error

Transient infrastructure error

Unexpected internal error
```

Каждый тип должен иметь:

- HTTP status, если применимо;
- stable code;
- safe user message;
- retryable;
- logging severity;
- diagnostic context.

---

# API error contract

Следовать уже существующим API conventions.

Если единый contract ещё не определён, предложить минимальный consistent вариант.

Логически ошибка должна содержать примерно:

```json
{
  "error": {
    "code": "LLM_PROVIDER_UNAVAILABLE",
    "message": "Сервис анализа временно недоступен.",
    "request_id": "req_...",
    "retryable": true
  }
}
```

Не воспринимать этот JSON как обязательную schema, если accepted API ADR определяет другой format.

Существующий API contract имеет приоритет.

---

# Frontend error handling

Создать единый frontend error pipeline.

Покрыть:

- failed API requests;
- unexpected React rendering errors;
- unhandled promise rejection;
- unexpected browser runtime errors;
- route/page errors, если framework это поддерживает.

Frontend не должен в каждом component самостоятельно изобретать:

```text
try
catch
toast("error")
```

Создать разумный централизованный mechanism.

---

# Frontend Error Boundary

Использовать framework-supported architecture для текущей версии Next.js/React.

UI должен иметь нормальные:

- page-level error state;
- component-level recovery там, где это оправдано;
- retry;
- reference ID.

Не скрывать ошибку пустым экраном.

---

# Frontend reporting

Если frontend runtime error не связан с API response, предусмотреть безопасный authenticated endpoint для передачи sanitized diagnostic event backend.

Endpoint должен:

- проходить validation;
- иметь rate limiting;
- не принимать произвольный giant payload;
- не доверять frontend-provided `user_id`;
- определять пользователя server-side;
- sanitise payload;
- ограничивать stack/message length.

Browser telemetry считать untrusted input.

---

# Error Center

Создать визуальный admin-only интерфейс.

Использовать существующий CVortex design system.

Figma остаётся visual source of truth.

Если отдельного Diagnostics screen ещё нет в Figma:

- не менять brand;
- использовать существующие components/tokens/patterns;
- при необходимости обновить design documentation/Figma согласно существующему workflow.

---

# Error Center — list

Показывать список incidents/errors.

Минимальные данные:

```text
severity

error code
short message

service/component

first seen
last seen

occurrence count

status

request/job/llm context
```

---

# Error Center — filters

Добавить полезные фильтры:

- severity;
- status;
- service/component;
- environment;
- error code;
- time range;
- request ID;
- job ID;
- LLM run ID;
- application ID.

Добавлять дополнительные фильтры только если они реально полезны.

---

# Error Center — search

Должна быть возможность вставить:

```text
req_...
```

и сразу найти связанную ошибку.

Аналогично:

```text
job ID
llm_run ID
application ID
error code
```

---

# Error detail

Detail view должен показывать:

## Summary

- error code;
- severity;
- status;
- message;
- component;
- timestamp.

## Correlation

- request ID;
- job ID;
- LLM run;
- application;
- user reference, если разрешено authorization model.

## Technical

- sanitized exception class;
- sanitized stack trace;
- route;
- job;
- provider;
- retry attempts;
- relevant sanitized context.

## Occurrences

Если ошибка повторяется:

- occurrence count;
- first seen;
- last seen;
- последние occurrences.

Не показывать secrets.

---

# Error grouping / deduplication

Не превращать Error Center в поток из 40 000 одинаковых ошибок.

Реализовать deterministic fingerprinting/grouping.

Fingerprint может учитывать применимые:

- error code;
- exception class;
- component;
- normalized stack location;
- operation.

Не использовать raw message целиком, если в нём присутствуют dynamic IDs.

Одинаковые ошибки должны группироваться в incident.

Incident должен иметь:

```text
occurrence_count
first_seen_at
last_seen_at
```

Решение должно оставаться простым.

---

# Incident status

Предусмотреть простой lifecycle:

```text
OPEN
RESOLVED
IGNORED
```

или используй существующие project conventions, если они уже определены.

Admin должен иметь возможность:

- отметить resolved;
- reopen;
- ignore known noise.

Не создавать полноценную Jira внутри CVortex.

---

# Persistence

Raw logs и queryable Error Center имеют разные задачи.

Не записывать все `INFO` logs в PostgreSQL только ради UI.

Выбери после inspection наиболее простой local-first architecture.

Предпочтительный принцип:

```text
structured raw logs
+
queryable persisted error incidents/events
```

Если для Error Center нужна новая persistence model:

- создать минимальную schema;
- предусмотреть indexes;
- retention;
- cleanup;
- multi-user/security implications;
- duplicate grouping.

Не использовать PostgreSQL как бесконечный dump всех console logs.

---

# Retention

Предусмотреть configurable retention policy.

Отдельно рассмотреть:

- raw logs;
- individual error occurrences;
- grouped incidents.

Не удалять активный incident только потому, что старые individual events очищены.

Добавить deterministic cleanup mechanism.

---

# Logging storm protection

Одна сломанная интеграция не должна уничтожить диск или БД миллионом одинаковых событий.

Предусмотреть:

- grouping;
- duplicate suppression где разумно;
- bounded payload;
- rate protection;
- retention.

Но не скрывать факт, что ошибка повторилась.

Хранить occurrence counter.

---

# Sensitive data / secrets

Это обязательный security requirement.

Никогда не логировать:

- passwords;
- session tokens;
- cookies;
- Authorization headers;
- OpenAI/provider API keys;
- user LLM credentials;
- invitation tokens;
- DB passwords;
- Redis credentials;
- secret environment variables.

Автоматически redacting как минимум ключи/поля типа:

```text
password
password_confirmation

token
access_token
refresh_token

authorization
cookie
set-cookie

api_key
apikey
secret

client_secret
```

Учитывать регистр и nested JSON structures.

---

# PII

Не логировать без необходимости:

- полный текст резюме;
- полный Career Fact Base;
- recruiter messages;
- email content;
- phone numbers;
- uploaded document contents;
- vacancy raw content;
- LLM prompt contents.

Использовать IDs, metadata и ограниченные sanitized excerpts только там, где это действительно необходимо для диагностики.

---

# Stack traces

Полные stack traces:

- допустимы только в защищённом diagnostic storage;
- доступны только authorized admin;
- никогда не входят в публичный API response;
- никогда не возвращаются frontend обычному пользователю.

Sanitise absolute paths/secrets, если они могут раскрывать чувствительную infrastructure information.

---

# Multi-user isolation

CVortex является multi-user системой.

Error diagnostics не должны создать новый IDOR.

Обычный User:

- не получает diagnostic events другого пользователя;
- не видит stack traces;
- не получает системные incidents.

Admin diagnostics:

- защищены authorization;
- audit-sensitive actions логируются согласно существующей audit policy.

Frontend `user_id` никогда не является trusted ownership source.

---

# Security events

Не смешивать обычные application errors и security-sensitive events без классификации.

Для событий вроде:

- repeated forbidden access;
- malformed malicious payload;
- prompt injection detection;
- SSRF block;
- suspicious file;
- secret redaction trigger;

использовать существующую security/audit architecture.

Не превращать Error Center в полноценный SIEM.

---

# Infrastructure failures

Error Center не может быть единственным способом диагностики.

Если Laravel/PostgreSQL/Redis/Next.js полностью упали, web UI может быть недоступен.

Поэтому должны оставаться рабочими:

```text
docker compose logs
make logs
container stdout/stderr
health checks
```

или существующие эквиваленты проекта.

Документировать emergency diagnostic path:

> UI unavailable → как посмотреть причину из CLI.

---

# Health overview

Если в проекте уже есть health architecture, Error Center может показывать status основных компонентов:

- Backend;
- Frontend;
- PostgreSQL;
- Redis;
- Horizon/Queues;
- document converter;

только если это можно сделать без значительного расширения scope.

Не строить отдельный monitoring platform ради status cards.

---

# Developer Experience

Добавить или улучшить developer commands, если они отсутствуют.

Например логически:

```text
make logs

make logs-backend
make logs-frontend

make failed-jobs

make diagnostics
```

Конкретные names должны соответствовать существующему Makefile/conventions.

Не создавать duplicate commands.

---

# Local development readability

Production/structured format может быть JSON.

Но локальный developer experience должен оставаться читаемым.

Исследуй существующую logging configuration и выбери подход, при котором:

- machine-readable logs существуют;
- локально человек не вынужден читать однострочный JSON глазами, если этого можно избежать.

Не создавать разные semantic logging schemas для local/prod.

Меняться может formatter, но не смысл событий.

---

# Observability events

Критические workflows CVortex должны использовать понятные event names.

Например:

```text
vacancy.import.started
vacancy.import.failed

llm.run.started
llm.run.failed

document.render.failed

queue.job.failed

auth.login.failed

application.generation.failed
```

Не обязательно использовать именно эти names.

Создать consistent naming convention.

---

# Logging boundaries

Не нужно логировать каждую функцию.

Логировать:

- начало важных asynchronous operations, где это помогает;
- completion важных operations при необходимости;
- retries;
- external dependency failures;
- invariant violations;
- final operation failures;
- unusual security-relevant conditions.

Не превращать логирование в трассировку каждой строки PHP.

---

# Error handling UX

Для основных frontend flows определить стандартные UI patterns:

## Inline error

Для локальных validation/domain проблем.

## Toast

Для краткой non-blocking ошибки.

## Error panel/card

Когда конкретный section не загрузился.

## Full page error

Когда route/functionality полностью unavailable.

## Retry

Для retryable operation.

Все patterns должны использовать existing design system.

---

# Error copy

Сообщение пользователю должно отвечать:

1. Что произошло?
2. Что пользователь может сделать?
3. Есть ли возможность повторить?
4. Какой reference ID сообщить администратору?

Плохо:

```text
Something went wrong
```

Ещё хуже:

```text
500
```

Нормально:

```text
Не удалось сформировать PDF.

DOCX создан успешно, но сервис конвертации временно недоступен.

Повторите попытку.

Reference: req_...
```

---

# Deterministic before AI

Никакой LLM не нужен для:

- классификации exception;
- определения severity;
- redaction;
- fingerprinting;
- grouping;
- API error mapping;
- correlation;
- retry rules.

Всё это deterministic code.

Не создавай `AI Error Analyzer` на этой фазе.

---

# Observability and audit separation

Не путать:

```text
application logs
error incidents
audit log
LLM run accounting
security events
```

Они могут коррелироваться ID, но имеют разное назначение.

Не сваливать всё в одну таблицу `logs`.

---

# Database

Если нужны schema changes:

- migrations;
- rollback;
- indexes;
- foreign keys;
- JSONB только где оправдан;
- user isolation;
- nullable semantics;
- timestamps;
- retention strategy;
- backward compatibility.

Если conceptual model изменяется:

- обновить ERD;
- Data Dictionary.

---

# API

Для новых diagnostic endpoints:

- `/api/v1` conventions;
- authorization;
- validation;
- stable response schema;
- filtering;
- pagination;
- sorting;
- OpenAPI;
- bounded payload sizes.

Не создавать endpoint типа:

```text
GET /api/v1/logs
```

который просто отдаёт весь logfile браузеру.

---

# Performance

Logging не должен значительно увеличивать latency основных flows.

Не выполнять тяжёлую синхронную processing работу при каждом `INFO`.

Error persistence должна быть достаточно простой и bounded.

Учитывать возможную рекурсию:

```text
database error
→ logger tries database
→ database error
→ logger tries database
...
```

Raw fallback logging должен оставаться независимым от Error Center persistence.

---

# Failure of the logging system itself

Logging infrastructure тоже может сломаться.

Система не должна:

- рекурсивно падать при logging failure;
- скрывать исходную application exception;
- превращать secondary logging failure в primary user error.

Предусмотреть safe fallback.

---

# Tests and validation

Добавить automated tests для затронутых layers.

Минимальный набор:

## Backend

- request ID создаётся;
- request ID возвращается в response;
- request ID входит в structured log context;
- known domain exception корректно mapping'уется;
- unexpected exception получает INTERNAL error;
- stack trace не возвращается API client;
- sensitive fields redacted;
- nested secrets redacted;
- authorization errors корректно классифицируются;
- repeated error корректно группируется;
- occurrence counter обновляется.

## Multi-user

User A:

- не получает diagnostics User B;
- не может запросить incident User B;
- не может изменять incident;
- не может получить stack trace через ID enumeration.

## Queue

- failed job создаёт/обновляет diagnostic incident;
- job correlation сохраняется;
- retries не создают неконтролируемый duplicate spam.

## LLM

- provider failure связывается с `llm_run_id`;
- API key не попадает в logs;
- prompt content не логируется по умолчанию.

## Frontend

- API error отображается через стандартный error UI;
- reference ID отображается;
- retry работает там, где предусмотрен;
- Error Boundary показывает fallback;
- frontend telemetry payload проходит sanitization/validation.

## Security regression

Добавить explicit test fixture с fake secrets:

```text
Authorization: Bearer SECRET_CANARY
api_key=SECRET_CANARY
password=SECRET_CANARY
```

`SECRET_CANARY` не должен появляться в persisted logs / diagnostic responses / frontend output.

## E2E

Добавить минимальный controlled failure scenario, если текущая test architecture позволяет это сделать безопасно.

Не создавать production debug endpoint.

Testing-only endpoint/fixture должен быть недоступен вне testing environment.

---

# Manual validation

После implementation вручную воспроизвести минимум несколько ошибок:

1. backend internal error;
2. invalid request;
3. external/provider failure;
4. failed queue job;
5. frontend runtime/API failure.

Для каждой проверить:

```text
User-facing UI
↓
error code
↓
reference/request ID
↓
Error Center search
↓
incident detail
↓
correlated technical context
```

---

# Design

Использовать утверждённый CVortex visual style:

- dark-first;
- deep navy/black;
- cyan/blue/violet accents;
- Space Grotesk;
- existing design tokens;
- existing components.

Не вводить случайные цвета.

Severity должна быть визуально различима, но accessibility важнее декоративности.

Проверить:

- desktop;
- mobile;
- keyboard navigation;
- focus states;
- screen reader labels;
- loading;
- empty;
- error;
- no-results states.

---

# Empty state

Error Center без ошибок не должен выглядеть как сломанная таблица.

Показать понятное состояние:

```text
No active errors
```

с краткой информацией о выбранных filters/time range.

---

# Documentation

После implementation создать или обновить минимум:

```text
docs/10-Operations/Logging-and-Diagnostics.md
```

Документ должен описывать:

- architecture;
- structured log contract;
- severity;
- error code strategy;
- correlation;
- request/job/LLM relationships;
- redaction;
- retention;
- Error Center;
- Horizon relationship;
- CLI fallback;
- incident lifecycle;
- troubleshooting workflow.

Если меняется architecture:

- ADR;
- architecture diagrams.

Если меняется data model:

- ERD;
- Data Dictionary.

Если меняется API:

- OpenAPI.

Если появляется новая security surface:

- threat model/security docs.

---

# Troubleshooting runbook

Создать короткий runbook.

Пример workflow:

```text
Пользователь сообщает Reference ID
↓
найти request_id
↓
найти incident
↓
посмотреть component/error code
↓
проверить связанный job/llm_run/application
↓
посмотреть sanitized stack/context
↓
проверить Horizon/external dependency при необходимости
```

Отдельный fallback:

```text
CVortex UI недоступен
↓
docker compose ps
↓
health checks
↓
make logs / docker compose logs
↓
локализовать container/service
```

Использовать реальные команды проекта.

---

# Existing errors

Если при implementation обнаруживаются существующие ошибки проекта:

- не начинай автоматически их исправлять;
- зафиксируй только те, которые блокируют эту задачу;
- остальные перечисли кратко как findings.

Эта задача создаёт систему диагностики, а не чинит всю Вселенную за один commit.

---

# Completion criteria

Task завершён только если выполнено всё применимое ниже.

## Logging

- [ ] существует единый structured logging contract;
- [ ] request correlation работает;
- [ ] jobs correlation работает;
- [ ] LLM correlation работает;
- [ ] secret redaction работает;
- [ ] raw logs остаются доступны независимо от Error Center.

## Error handling

- [ ] backend exception mapping централизован;
- [ ] stable error codes существуют;
- [ ] user-safe messages существуют;
- [ ] stack traces не уходят обычному frontend/user;
- [ ] retryability определяется явно.

## Frontend

- [ ] стандартный API error handling существует;
- [ ] Error Boundary/fallback реализован;
- [ ] reference ID отображается;
- [ ] retry UX существует где применимо;
- [ ] blank-screen failure устранён для покрытых scenarios.

## Jobs

- [ ] final queue failures видны;
- [ ] Horizon correlation понятна;
- [ ] duplicate error storm контролируется.

## Error Center

- [ ] существует protected diagnostics UI;
- [ ] список incidents работает;
- [ ] detail работает;
- [ ] filters работают;
- [ ] search по correlation IDs работает;
- [ ] grouping работает;
- [ ] occurrence count работает;
- [ ] OPEN/RESOLVED/IGNORED или accepted equivalent работает.

## Security

- [ ] secrets не логируются;
- [ ] PII minimization применяется;
- [ ] User A не видит User B diagnostics;
- [ ] frontend telemetry считается untrusted input;
- [ ] diagnostic endpoints authorized;
- [ ] no raw stack trace leakage.

## Operations

- [ ] CLI fallback существует;
- [ ] logging failure не ломает primary operation;
- [ ] retention существует;
- [ ] cleanup существует.

## Tests

- [ ] relevant backend tests проходят;
- [ ] authorization tests проходят;
- [ ] redaction tests проходят;
- [ ] queue tests проходят;
- [ ] frontend tests проходят;
- [ ] controlled manual failure scenarios проверены.

## Documentation

- [ ] Logging-and-Diagnostics documentation обновлена;
- [ ] OpenAPI обновлён;
- [ ] architecture docs/ADR обновлены при необходимости;
- [ ] security docs обновлены при необходимости;
- [ ] project state обновлён.

Не объявляй PASS, если обязательная проверка реально не выполнялась.

---

# State

После успешной реализации обновить:

```text
.agents/state/STATUS.md
.agents/state/NEXT.md
.agents/state/BLOCKERS.md
```

`STATUS` должен отражать реальное состояние repository.

`NEXT` должен содержать следующий bounded logical step.

`BLOCKERS` содержит только реальные blockers.

---

# Final report

В конце выведи кратко:

## 1. Result

```text
PASS / PARTIAL / BLOCKED
```

## 2. Architecture

Кратко:

- какой logging pipeline получился;
- где хранятся raw logs;
- где хранятся queryable incidents;
- как работает correlation.

## 3. Files

- created;
- changed.

## 4. Database/API

Только реальные изменения.

## 5. UI

Что реализовано в Error Center.

## 6. Security

Что сделано для redaction/isolation.

## 7. Tests

Что добавлено.

## 8. Validation

Только реально выполненные команды и проверки.

## 9. Known limitations

Только существующие.

## 10. State

- current phase/task status;
- recommended next bounded task.

Не пересказывай содержимое всей документации.

---

# STOP

После выполнения этой задачи остановись.

Не переходи к другим product features.

Не начинай рефакторить соседние subsystem.

Не подключай дополнительные observability services «на будущее».

Не исправляй unrelated bugs.

Не начинай следующую задачу самостоятельно.
