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

После Phase 09 система должна поддерживать безопасный invite-only multi-user access, role boundaries, ownership foundation, auditable invitation lifecycle и secret-storage foundation, достаточные для последующих domain phases.

## Execution mode

Следуй `/AGENTS.md`, applicable scoped `AGENTS.md`, resource policy, accepted ADR и Phase 06/08 contracts.

Default:

- один агент;
- sequential implementation;
- targeted context;
- deterministic authorization before convenience abstractions;
- narrow tests while iterating;
- final auth/security regression suite before completion;
- no subagents unless correctness requires it.

## Minimum context

Прочитай:

1. `PROJECT.md`.
2. current state.
3. accepted ADR по invite-only, multi-user ownership/isolation, API-first, PostgreSQL, security boundaries.
4. Phase 06 Ownership/Data/Security design.
5. current backend/frontend scoped `AGENTS.md`.
6. current API/auth research only for package/capability questions not already decided.
7. current Phase 08 implementation structure/configuration.

Не перечитывай unrelated research/docs.

## Current dependency research

Если authentication package/session strategy не frozen accepted ADR, проверь current official Laravel-supported approach and compatibility with current framework version before choosing package/configuration.

Не придумывай OAuth/token flow только потому, что он распространён. Выбери минимально sufficient first-party web auth consistent with API/PWA architecture and future clients.

Material architecture changes требуют ADR; ordinary implementation/package configuration does not.

# Scope

Реализовать только:

- invite-only registration;
- invitation lifecycle;
- login;
- logout;
- authenticated session/API boundary;
- roles `admin` and `user`;
- authorization policies/foundation;
- ownership enforcement patterns;
- invitation expiry;
- invitation usage limits;
- optional invitation target email;
- safe password handling;
- encrypted secrets foundation;
- security/audit events relevant to auth;
- rate limiting/abuse controls appropriate to auth;
- OpenAPI/auth documentation;
- automated authorization/security tests.

# Non-goals

НЕ реализовывать:

- CareerProfile/CareerFact;
- vacancies;
- applications;
- resumes/cover letters;
- Employer Memory;
- runtime LLM workflows;
- provider credentials UI beyond generic encrypted-secret foundation if needed;
- social login;
- SSO/SAML;
- passwordless magic links unless explicitly accepted requirement exists;
- MFA unless Phase 06 requirements make it MVP-blocking;
- organization/team tenancy;
- complex RBAC/permissions matrix beyond MVP roles;
- billing/subscriptions;
- public self-registration;
- Phase 10 functionality.

# Identity Model

Implement identity model consistent with Phase 06 conceptual design.

Minimum concepts:

```text
User
Invitation
Role (admin | user)
UserSetting only if Phase 09 genuinely owns an auth/security setting
AuditEvent
EncryptedSecret / credential abstraction only if design calls for it
```

Do not create generic ACL tables or permission engines without requirement.

## User

Minimum design/implementation concerns:

- stable identifier;
- role;
- email/login identity according to requirements;
- password hash, never plaintext/reversible password;
- active/disabled semantics if required by PRD;
- timestamps;
- ownership root for future private resources;
- no user-controlled role escalation.

## Roles

MVP roles:

```text
admin
user
```

Define exact privileges.

`admin` does not automatically mean unrestricted access to private candidate data unless Phase 06 requirements explicitly permit it. If admin support access exists, document scope and audit it.

Do not implement hidden superuser bypasses.

# Invitation Model

Invitation must support minimum:

- creator/admin actor;
- secure invitation token or equivalent;
- token hash/storage strategy;
- created_at;
- expires_at;
- revoked_at where applicable;
- used_at / usage tracking;
- usage limit;
- current usage count or equivalent deterministic mechanism;
- optional target email;
- status derived from actual state;
- audit events.

## Token security

Invitation secret/token:

- generated cryptographically securely;
- should not be stored in reusable plaintext if hash verification is feasible;
- must not be logged;
- must not appear in audit event payload after required creation response;
- must be protected from reuse beyond configured limit;
- expiry checked server-side;
- revocation checked server-side.

Define safe behavior for leaked/expired/revoked tokens.

## Optional target email

If invitation has target email:

- registration identity must match according to normalized comparison rules;
- mismatch returns safe validation error;
- do not leak unnecessary account existence data.

If no target email, invitation may register allowed account according to requirements.

# Registration Workflow

Implement deterministic invite-only workflow conceptually:

```text
admin creates invitation
→ invitation token delivered out-of-band/manual flow
→ user opens registration
→ server validates token state/expiry/usage/email constraint
→ validates registration input
→ creates user atomically
→ consumes invitation atomically
→ emits audit events
→ establishes authenticated state if product flow requires it
```

Race conditions must not permit invitation overuse.

Use transaction/locking/atomic update strategy appropriate to database implementation.

No public registration endpoint should allow account creation without valid invitation.

# Login / Logout

Implement secure login/logout according to chosen supported auth strategy.

Minimum controls:

- password verification through framework primitives;
- session/token rotation where relevant;
- logout invalidation;
- rate limiting;
- generic authentication errors avoiding useful credential enumeration where appropriate;
- CSRF protection for cookie/session-based state-changing requests;
- secure cookie flags per environment strategy;
- no tokens/API secrets in logs.

If frontend/backend are same-site through Nginx in local architecture, prefer simplest secure first-party flow consistent with accepted design.

# Authorization Foundation

Authorization must be enforced server-side.

Create clear patterns/policies for future owned resources.

Invariant:

```text
User A must never read, mutate, delete or enumerate
private resource owned by User B,
including nested and indirect relationships.
```

Never trust `user_id`, owner IDs, role or tenant-like identifiers supplied by frontend.

Owner is derived from authenticated user and server-side relationships.

## Policy pattern

Define conventions for:

- query scoping;
- route model binding / resource loading;
- authorization policy checks;
- nested resource authorization;
- not-found vs forbidden behavior where information disclosure matters;
- admin access;
- tests.

Avoid scattered ad-hoc owner comparisons.

# Ownership Test Fixtures

Create minimal private dummy/test resource only if required to prove ownership pattern without prematurely implementing Phase 10 domain model.

Prefer framework-level authorization test fixture or dedicated test-only model/schema where cleanly isolated.

Do not create fake Career/Vacancy production domain just to test ownership.

If a real production resource is required for foundation, use the smallest architecture-approved generic resource and document why.

# Encrypted Secrets Foundation

Phase 09 should establish reusable safe secret handling for future system-managed/BYOK credentials without implementing full LLM provider UI.

Define/implement minimum safe primitive according to Phase 06 design:

- encryption at rest using framework-supported encryption/key management boundary;
- owner/type/name metadata separated from encrypted secret value;
- masked presentation;
- secret value never returned after storage unless architecture explicitly requires one-time reveal;
- rotation/update path;
- deletion;
- authorization;
- audit event without plaintext secret;
- redaction from logs/errors/serialization;
- no secret in frontend persistence.

If full generic secret storage would be premature, implement only the infrastructure/contract that Phase 10+ can safely extend. Do not build a generic vault product.

# Audit Events

Implement audit events minimum for:

- invitation created;
- invitation revoked;
- invitation used;
- invitation expired only if explicit event generation is useful and deterministic;
- user registered;
- login success/failure only according to privacy/volume policy;
- logout where useful;
- role changed;
- user disabled/enabled if supported;
- encrypted credential/secret added/rotated/deleted;
- critical admin authorization actions.

Audit entries must not contain passwords, invitation token plaintext, session identifiers, API keys or secret values.

# API Design

Update OpenAPI/contracts for implemented endpoints only.

Expected surface may include:

- invitation administration;
- registration;
- login/logout/current user/session state;
- minimal user/admin endpoints required by MVP.

Follow accepted API version/error/pagination conventions.

Do not create speculative endpoints for future domains.

## Validation errors

Use consistent structured errors.

Do not reveal:

- whether arbitrary email has an account more than necessary;
- invitation token internals;
- password/hash information;
- authorization internals.

# Frontend Scope

Implement only frontend required to exercise Phase 09 auth flows if frontend implementation is part of current architecture.

Minimum possible screens/patterns:

- login;
- invite registration;
- logged-in shell/current-user state;
- logout;
- admin invitation management only if required for MVP execution.

Use Phase 07 design tokens/components principles.

Do not build Career/Vacancy dashboard placeholders with fake business data.

# Security Requirements

## Passwords

- framework-supported secure hash;
- password validation according to requirements;
- never log/store plaintext;
- no reversible encryption for passwords.

## Sessions / Tokens

- secure lifecycle;
- CSRF defense when relevant;
- rotation/fixation protection;
- appropriate cookie attributes;
- no localStorage auth secret if accepted first-party session architecture does not require it.

## Rate limiting

At minimum consider:

- login;
- registration/invitation validation;
- invitation creation if abuse relevant.

Rate limiting must not rely solely on user-controlled identifiers.

## IDOR / Cross-user

Authorization tests must include direct IDs, nested resources, enumeration/list endpoints and mutation paths where available.

## Mass assignment

Protect role/owner/security fields from request mass assignment.

## Email/account enumeration

Review response differences and rate limits.

## Logging

Ensure redaction for:

- passwords;
- invitation tokens;
- auth/session tokens;
- encrypted secret plaintext;
- CSRF/security tokens where applicable.

# Database / Migrations

Phase 09 may create migrations required for auth/multi-user foundation.

For each migration consider:

- UUID/ID convention from data design;
- uniqueness;
- normalized email uniqueness if applicable;
- invitation token hash indexes;
- expiry/status query indexes where justified;
- foreign keys;
- owner references;
- timestamps;
- rollback behavior;
- backward compatibility from M0 empty baseline.

Do not create Phase 10+ domain tables.

# Deterministic Before AI

No LLM should be needed for authentication/authorization logic.

Do not introduce AI into:

- invitation validation;
- role decisions;
- authorization;
- secret handling;
- rate limiting;
- audit semantics.

# Tests

Automated tests are mandatory.

## Invitation tests

Cover minimum:

- valid invitation registration;
- invalid token;
- expired invitation;
- revoked invitation;
- invitation reuse beyond allowed usage;
- usage count boundary;
- optional target email match;
- target email mismatch;
- concurrent/double-use behavior where practical;
- registration without invitation rejected.

## Authentication tests

Cover:

- valid login;
- invalid credentials;
- logout invalidates authenticated state;
- unauthenticated protected access rejected;
- session/CSRF behavior appropriate to implementation;
- rate limiting where deterministic to test.

## Role tests

Cover:

- normal user cannot perform admin invitation operations;
- admin can perform explicitly allowed admin operations;
- role cannot be escalated through request payload;
- unauthorized role mutations rejected.

## Ownership tests

Mandatory invariant tests:

```text
user A cannot read user B resource
user A cannot update user B resource
user A cannot delete user B resource
user A cannot enumerate user B resource
user A cannot access nested user B resource through indirect ID
```

Use real authorization boundary, not mocked policy return values.

## Secret tests

If encrypted-secret foundation implemented:

- plaintext not stored;
- serialization/API never returns plaintext;
- owner isolation;
- rotate/update works;
- delete works;
- audit contains metadata but not value;
- logs/test output do not expose secret.

# Validation

Run actual project commands from repository.

Minimum:

- backend unit/feature/integration tests relevant to auth;
- frontend auth tests if frontend changed;
- lint/static/type checks;
- migration up/down or documented safe rollback validation;
- OpenAPI validation if repository tooling exists;
- `make test`/targeted equivalent according to M0 workflow;
- final cross-user negative suite.

Do not rerun full suites unnecessarily during iteration, but final Phase 09 acceptance requires relevant regression checks.

# Documentation

Update:

- auth/authorization design docs with actual implementation choices;
- OpenAPI;
- local setup if auth env changes;
- security docs if controls became concrete;
- data model/ERD implementation mapping if relevant;
- documentation map/index;
- state.

If implementation differs materially from Phase 06 design, reconcile explicitly and use ADR when architecture changes.

# Completion Criteria

Phase 09 PASS only if:

## Authentication

- invite-only registration works;
- login works;
- logout works;
- public registration without invitation blocked;
- auth state usable by frontend/API boundary.

## Invitations

- expiry enforced server-side;
- revocation enforced;
- usage limit enforced atomically;
- optional target email enforced;
- token handled securely;
- invitation reuse test passes.

## Roles

- admin/user roles implemented;
- server-side role authorization implemented;
- user cannot self-escalate role.

## Ownership

- reusable ownership authorization pattern exists;
- direct/nested/list/mutation cross-user tests cover the foundation;
- user A cannot read or mutate user B resources.

## Secrets

- encrypted secret foundation exists to the level required by design;
- secrets masked/redacted;
- no plaintext round-trip/logging;
- owner authorization enforced.

## Audit

- required auth/security events are auditable;
- no sensitive secret material in audit payload.

## API / Frontend

- implemented endpoints documented in OpenAPI;
- frontend auth surfaces, if required, use design foundation and do not expose secrets;
- errors are structured and safe.

## Tests / Validation

- mandatory automated tests pass;
- migrations validated;
- relevant lint/type/static checks pass;
- no validation falsely claimed.

## Scope

- no Career/Vacancy/Application domain implementation;
- no runtime AI feature implementation;
- Phase 10 not started.

# State update

After PASS:

- `STATUS.md`: Phase 09 completed, auth strategy, migrations, tests, security controls, known limitations;
- `NEXT.md`: expected `10-career-foundation`;
- `BLOCKERS.md`: only real blockers.

If cross-user isolation tests fail, Phase 09 cannot be completed.

# Final Report

Concise output:

1. Result.
2. Files/migrations/endpoints changed.
3. Auth/session strategy actually implemented.
4. Invitation/role/ownership/security controls.
5. Validation/tests actually executed.
6. Blockers/limitations.
7. Exact next phase.

# STOP

After Phase 09 stop.

Do not create CareerProfile/CareerFact.
Do not implement resume import.
Do not implement vacancy ingestion.
Do not start Phase 10 in the same session.