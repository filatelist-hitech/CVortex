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

Реализовать authentication/authorization foundation CVortex поверх M0, не переходя к Career, Vacancy или Application functionality.

После Phase 09 система должна поддерживать безопасный invite-only multi-user access, first-party session authentication, роли `admin`/`user`, ownership foundation, auditable invitation lifecycle, user disable/enable lifecycle, безопасную работу с паролями, minimal encrypted-secret primitive, minimal admin UI и automated auth/security regression suite.

Phase 09 является infrastructure/security foundation и не реализует карьерный домен CVortex.

## Execution mode

Следуй `/AGENTS.md`, applicable scoped `AGENTS.md`, resource policy, accepted ADR, Phase 06 contracts, Phase 07 design foundation и Phase 08 implementation structure/configuration.

Default:

- один агент;
- sequential implementation;
- targeted context;
- deterministic authorization before abstractions;
- narrow tests during iteration;
- final auth/security regression suite before completion;
- no subagents unless correctness genuinely requires them.

Не загружай unrelated docs/research рекурсивно.

## Authoritative product decisions

Следующие решения зафиксированы владельцем продукта и не являются implementation choices.

### Authentication

First-party web client использует Laravel Sanctum SPA authentication поверх Laravel session cookies.

```text
Next.js → Nginx → Laravel → Sanctum SPA auth → Laravel session
```

Обязательны CSRF protection и server-side authenticated user context.

Не использовать в Phase 09:

- Personal Access Tokens;
- bearer auth для frontend;
- JWT;
- OAuth;
- localStorage auth tokens;
- native/mobile auth;
- browser-extension auth.

Future native/browser-extension authentication — отдельное architecture decision.

### Identity

Email — единственный login identity.

- email required;
- normalized server-side;
- case-insensitive uniqueness semantics;
- comparison rules едины для `User.email` и `Invitation.target_email`;
- username отсутствует;
- email mutation в Phase 09 отсутствует.

Frontend не определяет canonical normalization самостоятельно.

### Email verification

Отдельная email-verification flow в Phase 09 не реализуется.

Valid invitation позволяет создать account без отдельного подтверждения mailbox ownership.

Это intentional MVP limitation. Перед self-service password recovery, email security notifications, public registration или широким production onboarding решение должно быть пересмотрено отдельной задачей.

### Registration result

После успешной регистрации пользователь автоматически получает authenticated session.

```text
validate invitation
→ validate registration input
→ atomically create User
→ atomically consume Invitation
→ emit audit events
→ establish authenticated session
→ regenerate session identifier
→ return authenticated user state
```

### Admin privacy boundary

`admin` является системной ролью, но НЕ получает автоматического доступа к private domain data других пользователей.

Admin может управлять invitations, users, user status, user roles и SYSTEM-scoped security primitives, где явно разрешено.

Admin не получает ownership bypass для будущих Career Facts, Career Profiles, Vacancies, Applications, Resumes, Cover Letters, Conversations, Employer Memory, Interviews, user-owned secrets и других private user resources.

Не создавать hidden superuser bypass.

## Minimum context

Перед реализацией прочитай:

1. `PROJECT.md`.
2. `.agents/state/STATUS.md`.
3. `.agents/state/NEXT.md`.
4. applicable scoped `AGENTS.md`.
5. accepted ADR по invite-only, multi-user ownership/isolation, API-first, PostgreSQL и security boundaries.
6. Phase 06 Ownership/Data/Security design.
7. Phase 07 design system foundation.
8. Phase 08 implementation/configuration.

Используй documentation indexes/maps. Не перечитывай unrelated research.

## Targeted dependency research

Проверь официальные current sources только для freshness-sensitive implementation details:

- current Laravel-supported Sanctum SPA/session setup;
- session configuration;
- CSRF requirements;
- secure cookie configuration;
- password hashing primitives;
- session invalidation capabilities;
- rate limiting;
- encryption primitives.

Research не переоткрывает продуктовые решения выше. Если current framework capabilities конфликтуют с accepted architecture, зафиксируй конфликт и используй ADR discipline.

# Scope

Реализовать только:

- bootstrap первого admin;
- invite-only registration;
- invitation lifecycle;
- login/logout/current authenticated user;
- password change;
- operator password recovery primitive;
- authenticated session boundary;
- roles `admin` and `user`;
- ACTIVE/DISABLED user lifecycle;
- admin role management;
- admin enable/disable management;
- authorization policies/foundation;
- ownership enforcement patterns;
- invitation expiry and usage limits;
- optional target email;
- encrypted-secret foundation;
- auth/security audit events;
- auth rate limiting and enumeration resistance;
- minimal auth/admin frontend;
- OpenAPI/auth documentation;
- automated authorization/security tests.

# Non-goals

НЕ реализовывать:

- CareerProfile/CareerTrack/CareerFact;
- vacancies/companies domain/applications;
- resumes/cover letters/Employer Memory/interviews;
- runtime LLM workflows;
- provider-specific credentials UI;
- OpenAI-specific credential type;
- social login/OAuth/SSO/SAML;
- magic links;
- MFA;
- username login;
- email change;
- account deletion;
- email verification;
- email delivery;
- self-service forgot-password;
- device/session-management UI;
- PAT/JWT/native/browser-extension auth;
- organization/team tenancy;
- generic permission engine/complex RBAC;
- billing/subscriptions;
- public registration;
- Phase 10 functionality.

# Domain model and glossary

Minimum production concepts:

```text
User
Invitation
Role
AuditEvent
EncryptedSecret
```

Framework session storage may add implementation-specific persistence according to accepted configuration.

## User

Authentication principal и ownership root, не CareerProfile.

Minimum conceptual fields:

```text
id
email
password_hash
role
status
created_at
updated_at
```

User status:

```text
ACTIVE
DISABLED
```

DISABLED user cannot login or continue using authenticated application access. Ownership data не удаляется.

## Roles

Exactly:

```text
admin
user
```

Не создавать generic ACL/RBAC tables без отдельного requirement.

### user privileges

Normal user может login/logout, читать собственный current-user state, менять собственный password и использовать только own private resources/secrets через ownership authorization.

Normal user не может создавать/revoke invitations, читать admin invitation list, управлять другими users, менять roles/status или управлять SYSTEM secrets.

### admin privileges

Admin может создавать/list/revoke invitations, просматривать minimal user administration list, менять role другого пользователя, enable/disable другого пользователя и управлять SYSTEM-owned secrets, если secret API реализован.

Admin user list может показывать только account/security metadata:

```text
id
email
role
status
created_at
last_login_at if implemented
```

Никакой private career/job-search data.

# Last-admin invariant

Система всегда должна иметь минимум одного ACTIVE admin.

Запрещено:

- demote последнего active admin;
- disable последнего active admin.

Инвариант enforce server-side и покрыть tests.

# Role changes

Только admin может менять role другого пользователя:

```text
user → admin
admin → user
```

Role mutation должна пройти authorization, проверить last-admin invariant, быть transactional, invalidировать active sessions target user согласно session policy и создать AuditEvent.

User не может self-escalate через payload/API. Role/status/owner fields должны быть защищены от mass assignment.

# User disable / enable

Только admin может менять status другого пользователя.

Disable:

- проверяет last-admin invariant;
- блокирует future login;
- invalidates target user sessions;
- создаёт audit event.

Enable:

- возвращает возможность login;
- не создаёт session автоматически;
- создаёт audit event.

# Bootstrap first admin

Первый admin создаётся operator-controlled CLI command.

Не использовать default credentials, committed seed password, «first registered becomes admin» или public bootstrap endpoint.

CLI должен безопасно принимать email/password, не печатать/логировать plaintext password, применять те же validation/hashing rules, создавать ACTIVE admin и безопасно обрабатывать повторный bootstrap.

# Invitation model

Minimum concepts:

```text
id
created_by_user_id
token_hash
target_email nullable
max_uses
uses_count
expires_at
revoked_at nullable
created_at
updated_at
```

Status derived from actual state. Не хранить mutable duplicated status, если его можно вычислить надёжно.

Canonical derived statuses:

```text
ACTIVE
EXHAUSTED
EXPIRED
REVOKED
```

## Targeted invitation

```text
target_email != null
max_uses = 1
```

Нельзя создать targeted invite с `max_uses > 1`.

## Generic invitation

```text
target_email = null
max_uses = 1..10
```

Default `max_uses = 1`.

## Expiry

Expiry обязательна.

```text
default lifetime = 7 days
maximum lifetime = 30 days
```

Frontend time не authoritative. Expiry проверяется server-side.

## Atomicity

Invariant:

```text
uses_count <= max_uses
```

Два concurrent requests на последний remaining use не должны создать два accounts.

Проверка invitation, account creation и consumption должны использовать transaction/locking/atomic update strategy, соответствующую PostgreSQL/Laravel implementation.

## Token security

Token:

- cryptographically secure;
- не хранится reusable plaintext;
- проверяется через safe hash/storage strategy;
- не логируется;
- не попадает в audit/error payload;
- не восстанавливается из DB representation.

Plaintext token выдаётся только один раз в successful invitation creation response.

После этого API никогда не возвращает token снова. Если token потерян: revoke old invitation → create new invitation.

## Invitation delivery

Phase 09 не отправляет email. Frontend после create показывает one-time token или registration URL и copy action. Delivery manual/out-of-band.

## Target email

Если `target_email != null`, normalized registration email должен совпадать. Mismatch возвращает safe validation error без account-enumeration leakage.

Если `target_email == null`, любой valid normalized email может зарегистрироваться при соблюдении invitation limits.

## Invitation administration

Admin UI/API позволяет create/list/revoke ACTIVE invitation.

List показывает metadata:

```text
id
derived status
target_email nullable
uses_count
max_uses
created_by
created_at
expires_at
revoked_at
```

Plaintext token не показывается повторно. Expired/exhausted/revoked invitations сохраняются как operational history.

# Registration

Minimum input:

```text
invitation_token
email
password
password_confirmation
```

Не собирать first/last/display name или Career data.

Canonical workflow:

```text
validate token/state/expiry/revocation/remaining usage/target email
→ validate normalized email uniqueness
→ validate password
→ transaction
→ create User(role=user, status=ACTIVE)
→ consume invitation atomically
→ create required audit events
→ commit
→ establish authenticated Laravel session
→ regenerate session identifier
→ return current-user state
```

Public account creation без valid invitation невозможен. Invitation registration никогда не создаёт admin.

# Login / logout / sessions

Login input:

```text
email
password
```

Server normalizes email, rejects DISABLED users, validates password using framework primitive, applies rate limiting, regenerates session identifier after success и emits audit/security events.

Invalid email и invalid password должны по возможности иметь indistinguishable external behavior.

Ordinary logout завершает current session only.

Несколько simultaneous sessions разрешены, например Mac browser + iPhone PWA.

Phase 09 не реализует active-device/session list, remote logout one session или logout-all UI.

## Security-triggered session invalidation

Следующие события invalidates existing sessions target user:

```text
user disabled
role changed
operator password recovery
```

Password change требует current password; после success current session safely rotates/re-authenticates, а остальные sessions invalidated.

Role changes не должны позволять старым sessions продолжать работать со старым security context.

Custom `Remember me` в Phase 09 не реализуется.

# Password policy

```text
minimum length = 15
maximum supported input = 128
```

Разрешать spaces/passphrases и Unicode там, где framework implementation безопасно поддерживает его.

Не требовать artificial composition rules (uppercase/lowercase/digit/special combination) и periodic forced rotation.

Password никогда не хранится plaintext, не reversible-encrypted и не логируется. Exact hashing algorithm/cost выбирается по current official framework/security research.

# Password change

Authenticated user меняет password через:

```text
current_password
new_password
new_password_confirmation
```

После success: hash updated, audit emitted, current session rotated/re-authenticated, other sessions invalidated.

User не может менять чужой password. Admin не устанавливает user password через normal admin UI/API.

# Password recovery

Self-service Forgot Password не реализуется.

Для MVP создать operator-controlled CLI recovery mechanism.

Он должен безопасно resolve target user, принимать compliant new password, не выводить/log plaintext, invalidate all existing sessions и создать security/audit record без credential material.

Перед production self-service recovery отдельно решить email verification + reset-token lifecycle.

# Authorization foundation

Authorization enforced server-side.

Invariant:

```text
User A must never read, mutate, delete or enumerate
private resource owned by User B,
including nested and indirect relationships.
```

Не доверять `user_id`, owner IDs, role или tenant-like identifiers из frontend/request body как authoritative ownership.

Создать reusable conventions для query scoping, resource loading, policies, nested authorization, admin operations, ownership и tests. Избегать scattered ad-hoc owner comparisons.

## 404 vs 403

Если User A обращается по ID к private resource User B, отвечать как `404 Not Found`, чтобы не подтверждать существование чужого resource.

Для known capability boundary, например normal user вызывает admin API, использовать `403 Forbidden`.

Применять convention последовательно и покрыть tests.

## Ownership test fixture

Не создавать production Career/Vacancy/Application entities ради Phase 09 test.

Предпочесть framework-level authorization fixture или isolated test-only model/schema. Production generic resource использовать только если действительно требуется и documented why.

# Encrypted secrets foundation

Реализовать minimal persisted `EncryptedSecret` primitive.

Conceptually:

```text
EncryptedSecret
- id
- ownership_scope: USER | SYSTEM
- user_id nullable according to scope
- type
- name
- encrypted_value
- created_at
- updated_at
```

Exact schema соответствует Phase 06 data design/repository conventions.

Secret value:

- encrypted at rest;
- metadata separated from ciphertext;
- never returned plaintext after storage;
- never logged/serialized into errors/frontend persistence/audit;
- no plaintext reveal endpoint.

Allowed operations: create, update/rotate, delete, read masked metadata.

USER secret принадлежит одному User; только owner имеет allowed user-secret operations. Admin не получает secret-value bypass.

SYSTEM secret управляется explicit admin/system authorization boundary. Normal user не может mutate SYSTEM secret.

Не добавлять provider-specific semantics.

# Audit policy

Persistent AuditEvent использовать для successful/security-significant state changes минимум:

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
admin.critical_action
```

Exact naming следует existing event conventions.

Invitation expiry является derived state; не создавать background event/job только из-за наступления `expires_at`.

Не записывать каждую invalid-password attempt как permanent AuditEvent. Login failures идут в structured security logging/rate-limit telemetry/observability с privacy-safe metadata.

Audit payload никогда не содержит passwords, hashes, invitation plaintext token, session IDs, CSRF/auth tokens, API keys, secret plaintext или unnecessary PII.

# API design

Документировать только реально реализованные endpoints в `/api/v1` согласно accepted response/error conventions.

Expected logical capabilities:

Public/auth:

```text
register using invitation
login
logout
current user/session
change password
```

Admin:

```text
create/list/revoke invitation
list users
change user role
disable user
enable user
```

Secrets — только если implementation exposes API in this phase:

```text
USER secret metadata/create/rotate/delete
admin SYSTEM secret operations
```

Не создавать speculative future endpoints.

# Frontend scope

Minimum public/authenticated surfaces:

- login;
- invite registration;
- logged-in app shell/current-user state;
- logout;
- password change.

Minimum admin surfaces:

- invitation list;
- create invitation;
- one-time token/URL presentation;
- revoke invitation;
- minimal user list;
- change role;
- enable/disable user.

Не строить Career/Vacancy/Application placeholders или fake business data.

Use Phase 07 design system/Figma source of truth. Обработать loading/empty/success/error/validation/unauthorized/disabled/destructive confirmation, keyboard/focus accessibility и responsive behavior.

# Security requirements

## Sessions/cookies

Обязательно:

- fixation protection;
- regeneration after auth;
- CSRF;
- appropriate SameSite strategy;
- HttpOnly where applicable;
- Secure cookies in secure environments;
- no auth secret in localStorage;
- logout invalidation;
- security-change invalidation.

Document exact environment settings.

## Rate limiting

Применить минимум к login, invitation validation/registration и admin invitation creation where abuse relevant.

Exact thresholds — configurable implementation/security choice based on current guidance. Не спрашивать пользователя requests/minute без реальной необходимости.

Не основывать rate limiting только на attacker-controlled identifier.

## Account enumeration

Не раскрывать лишнее через различия invalid email/password, disabled account, invitation mismatch, existing email там, где disclosure не нужен UX.

Authenticated admin UI может показывать system accounts согласно роли.

## IDOR / cross-user

Проверить direct IDs, lists, nested access, mutation/delete, secrets ownership, indirect relations и guessed identifiers.

## Logging

Redact passwords, invitation tokens, session IDs, CSRF/security tokens, API keys и secret plaintext.

# Database / migrations

Создавать только schema, необходимую auth/security foundation.

Учитывать accepted ID convention, normalized email uniqueness, foreign keys, invitation hash indexes, usage/expiry queries, ownership references, secret scope constraints, timestamps, rollback и M0 compatibility.

Database constraints должны усиливать invariants где разумно.

Не создавать Phase 10+ tables.

# Deterministic before AI

LLM не используется нигде в Phase 09.

Не использовать AI для login, password/invitation validation, normalization, authorization, roles, secrets, rate limiting или audit semantics.

# Tests

Automated tests mandatory.

## Invitations

Cover:

- targeted and generic successful registration;
- invalid/expired/revoked/exhausted token;
- normalized target-email match/mismatch;
- targeted `max_uses > 1` rejected;
- generic `max_uses` boundaries 1..10;
- default expiry 7 days;
- lifetime >30 days rejected;
- registration without invitation rejected;
- token one-time reveal and no later retrieval;
- concurrent last-use boundary;
- `uses_count` never exceeds `max_uses`.

## Registration/authentication

Cover:

- created role always `user`;
- created status ACTIVE;
- duplicate normalized email safely rejected;
- automatic authenticated session;
- session ID regeneration;
- invitation/user atomicity;
- valid login;
- invalid email/password generic behavior;
- disabled user cannot login;
- current-user protected access;
- logout current session only;
- another valid session survives ordinary logout;
- CSRF/fixation/rate limiting.

## Passwords

Cover min<15 rejected, passphrase/spaces accepted, max semantics, current password required, successful change, other sessions invalidated, password never serialized/logged.

## Roles/status

Cover normal user cannot access admin API; self-escalation rejected; admin can promote/demote when invariant allows; last active admin cannot be demoted/disabled; role/status changes invalidate target sessions and are audited; re-enabled user can login.

## Ownership/admin privacy

Mandatory:

```text
User A cannot read/update/delete/enumerate User B resource
User A cannot access nested User B resource through indirect ID
admin role alone does not bypass private-resource ownership
```

Use real authorization boundary, not mocked policy returns.

Private foreign resource unauthorized lookup → 404. Normal-user admin capability denial → 403.

## Secrets

Cover plaintext not stored/returned, user-owner isolation, admin cannot read another user's plaintext secret, SYSTEM admin boundary, normal user cannot mutate SYSTEM secret, rotate/delete, safe masking, audit/log redaction.

## Bootstrap/recovery

Where testable: bootstrap CLI creates ACTIVE admin, applies password policy and does not output plaintext; operator recovery validates target/new password, invalidates sessions and audits safely.

# Validation

Run actual project commands.

Minimum:

- targeted backend auth tests;
- invitation concurrency tests;
- authorization negative suite;
- secret tests;
- frontend auth/admin tests if changed;
- lint/static analysis/type checks;
- migration up/down or documented safe rollback validation;
- OpenAPI validation;
- relevant `make test` targets;
- final cross-user regression suite.

Use narrow validation during iteration; run complete relevant Phase 09 regression before PASS. Never claim a command/test ran if it did not.

# Documentation

Update auth/authorization/session/invitation/role/status/ownership/secrets/security docs, OpenAPI, local setup when auth env changes, data-model implementation mapping/ERD where applicable, documentation map/index and project state.

Document limitations explicitly:

- no email verification;
- no self-service password reset;
- no email change/account deletion;
- no MFA;
- no PAT/native auth;
- no session-management UI.

If implementation materially differs from Phase 06 design, reconcile explicitly and use ADR only for real architecture changes.

# Completion criteria

Phase 09 PASS only if:

## Bootstrap/Auth

- first admin can be created securely via operator CLI;
- no default credentials;
- registration never creates admin;
- Sanctum/session auth works;
- invite-only registration/login/logout/current-user work;
- automatic login after registration works;
- CSRF/session protections work;
- public registration without invitation blocked.

## Identity/Invitations

- email is sole login identity with deterministic normalization/uniqueness;
- username/email mutation endpoints absent;
- targeted invites single-use;
- generic invites 1..10;
- default uses=1, default expiry=7d, max lifetime=30d;
- expiry/revocation/usage enforced server-side;
- concurrent overuse impossible;
- token one-time reveal only;
- invitation history preserved.

## Roles/Status/Sessions

- admin/user enforced server-side;
- self-escalation impossible;
- last ACTIVE admin protected;
- ACTIVE/DISABLED works;
- disabled user cannot authenticate;
- required security changes invalidate sessions;
- ordinary logout affects current session only;
- no remember-me feature introduced.

## Passwords/Recovery

- minimum 15/passphrases supported;
- password change requires current password;
- self-service forgot-password absent;
- operator recovery exists;
- plaintext password never stored/logged.

## Authorization/Secrets/Audit

- reusable ownership pattern exists;
- direct/nested/list/mutation negative tests pass;
- private foreign resource → 404; forbidden admin capability → 403;
- admin is not private-data superuser;
- encrypted USER/SYSTEM secret primitive exists with owner/system authorization;
- no plaintext round-trip/logging;
- required security mutations auditable without secret material.

## API/Frontend/Validation

- implemented endpoints documented in OpenAPI;
- login/invite registration/auth shell/logout/password/admin invitation/admin user surfaces exist;
- no fake Career/Vacancy UI;
- mandatory tests/migrations/lint/static/type checks pass;
- no validation falsely claimed.

## Scope

- no Career/Vacancy/Application implementation;
- no runtime AI;
- no OAuth/JWT/PAT/native auth;
- Phase 10 not started.

# State update

After PASS:

- `STATUS.md`: Phase 09 completed, auth/session/invitation/role/status/password/recovery/ownership/secrets strategy, migrations, tests, limitations;
- `NEXT.md`: `10-career-foundation`;
- `BLOCKERS.md`: real blockers only.

If cross-user isolation, last-admin invariant, invitation atomicity, secret redaction or mandatory session security fails, Phase 09 cannot be marked PASS.

# Final report

Выведи кратко:

1. Result — PASS/PARTIAL/BLOCKED.
2. Files/migrations/endpoints changed.
3. Implemented auth/session/invitation/role/status model.
4. Security/ownership/secrets/audit controls.
5. Validation/tests actually executed.
6. Real blockers/limitations.
7. Completed phase and exact next phase.

Не повторяй содержание этой specification.

# STOP

После Phase 09 остановись.

Не создавать CareerProfile/CareerFact/CareerTrack, resume import, vacancy ingestion, applications или runtime LLM functionality.

Не начинать Phase 10 в этой session.
