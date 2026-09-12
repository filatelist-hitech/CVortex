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
  - ../policies/git-workflow.md
  - ../policies/documentation.md
  - legacy/phase-09-hardened-pr17.md
---

# CVortex M1.1 — Access Core

## 1. Objective

Deliver the minimum secure access foundation required before CVortex stores private Career data.

Observable outcome:

- an operator can bootstrap the first admin through CLI;
- an operator can create and revoke one-time invitations through CLI;
- an invited person can register through the web UI;
- a registered user can sign in, load an authenticated shell/current-user state and sign out;
- ACTIVE/DISABLED account state is enforced on every authenticated request;
- ownership/isolation conventions exist and are proven with negative cross-user tests before Career resources are introduced;
- the implementation remains deliberately smaller than the legacy Phase 09 hardened-auth plan.

This task does **not** implement Career, Vacancy, Application or runtime AI capabilities.

---

## 2. Execution authority and preflight

### 2.1 Mandatory execution gate

This task may be implemented only when either:

```text
.agents/state/NEXT.md == m1-1-access-core
```

or the current user explicitly authorizes this bounded task as an override under the Git workflow policy.

If neither condition is true:

```text
STOP
Do not make product implementation changes.
```

Editing/refining this task document itself is documentation work and does not authorize M1.1 product implementation.

### 2.2 Repository preflight

Before the first implementation write:

1. read `PROJECT.md`;
2. read `.agents/state/NEXT.md`;
3. read this task in full;
4. read relevant scoped `AGENTS.md` files, if present;
5. read `.agents/policies/git-workflow.md` and `.agents/policies/documentation.md`;
6. inspect accepted ADRs/security docs that govern authentication, sessions, IDs and API conventions;
7. run the repository write preflight required by Git policy when available;
8. work only on a bounded short-lived branch based on current `stage`.

Do not write directly to `main` or `stage`.

### 2.3 Source precedence

Use accepted project sources before external guidance.

If current official framework/security research conflicts with an accepted project decision:

1. do not silently override the project decision;
2. identify the conflict;
3. update or propose the relevant ADR/documentation before changing architecture.

---

## 3. Source decision inventory

`legacy/phase-09-hardened-pr17.md` preserves the broader hardened authentication plan. M1.1 extracts only the subset needed for First Value.

Deferred unless required to close a concrete M1.1 security gap:

- broad admin UX;
- generic persisted secret management;
- BYOK/provider credential management;
- email-based password recovery;
- device/session management UI;
- role-management UI/API;
- Career/Vacancy/Application data.

Do not re-import legacy scope merely because it already exists in an older plan.

---

## 4. Fixed authentication architecture

First-party path:

```text
Next.js
→ same-origin Nginx
→ Laravel
→ Sanctum SPA/session authentication
```

Required invariants:

- secure session cookie;
- CSRF protection for state-changing authenticated requests;
- session ID regeneration after successful authentication and registration;
- no authentication secrets in `localStorage`;
- no JWT/PAT/bearer-token auth for the first-party web app;
- no OAuth/future-client auth flow in this slice.

Framework-specific middleware order, cookie names and exact Sanctum configuration are implementation details and must be chosen after checking current official Laravel/Sanctum documentation.

If the accepted architecture does not yet have ADR coverage for this authentication model, create or update the relevant ADR during implementation. A task file is not a substitute for a durable architecture decision.

---

## 5. Canonical domain vocabulary

Use these terms consistently. Do not introduce synonyms for the same concepts.

### 5.1 Operator

An **Operator** is a human with trusted deployment/CLI access.

Operator is **not**:

- an application `User` role;
- an implicit superuser record;
- a bypass into private user data.

Operator actions are explicit operational actions performed through CLI commands and recorded through the audit actor model where applicable.

### 5.2 User

A **User** is an authenticated application identity.

Canonical semantics:

```text
User
- stable_id
- normalized_email
- password_hash
- role: admin | user
- status: ACTIVE | DISABLED
- timestamps
```

Email is the only login identity in M1.1.

`admin` is an account/security capability marker. It is **not** private-data god mode.

An admin must not gain access to another user's private future resources merely because `role=admin`.

### 5.3 Invitation

An **Invitation** authorizes exactly one registration in M1.1.

Supported forms:

Targeted:

```text
target_email != null
max_uses = 1
```

Generic:

```text
target_email = null
max_uses = 1
```

Multi-use invitations are deferred.

Invitation status is derived, not stored as a mutable status column.

Canonical precedence:

```text
REVOKED
→ EXPIRED
→ EXHAUSTED
→ ACTIVE
```

Primary state comes from data such as:

- `revoked_at`;
- `expires_at`;
- one-time consumption state/usage count;
- creation timestamps.

Default expiry:

```text
7 days
```

Maximum expiry:

```text
30 days
```

### 5.4 AuditEvent

`AuditEvent` is a production, append-only application concept for security/business mutations implemented by this slice.

Minimum semantics:

```text
AuditEvent
- stable/event identifier
- event_type
- actor_type: USER | OPERATOR | SYSTEM
- actor_user_id: nullable
- subject type/id where applicable
- safe metadata JSON where useful
- occurred_at
```

Normal application code must not edit or delete existing audit events.

Retention/archival policy is outside M1.1.

### 5.5 Ownership

**Ownership** means a server-authoritative relationship between an authenticated `User` and a private resource.

The client must never be the authority for ownership.

Do not create a generic production `OwnedResource` model in M1.1. Use a small test-only/isolated ownership fixture to prove the policy convention before Career resources exist.

---

## 6. Global security invariants

The following invariants are non-negotiable:

```text
User A cannot read, mutate, delete or enumerate
private resources owned by User B.
```

```text
Admin role does not bypass private-data ownership.
```

```text
Frontend-supplied user_id / owner_id / role / scope
is never an authorization source.
```

```text
DISABLED users cannot continue using an existing authenticated session.
```

```text
Plaintext password, invitation token, session identifier,
CSRF/auth token or other secret is never persisted in audit/log output.
```

```text
Registration cannot partially create a User if invitation consumption fails.
```

Foreign private resource behavior:

```text
resource owned by another user → 404
known resource/capability but insufficient role → 403
```

This prevents resource-existence disclosure while retaining meaningful capability errors.

---

## 7. Email identity rules

Email is required and unique according to one canonical normalization/comparison path.

Create or use a single `EmailNormalizer`-style boundary shared by:

- registration;
- login;
- invitation target matching;
- uniqueness checks;
- operator CLI where email is accepted.

Do not duplicate ad hoc normalization logic across controllers, commands or validators.

Before implementation, verify current Laravel/PostgreSQL behavior and accepted project conventions, then document the exact normalization/comparison rule.

Do not silently guess RFC edge-case behavior.

If an email is already registered:

- a new registration attempt is rejected;
- the invitation is not consumed;
- no account merge/recovery occurs through the invitation flow.

---

## 8. Stable identifiers

Use non-sequential externally safe stable user identifiers.

Choose the Laravel-friendly concrete representation only after implementation research and accepted project conventions are checked.

Preferred direction:

```text
UUID or ULID
```

Do not expose auto-increment database identifiers as the intended public stable identity merely for convenience.

---

## 9. Operator bootstrap

Implement a dedicated operator-controlled CLI command for creating the first admin.

Required behavior:

- no default credentials;
- no committed seed password;
- no public bootstrap endpoint;
- no `first registered user becomes admin` behavior;
- plaintext password must not be logged;
- successful creation produces an appropriate `AuditEvent` with `actor_type=OPERATOR`;
- the created user is `ACTIVE` with `role=admin`.

### 9.1 Repeat behavior

If an admin already exists, the bootstrap command must fail safely and make no changes.

Do not:

- silently create another admin;
- overwrite an existing admin password;
- promote an arbitrary account.

Separate recovery or additional-admin workflows belong to later scoped work unless a proven blocker requires otherwise.

### 9.2 Role mutation

M1.1 does not provide user-to-admin promotion or general role mutation through CLI, API or UI.

---

## 10. Invitation management

Invitation creation and revocation are operator CLI operations in M1.1.

Do not build an admin API or admin UI solely to make the `admin` enum feel busy.

### 10.1 Token rules

Invitation token must be:

- generated cryptographically securely;
- stored only as a safe hash/verification form;
- shown in plaintext only once at creation;
- impossible to retrieve later in plaintext;
- excluded from logs, audit metadata, serialized API responses and exceptions.

### 10.2 Targeted invitation

For a targeted invitation:

```text
target_email != null
```

Registration succeeds only when the normalized registration email matches the normalized target email.

Mismatch rejects registration and does not consume the invitation.

### 10.3 Generic invitation

For a generic invitation:

```text
target_email = null
```

The registrant may supply any valid, currently unregistered email.

Generic invitations remain one-time in M1.1.

### 10.4 Revocation

Revocation marks the invitation through canonical primary data such as `revoked_at`.

Do not persist a separately mutable `status` field that can drift from source data.

### 10.5 Invite URL transport

Treat the invitation token as a secret.

Preferred browser transport:

```text
/register#token=<secret>
```

The frontend must:

1. read the fragment locally;
2. move the token into transient registration state;
3. immediately remove the token from the visible URL/history using `history.replaceState` or equivalent;
4. submit the token only in the registration request body.

The token must not be placed in a normal query string by default, because query parameters may leak through access logs, analytics, browser history or referrers.

If the current Next.js routing architecture makes the fragment approach materially unsafe or unworkable, stop and document the alternative plus its logging/referrer mitigations before implementation.

---

## 11. Registration flow

Web registration input is exactly:

```text
invitation token
email
password
password confirmation
```

No role, user ID, owner ID, status or scope input is accepted from the client.

### 11.1 Atomic transaction

The database portion of successful registration is one transaction:

```text
lock/validate invitation
→ validate target email when applicable
→ verify email is not registered
→ create ACTIVE User with role=user
→ consume one-time invitation
→ append AuditEvent(s)
→ COMMIT
```

Only after successful commit:

```text
establish authenticated session
→ regenerate session ID
→ continue to authenticated shell
```

If any transaction step fails, no partial User/invitation state may remain.

### 11.2 Concurrent use

For two concurrent attempts using the same one-time invitation:

- exactly one may commit successfully;
- the other must fail safely as no longer available;
- no duplicate/partial User may be created;
- the invitation must not be over-consumed.

Use database locking/constraints/transaction semantics, not LLM reasoning or best-effort application timing.

---

## 12. Login flow

Login input:

```text
email
password
```

Required behavior:

- use the canonical email normalization path;
- use framework-supported password verification;
- apply abuse rate limiting;
- regenerate session ID after success;
- do not reveal whether an unknown email exists;
- do not authenticate a DISABLED user.

Error semantics:

- unknown email and wrong password use the same generic credential error;
- after credentials are correctly verified for an existing account, a disabled-account message may be returned;
- do not expose unnecessary identity enumeration details.

Exact rate-limit thresholds must be configurable and chosen after checking current official Laravel/security guidance. Do not hardcode arbitrary numbers into architecture merely to satisfy the task text.

Successful login/logout belong in structured security/application logging as appropriate, not permanent `AuditEvent` records by default.

---

## 13. Session and disabled-account behavior

Multiple simultaneous sessions are allowed.

Normal logout invalidates the current session only.

The architecture must permit future invalidate-all behavior without redesigning authentication.

### 13.1 DISABLED enforcement

A user's `status` is checked/enforced on every authenticated request, not only at login.

A DISABLED user must lose authenticated access even if session invalidation fails or a stale session survives unexpectedly.

Implement operator CLI commands sufficient for this slice:

```text
user:disable
user:enable
```

Disabling a user must invalidate their active sessions using the framework/session architecture available to the application.

### 13.2 Last active admin invariant

The system must not allow an operation that leaves zero ACTIVE admins.

Therefore attempting to disable the sole ACTIVE admin must fail closed and make no changes.

No delete-user operation is part of M1.1.

No soft-delete lifecycle is introduced for `User` or `Invitation` in this slice.

---

## 14. Password policy

Use current framework-supported password hashing after official compatibility/security verification.

Password policy:

```text
minimum: 15 characters
maximum: 128 characters
spaces/passphrases: allowed
mandatory uppercase/lowercase/digit/symbol composition: no
periodic rotation: no
```

Plaintext passwords must never be:

- persisted;
- logged;
- audited;
- reversibly encrypted.

Self-service forgot-password/email recovery is outside M1.1.

Do not add email verification in M1.1.

---

## 15. Authorization foundation

Create one reusable server-side ownership/policy convention suitable for future private domain resources.

Prove it using a small test-only/isolated ownership fixture rather than inventing production Career/Vacancy models.

Required behavior:

- authenticated identity comes from the server-side session/principal;
- owner identity comes from the trusted resource relationship;
- client-provided ownership identifiers are ignored/rejected for authorization purposes;
- User A accessing User B's private fixture receives `404`;
- User A cannot mutate/delete/enumerate User B's private fixture;
- admin role does not bypass the same ownership rule;
- role mass assignment/self-escalation is impossible through request payloads.

Do not introduce a generic production `OwnedResource` abstraction without a real domain need.

---

## 16. Audit requirements

Persist append-only `AuditEvent` entries only for security/business mutations actually implemented by M1.1.

Expected event families include:

- first admin bootstrapped;
- invitation created;
- invitation revoked;
- invitation consumed;
- user registered;
- user disabled;
- user enabled;
- other operator/admin security mutations if they become explicitly part of this slice.

Do not persist permanent audit events merely for every successful login/logout unless an accepted security decision later requires it.

### 16.1 Actor model

Use:

```text
actor_type = USER | OPERATOR | SYSTEM
actor_user_id = nullable
```

Do not create fake `User` rows to represent CLI operators.

### 16.2 Secret exclusion

Audit/log metadata must never contain:

- plaintext passwords;
- invitation tokens;
- session IDs;
- CSRF/auth tokens;
- API keys or future provider secrets.

Use explicit serialization/redaction rules and tests.

---

## 17. Minimal HTTP/UI surface

Build only what First Value requires.

### 17.1 Web UI

Required screens/surfaces:

- login;
- invite registration;
- authenticated shell;
- logout action.

Authenticated shell should remain intentionally minimal, for example:

```text
/app
- normalized/current email
- role
- status
- logout
```

Do not create fake Career dashboards or placeholder business navigation.

Follow the accepted design/accessibility foundation for:

- keyboard interaction;
- focus handling;
- loading state;
- validation state;
- error state;
- permission state.

### 17.2 Current-user contract

Expose one minimal current-user endpoint/contract such as `/me` according to accepted API conventions.

Return only safe identity data required by the shell, for example:

```text
stable id
email
role
status
```

Do not serialize password/security/internal fields.

### 17.3 Admin surface

No admin invitation UI/API is required in M1.1.

The `admin` role may exist without a rich runtime admin surface in this slice.

---

## 18. Deterministic-before-AI boundary

No LLM is needed for M1.1 authentication, authorization, invitation consumption, rate limiting, validation, ownership or audit logic.

Use:

- database constraints;
- transactions/locking;
- framework authentication primitives;
- validators;
- policies/middleware;
- deterministic tests.

Do not add AI merely because CVortex is an AI-enabled product.

---

## 19. Required implementation research

Before fixing framework-specific implementation details, verify current official primary sources for the installed/selected versions of:

- Laravel authentication/session behavior;
- Laravel Sanctum SPA/session authentication;
- CSRF handling;
- password hashing support/configuration;
- rate limiting;
- session invalidation/storage behavior;
- PostgreSQL constraints/locking behavior relevant to invitation consumption;
- Next.js behavior needed for safe invitation fragment handling.

Prefer official documentation and primary sources.

Record material conclusions in code comments only when useful and in durable project docs/ADR when they affect architecture.

Do not pin models, prices or unrelated dependencies during this task.

---

## 20. Required tests

Automated coverage must prove behavior, not merely exercise happy paths.

### 20.1 Bootstrap

- first admin can be created safely;
- no default/seed credential path exists;
- repeat bootstrap with an existing admin makes no changes;
- plaintext password is absent from command/log/audit output.

### 20.2 Invitations

- targeted invitation valid path;
- targeted normalized email match;
- targeted email mismatch rejected without consumption;
- generic invitation accepts an unregistered valid email;
- invalid token rejected;
- expired invitation rejected;
- revoked invitation rejected;
- exhausted invitation rejected;
- token stored only in non-plaintext form;
- token shown only at creation;
- token cannot be retrieved later;
- token absent from serialization/audit/log output;
- concurrent use of one one-time invitation allows exactly one successful registration;
- duplicate email registration rejects without consuming invitation.

### 20.3 Registration

- registration without invitation rejected;
- valid registration creates `ACTIVE role=user`;
- role/status cannot be client-selected;
- transaction rollback prevents partial User/invitation state;
- registration establishes a session only after commit;
- session ID is regenerated.

### 20.4 Login/logout/session

- normalized email login succeeds;
- wrong password and unknown email do not enumerate identity;
- disabled account cannot log in;
- disabled authenticated account cannot continue accessing protected routes;
- current-session logout works;
- multiple sessions can coexist unless one is explicitly invalidated;
- CSRF/session protections behave as intended;
- deterministic rate-limit behavior is tested without coupling tests to arbitrary production thresholds.

### 20.5 Disable/enable

- operator can disable a normal ACTIVE user;
- disable invalidates active sessions;
- disabled user is blocked on subsequent authenticated requests;
- operator can re-enable the account;
- sole ACTIVE admin cannot be disabled;
- failed last-admin disable makes no partial changes.

### 20.6 Authorization

Using the isolated ownership fixture:

- owner can access own private resource;
- foreign user receives `404`;
- foreign user cannot mutate/delete/enumerate the resource;
- admin also cannot bypass foreign ownership;
- capability failure where existence is intentionally known returns `403`;
- mass-assignment/self-role escalation is blocked;
- client `user_id`/`owner_id` values do not control authorization.

### 20.7 Audit/redaction

- expected security/business mutations append audit events;
- actor type/user linkage is correct for USER/OPERATOR paths;
- audit events are not updated/deleted through normal application flows;
- sensitive values are absent from audit/log/serialized output.

### 20.8 Database and app validation

Run as applicable:

- migrations up;
- migrations down/rollback safety expected by project policy;
- backend automated tests;
- frontend tests/type checks/lint/build checks relevant to touched code;
- repository governance/security checks required by the project.

---

## 21. Documentation deliverables

Update durable Markdown documentation to match the implemented behavior.

At minimum verify/update as applicable:

- auth/session architecture docs;
- API/current-user contract docs;
- security/authorization conventions;
- operator CLI usage;
- invitation lifecycle;
- audit-event semantics;
- project state/NEXT transition;
- relevant ADR for material architecture decisions.

Do not create documentation that describes behavior not actually implemented.

Do not leave accepted architecture only in chat history or this task file when an ADR is required by documentation policy.

---

## 22. Non-goals / hard scope boundaries

Do not implement in M1.1 unless a concrete, documented security blocker makes it unavoidable:

- Career models or UI;
- Vacancy models or UI;
- Application generation;
- runtime AI/LLM features;
- generic `EncryptedSecret` persistence/API/UI;
- BYOK;
- system-managed provider secret UI;
- admin dashboard;
- admin invitation UI/API;
- rich admin user management;
- role promotion/demotion workflows;
- active-device/session UI;
- logout-all UI;
- email verification;
- forgot-password email flow;
- account merge through invitation;
- multi-use invitations;
- generic production `OwnedResource` abstraction;
- user deletion;
- soft-delete lifecycle added merely for completeness.

If an attractive adjacent feature appears, record it as follow-up work and continue with M1.1. Do not expand this slice opportunistically.

---

## 23. Acceptance criteria

M1.1 is PASS only when all applicable criteria are satisfied:

- [ ] execution authority/preflight was respected;
- [ ] first admin bootstrap works safely and repeat execution fails closed;
- [ ] operator CLI can create/revoke one-time targeted or generic invitations;
- [ ] invitation token is cryptographically secure, hashed at rest and shown once;
- [ ] invite token does not leak through normal URL query logging, audit or serialization paths;
- [ ] invited registration works atomically;
- [ ] concurrent use of the same invitation cannot create two accounts;
- [ ] duplicate email cannot consume an invitation;
- [ ] registration auto-establishes a regenerated authenticated session after commit;
- [ ] login/logout/current-user flow works;
- [ ] same-origin Sanctum session + CSRF is the implemented auth model;
- [ ] canonical email normalization/comparison is centralized and documented;
- [ ] stable non-sequential public user identity is used according to accepted project conventions;
- [ ] roles/status exist and cannot be client-escalated;
- [ ] DISABLED is enforced on every authenticated request;
- [ ] operator disable/enable path works and invalidates sessions;
- [ ] at least one ACTIVE admin is preserved;
- [ ] reusable ownership convention exists and negative tests pass;
- [ ] foreign private resource access returns `404`;
- [ ] admin has no private-data ownership bypass;
- [ ] AuditEvent is append-only at application level and supports USER/OPERATOR/SYSTEM actors;
- [ ] secrets are absent from logs/audit/serialized output;
- [ ] no generic secrets/BYOK/admin-console/Career scope leaked in prematurely;
- [ ] required tests and repository validation pass;
- [ ] docs/API/security/ADR state match the implementation.

---

## 24. State transition

Only after every acceptance criterion and required validation passes:

```text
.agents/state/NEXT.md = m1-2-career-core
```

Perform this transition only if the repository workflow allows the acting agent to update state as part of task completion.

If validation is incomplete or failing, do not advance `NEXT.md`.

---

## 25. STOP conditions

STOP and report the blocker instead of improvising when any of the following is true:

- M1.1 lacks execution authority and no explicit current-user override exists;
- implementation would require changing an accepted architecture decision without ADR/documentation reconciliation;
- safe session invalidation for DISABLED accounts cannot be achieved with the accepted architecture;
- invitation token handling would knowingly expose plaintext secrets in logs/audit/history without an accepted mitigation;
- atomic one-time invitation consumption cannot be guaranteed;
- authorization cannot prevent cross-user private-resource access;
- implementation requires adding Career/Vacancy/Application domain objects merely to make M1.1 tests pass;
- a requested adjacent feature would materially expand M1.1 beyond this task.

Do not begin M1.2 Career implementation from this task.
