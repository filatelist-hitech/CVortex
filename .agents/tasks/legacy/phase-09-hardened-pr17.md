---
title: CVortex Phase 09 — Authentication + Multi-user
status: ready
phase: 09-auth-multi-user
owner: project
created: 2026-09-12
updated: 2026-09-12
tags:
  - task
  - auth
  - authorization
  - multi-user
  - security
related:
  - ../../PROJECT.md
  - ../state/STATUS.md
  - ../../docs/03-ADR/INDEX.md
---

# CVortex — Phase 09: Authentication + Multi-user

## Goal

Реализовать authentication/authorization foundation CVortex поверх M0.

После Phase 09 должны работать:

- invite-only registration;
- first-party session authentication;
- login/logout;
- роли `admin` и `user`;
- `ACTIVE/DISABLED` lifecycle пользователей;
- server-side authorization и ownership foundation;
- invitation lifecycle;
- password change и operator recovery;
- encrypted-secret foundation;
- auth/security audit;
- минимальный auth/admin UI;
- OpenAPI и automated security tests.

Не переходить к Career, Vacancy, Application или runtime AI.

# Source of truth

Перед началом:

1. прочитай `/AGENTS.md`, `/PROJECT.md` и current state;
2. найди applicable scoped `AGENTS.md`;
3. используй accepted ADR по auth, invite-only, multi-user ownership, API-first, PostgreSQL и security;
4. используй Phase 06 security/data design, Phase 07 design foundation и Phase 08 implementation structure;
5. открывай research/docs через indexes и только по необходимости.

Не перечитывай unrelated research.

Если implementation требует изменения accepted architecture, не меняй решение молча. Зафиксируй конфликт и используй ADR, если изменение действительно архитектурное.

# Fixed product decisions

Эти решения уже приняты. Не переоткрывай их во время implementation.

## Authentication

First-party client:

```text
Next.js
→ Nginx
→ Laravel
→ Sanctum SPA authentication
→ Laravel session
```

Использовать session cookie + CSRF.

Не реализовывать в Phase 09:

- bearer auth;
- Personal Access Tokens;
- JWT;
- OAuth;
- localStorage auth tokens;
- native/mobile authentication;
- browser-extension authentication;
- custom remember-me flow.

Future clients получат auth strategy отдельным решением.

## Identity

Email:

- единственный login identity;
- required;
- normalized server-side;
- unique по normalized representation;
- использует одинаковые comparison rules в `User.email` и `Invitation.target_email`.

Username отсутствует.

Email нельзя изменить в Phase 09.

Email verification отсутствует. Это явное MVP limitation; перед self-service password recovery решение необходимо пересмотреть.

## Registration

Successful registration:

```text
valid invitation
→ create user
→ consume invitation atomically
→ audit
→ establish authenticated session
→ regenerate session ID
```

Новый пользователь всегда получает:

```text
role = user
status = ACTIVE
```

Invitation никогда не создаёт `admin`.

## Admin boundary

`admin` управляет account/security surface, но не получает обход ownership.

Admin не получает доступ к private Career/Vacancy/Application/Resume/Conversation/Employer Memory data другого пользователя только из-за роли.

Hidden superuser bypass запрещён.

# Domain model

Минимальные production concepts:

```text
User
Invitation
AuditEvent
EncryptedSecret
```

Role:

```text
admin
user
```

User status:

```text
ACTIVE
DISABLED
```

Не создавать generic ACL/permissions engine.

Не создавать Career/Vacancy/Application entities для проверки Phase 09.

# Users and roles

## User

User является authentication principal и ownership root, а не CareerProfile.

Минимально нужны:

```text
id
email
password hash
role
status
timestamps
```

Используй ID convention из accepted data design.

## Admin capabilities

Admin может:

- создавать и revoke invitations;
- просматривать invitation metadata;
- просматривать минимальный список пользователей;
- менять роль другого пользователя;
- disable/enable другого пользователя;
- управлять SYSTEM secrets.

Admin user list содержит только security/account metadata:

```text
id
email
role
status
created_at
last_login_at if implemented
```

Не возвращать private product data.

## Last active admin

Всегда должен существовать минимум один `ACTIVE admin`.

Server-side запретить:

- demote последнего active admin;
- disable последнего active admin.

Этот invariant должен иметь automated tests.

## Role changes

Только admin может выполнять:

```text
user ↔ admin
```

Пользователь не может изменить собственную роль через request payload.

Role change:

- проверяет authorization;
- проверяет last-admin invariant;
- выполняется transactionally;
- invalidates sessions target user;
- audit'ится.

## Disable / enable

Admin может менять другого пользователя:

```text
ACTIVE ↔ DISABLED
```

Disabled user:

- не может login;
- не может продолжать authenticated access;
- сохраняет свои данные.

Disable invalidates его sessions.

Enable не создаёт новую session автоматически.

# Bootstrap admin

Первый admin создаётся operator-controlled CLI command.

Запрещены:

- default credentials;
- committed seed password;
- public bootstrap endpoint;
- правило «первый зарегистрированный становится admin».

CLI должен:

- принимать email/password безопасно;
- применять обычные email/password rules;
- не выводить и не логировать plaintext password;
- создавать `ACTIVE admin`;
- безопасно обрабатывать повторный bootstrap.

Следуй repository naming conventions.

# Invitations

Invitation бывает двух типов.

## Targeted

```text
target_email != null
max_uses = 1
```

Normalized registration email обязан совпадать с normalized target email.

## Generic

```text
target_email = null
max_uses = 1..10
```

Default:

```text
max_uses = 1
```

## Lifetime

Каждый invitation имеет expiry.

```text
default lifetime = 7 days
maximum lifetime = 30 days
```

Invitation без expiry или с lifetime > 30 days запрещён.

## State

Status derived из данных, а не хранится как независимо изменяемое поле:

```text
ACTIVE
EXHAUSTED
EXPIRED
REVOKED
```

Определи однозначный precedence и покрой tests.

## Token

Invitation token:

- cryptographically secure;
- хранится только в безопасной hashed representation;
- не логируется;
- не попадает в audit;
- не возвращается API после creation response.

Plaintext token или registration URL показывается admin один раз после создания.

Если token потерян:

```text
revoke old invitation
→ create new invitation
```

Phase 09 не отправляет invitation по email.

## Atomic consumption

Invariant:

```text
uses_count <= max_uses
```

Registration и consume invitation должны быть защищены одной подходящей PostgreSQL transaction/locking/atomic-update strategy.

Два concurrent requests не могут использовать последний slot дважды.

# Registration

Input:

```text
invitation_token
email
password
password_confirmation
```

Не собирать first name, last name, display name или Career data.

Server должен проверить:

- token;
- status;
- expiry;
- revocation;
- available usage;
- target-email constraint;
- normalized email uniqueness;
- password policy.

User creation и invitation consumption должны быть atomic.

Public registration без valid invitation запрещена.

После successful transaction создать authenticated session и regenerate session ID.

# Login and sessions

Login:

```text
email
password
```

Требования:

- server-side email normalization;
- framework password verification;
- DISABLED account rejection;
- rate limiting;
- generic failure response where needed to reduce enumeration;
- session regeneration after success.

Несколько simultaneous sessions разрешены.

Обычный logout завершает только current session.

Phase 09 не реализует:

- active-device list;
- remote per-session logout;
- session-management UI.

Architecture при этом должна позволять invalidation всех sessions пользователя.

## Security-triggered invalidation

Все sessions target user инвалидируются после:

- disable;
- role change;
- operator password recovery.

Password change:

- требует current password;
- safely rotates/re-authenticates current session;
- invalidates остальные sessions.

# Passwords

Policy:

```text
minimum = 15 characters
maximum = 128 characters
```

Разрешать passphrases и spaces.

Не требовать обязательную комбинацию uppercase/lowercase/digit/symbol.

Не вводить periodic password rotation.

Использовать current framework-supported password hashing primitive после проверки official Laravel/security guidance.

Plaintext password:

- не хранить;
- не логировать;
- не reversible-encrypt.

## Password change

Authenticated user может поменять только собственный password.

Input:

```text
current_password
new_password
new_password_confirmation
```

После success применить session invalidation policy и AuditEvent.

## Recovery

Self-service `Forgot password` отсутствует.

Admin не может установить temporary password через normal UI/API.

Предусмотреть operator-controlled CLI recovery:

- explicit user identity;
- compliant new password;
- no plaintext logging/output;
- invalidate all sessions;
- audit security event.

# Authorization and ownership

Authorization enforced server-side.

Invariant:

```text
User A cannot read, mutate, delete or enumerate
private resource owned by User B,
including nested or indirect access.
```

Не доверять frontend-supplied:

```text
user_id
owner_id
role
scope
tenant-like identifiers
```

Ownership определяется authenticated user и server-side relationships.

Используй единый reusable pattern для:

- query scoping;
- resource loading;
- policies;
- nested resources;
- admin capabilities.

Не размазывай ad-hoc owner checks по controllers.

## Response convention

Чужой private resource:

```text
404 Not Found
```

Known capability, которой не хватает роли, например user → admin endpoint:

```text
403 Forbidden
```

## Ownership fixture

Для проверки authorization не создавать production Career/Vacancy entities.

Предпочтительно использовать isolated test fixture/test-only model.

Production generic resource допустим только при архитектурной необходимости, которую нужно документировать.

# Encrypted secrets

Реализовать минимальный persisted secret primitive без provider-specific UI.

Scopes:

```text
USER
SYSTEM
```

Минимальная модель:

```text
id
ownership_scope
user_id nullable according to scope
type
name
encrypted_value
timestamps
```

## Rules

Secret value:

- encrypted at rest;
- никогда не возвращается plaintext после storage;
- не попадает в logs/errors/audit;
- не сохраняется frontend persistence.

Operations:

```text
create
rotate/update
delete
read safe metadata
```

USER secret доступен только owner.

Admin role сама по себе не даёт доступа к plaintext USER secret.

SYSTEM secret управляется только explicit admin/system boundary.

Не реализовывать OpenAI-specific credential logic.

# Audit

Persistent AuditEvent нужен для successful/security-significant actions:

```text
invitation.created
invitation.revoked
invitation.used
user.registered
user.login_succeeded
user.logged_out
user.role_changed
user.disabled
user.enabled
user.password_changed
user.password_recovered
secret.created
secret.rotated
secret.deleted
```

Следуй existing naming convention, если она уже существует.

Не создавать background `invitation.expired` event: expiry является derived state.

Failed login attempts идут в security logs/rate-limit telemetry, а не в permanent audit по одному событию на пароль.

Audit не содержит:

- passwords/hashes;
- invitation plaintext tokens;
- session IDs;
- CSRF/auth tokens;
- API keys;
- secret plaintext.

# API

Документировать только endpoints, реально реализованные Phase 09.

Logical surface:

```text
registration
login
logout
current user
change password

admin invitation create/list/revoke
admin user list
admin role change
admin disable/enable
```

Secret API добавляй только в объёме, необходимом выбранной implementation foundation.

Следуй existing `/api/v1`, error и pagination conventions.

Не создавать speculative endpoints.

# Frontend

Реализовать только Phase 09 surfaces.

Public:

- login;
- invite registration.

Authenticated:

- current-user shell;
- logout;
- password change.

Admin:

- invitation list/create/revoke;
- one-time registration URL/token presentation;
- minimal user list;
- role change;
- enable/disable.

Используй Phase 07 design system и Figma source of truth.

Проверь:

- desktop/mobile;
- keyboard navigation;
- focus;
- loading;
- empty;
- validation;
- error;
- unauthorized;
- destructive confirmation.

Не создавать fake Career/Vacancy dashboard.

# Security

Проверь минимум:

- CSRF;
- session fixation;
- secure cookie configuration;
- login/registration rate limiting;
- account enumeration;
- mass assignment;
- IDOR;
- cross-user access;
- nested-resource access;
- secret leakage;
- sensitive-data logging.

Rate limits выбери по current framework/security research, сделай configurable и документируй.

Не проси пользователя выбирать requests/minute, если это не product decision.

# Database

Создай только migrations, необходимые Phase 09.

Проверь:

- existing ID convention;
- normalized email uniqueness;
- foreign keys;
- invitation token-hash indexes;
- invitation usage/expiry queries;
- ownership references;
- secret-scope constraints;
- timestamps;
- rollback;
- compatibility с M0 baseline.

Database constraints должны поддерживать business invariants там, где это разумно.

Не создавать Phase 10 tables.

# Tests

Automated tests обязательны.

## Invitations

Проверить:

- valid targeted registration;
- valid generic registration;
- invalid token;
- expired;
- revoked;
- exhausted;
- target email match/mismatch;
- targeted `max_uses > 1` rejected;
- generic boundaries `1..10`;
- default and maximum expiry;
- registration without invitation;
- token one-time reveal;
- token unavailable after creation;
- concurrent last-slot consumption;
- `uses_count` never exceeds `max_uses`.

## Authentication

Проверить:

- valid login;
- invalid credentials;
- disabled login;
- current-user endpoint;
- unauthenticated protected access;
- auto-login after registration;
- session regeneration;
- CSRF behavior;
- current-session logout;
- another normal session survives ordinary logout;
- rate limiting.

## Passwords

Проверить:

- minimum length;
- passphrase/spaces;
- maximum length;
- current password requirement;
- successful change;
- other-session invalidation;
- recovery CLI;
- no plaintext serialization/logging.

## Roles/status

Проверить:

- user cannot use admin operations;
- user cannot self-escalate;
- admin can promote/demote another user;
- role change invalidates target sessions;
- admin can disable/enable user;
- disabled sessions lose access;
- last active admin cannot be demoted;
- last active admin cannot be disabled.

## Ownership

Через real authorization boundary:

```text
User A cannot read User B resource
User A cannot update User B resource
User A cannot delete User B resource
User A cannot enumerate User B resource
User A cannot reach nested User B resource by indirect ID
```

Также проверить, что admin role не обходит private-resource ownership.

## Secrets

Проверить:

- plaintext absent from stored representation/API output;
- USER owner isolation;
- SYSTEM admin boundary;
- rotate/update;
- delete;
- safe metadata;
- audit/log redaction.

Не mock authorization result.

# Validation

Во время разработки запускай narrow relevant checks.

Перед PASS выполнить применимые:

- backend auth/security tests;
- frontend auth/admin tests;
- authorization negative suite;
- invitation concurrency tests;
- secret tests;
- lint/static analysis;
- TypeScript checks;
- migration up/down validation;
- OpenAPI validation;
- relevant project `make` targets.

Не заявляй проверку как выполненную, если команда не запускалась.

# Documentation

После implementation обновить только документы, которые реально изменились:

- auth/session design;
- authorization/ownership conventions;
- invitation lifecycle;
- role/status semantics;
- secret-storage foundation;
- security docs;
- OpenAPI;
- local setup, если изменились env requirements;
- data-model mapping/ERD where applicable;
- documentation index;
- project state.

Зафиксировать known limitations:

- no email verification;
- no self-service password reset;
- no email change;
- no account deletion;
- no MFA;
- no PAT/native auth;
- no session-management UI.

ADR нужен только для material architecture change.

# Completion criteria

Phase 09 PASS только если:

- first admin можно безопасно создать через operator CLI;
- registration невозможна без valid invitation;
- registration создаёт только ACTIVE user и сразу authenticates его;
- login/logout/session/CSRF flow работает;
- targeted/generic invitation rules и atomic usage работают;
- invitation token нельзя получить повторно;
- roles и ACTIVE/DISABLED lifecycle enforced server-side;
- last-active-admin invariant проходит tests;
- password change/recovery соблюдают session policy;
- ownership pattern и cross-user negative suite проходят;
- admin не обходит private ownership;
- USER/SYSTEM encrypted-secret boundaries работают;
- sensitive values отсутствуют в API/logs/audit;
- implemented API задокументирован;
- required frontend surfaces работают;
- migrations и mandatory validation проходят;
- Career/Vacancy/Application/runtime AI не реализованы.

Любой failure в cross-user isolation, invitation atomicity, last-admin protection или secret redaction блокирует PASS.

# State

После PASS обнови:

```text
.agents/state/STATUS.md
.agents/state/NEXT.md
.agents/state/BLOCKERS.md
```

`STATUS.md` должен отражать фактические implementation choices, migrations, security controls, tests и known limitations.

`NEXT.md`:

```text
10-career-foundation
```

`BLOCKERS.md` содержит только реальные blockers.

# Final report

Выведи кратко:

1. Result: `PASS / PARTIAL / BLOCKED`.
2. Files/migrations/endpoints changed.
3. Implemented auth/session/invitation/role model.
4. Security and ownership controls.
5. Validation actually executed.
6. Real blockers/limitations.
7. Exact next phase.

Не пересказывай task spec.

# STOP

После Phase 09 остановись.

Не реализуй CareerProfile/CareerFact, resume import, vacancy ingestion или другую Phase 10 functionality.

Не начинай Phase 10 в этой session.
