---
title: CVortex M1.1 — Access Core
status: ready
milestone: m1-first-value
slice: m1-1-access-core
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [task, auth, authorization, invitations, security]
related:
  - ../../PROJECT.md
  - ../../docs/01-Product/Roadmap.md
  - legacy/phase-09-hardened-pr17.md
---

# CVortex M1.1 — Access Core

## Observable outcome

An operator can bootstrap an admin and create an invitation; an invited user can register, sign in, use an authenticated shell and sign out. Server-side identity/ownership conventions and cross-user negative tests exist before private Career data is introduced.

This is intentionally smaller than the old Phase 09.

## Source decision inventory

`legacy/phase-09-hardened-pr17.md` preserves the full hardened auth plan. M1.1 extracts only what First Value requires. Deferred admin UX, generic persisted secret management and broader account-security operations move to M2+ unless needed to close an actual M1 security gap.

## Fixed authentication model

First-party path:

```text
Next.js
→ same-origin Nginx
→ Laravel
→ Sanctum SPA/session auth
```

Use secure session cookie + CSRF. Do not introduce JWT, PAT/bearer auth, OAuth, localStorage auth secrets or future-client auth flows.

Email is the single login identity, required, server-normalized and unique by the same comparison rules used for targeted invitations.

## Minimal identity model

Production concepts for this slice:

```text
User
Invitation
AuditEvent
```

User fields/semantics include stable ID, normalized email, password hash, role (`admin|user`), status (`ACTIVE|DISABLED`) and timestamps.

No Career/Vacancy models exist yet.

Admin role is account/security capability, not a bypass into future private user data.

## Operator bootstrap

First admin is created only through an operator-controlled CLI command.

Never use default credentials, a committed seed password, public bootstrap endpoint or "first registered user becomes admin" logic.

The CLI must not print/log plaintext passwords.

## Invitations

Support targeted and generic invitations sufficient for M1:

Targeted:

```text
target_email != null
max_uses = 1
```

Generic:

```text
target_email = null
max_uses = 1..10
default = 1
```

Expiry:

```text
default = 7 days
maximum = 30 days
```

Invitation status is derived from data (`ACTIVE/EXHAUSTED/EXPIRED/REVOKED`).

Token is cryptographically secure, stored hashed, never logged/audited in plaintext and shown once at creation. Registration + invitation consumption must be atomic so concurrent requests cannot overuse the final slot.

For First Value, invitation create/revoke may be an authenticated admin endpoint **or** operator/admin CLI if that meaningfully reduces UI scope. Registration must be usable in the web UI. Record the chosen reversible implementation in docs; do not build a large admin console.

## Registration/login/logout

Registration input is only invitation token, email, password and confirmation.

Successful registration atomically:

```text
validate invitation
→ create ACTIVE user with role=user
→ consume invitation
→ audit
→ establish session
→ regenerate session ID
```

Login validates normalized email/password, rejects DISABLED users, rate-limits abuse and regenerates session on success.

Normal logout ends the current session.

Multiple simultaneous sessions may exist. Architecture must permit invalidating all user sessions later.

## Password policy

Use current framework-supported hashing after official compatibility/security verification.

Policy from hardened auth decision:

```text
min 15 characters
max 128 characters
passphrases/spaces allowed
no mandatory character-class composition
no periodic rotation
```

Plaintext passwords are never stored/logged/reversibly encrypted.

Self-service password recovery is not part of M1.1. If local testing requires recovery, use an operator-controlled path based on the hardened legacy decision; do not build email recovery yet.

## Authorization foundation

Invariant:

```text
User A cannot read, mutate, delete or enumerate
private resources owned by User B.
```

Do not trust frontend `user_id`, `owner_id`, role/scope identifiers.

Create a reusable server-side ownership/policy convention before Career data exists. Use test-only/isolated fixtures if needed; do not invent production Career/Vacancy resources just to test authorization.

Response convention:

- foreign private resource: `404`;
- known capability lacking role: `403`.

User cannot self-assign/admin-escalate role through request payload.

## Minimal account safety

Keep `ACTIVE/DISABLED` semantics in the model and enforce disabled-login/access behavior. If disable/role-change operations are exposed in this slice, they must preserve at least one active admin and invalidate target sessions as defined by the hardened legacy spec.

If those operations are not needed for First Value, do not build their full UI/API merely for completeness; retain the decision for M2.

## Audit

Persist only security/business events implemented by this slice, such as invitation create/revoke/use, registration, successful login/logout and operator/admin security changes actually exposed.

Never audit plaintext password, invitation token, session IDs, CSRF/auth tokens or secrets.

## Frontend

Build only:

- login;
- invite registration;
- authenticated shell/current-user state;
- logout.

Add minimal admin/operator surface only if the chosen invitation flow requires it. No Career/Vacancy dashboard placeholders.

Use Phase 07 design foundation for keyboard/focus/loading/validation/error/permission states.

## Non-goals

Defer unless a concrete security blocker requires them:

- generic `EncryptedSecret` persistence/API/UI;
- BYOK;
- system-managed provider secret UI;
- rich admin user management;
- role-management UI;
- active-device/session UI;
- email verification;
- forgot-password email flow;
- Career/Vacancy/Application data;
- runtime AI.

## Tests / validation

Mandatory automated coverage includes:

- valid/invalid/expired/revoked/exhausted invitation;
- targeted email match/mismatch;
- generic usage bounds and concurrent last-slot consumption;
- registration without invitation rejected;
- token one-time reveal/no plaintext persistence;
- login/logout/session regeneration;
- DISABLED account rejected;
- CSRF/session behavior;
- rate-limit behavior where deterministic;
- role mass-assignment/self-escalation blocked;
- 404 cross-user/private-resource convention using the ownership fixture;
- no sensitive values in serialization/audit/log output.

Run migrations up/down and relevant backend/frontend checks.

## Completion criteria

- [ ] bootstrap admin path works safely;
- [ ] invitation creation path exists without a large admin subsystem;
- [ ] invited registration works atomically;
- [ ] login/logout/current-user flow works;
- [ ] same-origin Sanctum/session + CSRF is the implemented auth model;
- [ ] roles/status exist and cannot be client-escalated;
- [ ] reusable ownership convention and negative tests pass;
- [ ] no private-data admin bypass exists;
- [ ] no generic secrets/BYOK scope leaked in prematurely;
- [ ] docs/API/state updated.

## State update

On PASS: `NEXT.md = m1-2-career-core`.

## STOP

Do not begin Career implementation in this task.
