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

## Observable outcome

Before CVortex stores private Career data, it has a small working access layer. An operator can bootstrap the first admin and manage one-time invitations from CLI. An invited person can register in the web app, sign in, reach a minimal authenticated shell and sign out. Disabled accounts lose access on existing sessions, and cross-user isolation is already enforced and tested.

Keep this slice smaller than the legacy Phase 09 plan. Career, Vacancy, Application and runtime AI stay out.

## Execution gate

Implement this task only when `.agents/state/NEXT.md` is `m1-1-access-core`, or when the current user gives a bounded override allowed by `.agents/policies/git-workflow.md`.

If neither condition is true:

```text
STOP
Do not make product implementation changes.
```

Editing this task document does not authorize product implementation.

### Repository preflight

Before the first implementation write:

1. read `PROJECT.md`;
2. read `.agents/state/NEXT.md`;
3. read this task in full;
4. read relevant scoped `AGENTS.md` files, if present;
5. read `.agents/policies/git-workflow.md` and `.agents/policies/documentation.md`;
6. inspect accepted ADRs and security docs governing authentication, sessions, IDs and API conventions;
7. run the repository write preflight required by Git policy when available;
8. work only on a bounded short-lived branch based on current `stage`.

Never write product changes directly to `main` or `stage`.

Accepted project decisions outrank external guidance. If current official framework or security research conflicts with an accepted project decision, identify the conflict and update or propose the relevant ADR before changing architecture.

## Scope

`legacy/phase-09-hardened-pr17.md` is requirement evidence, not active scope. M1.1 includes only the access foundation First Value needs.

Deferred unless a concrete M1.1 security blocker proves otherwise:

- broad admin UX;
- role-management UI/API;
- persisted secret/BYOK/provider-credential management;
- email verification or email-based password recovery;
- active-device/session UI;
- logout-all UI;
- user deletion;
- speculative soft deletes;
- multi-use invitations;
- generic production `OwnedResource` abstractions;
- Career/Vacancy/Application data;
- runtime AI.

Do not pull legacy features back in merely because they were designed earlier.

## Authentication model

The first-party path is fixed:

```text
Next.js → same-origin Nginx → Laravel → Sanctum SPA/session auth
```

Use secure session cookies and CSRF protection for state-changing authenticated requests. Regenerate the session ID after successful login and registration.

Do not:

- store auth material in browser persistent storage;
- introduce JWT/PAT/bearer auth for the first-party web app;
- introduce OAuth or future-client auth flows in this slice.

Middleware order, cookie names and exact Sanctum configuration are implementation details. Verify them against current official Laravel/Sanctum documentation. If this model lacks accepted ADR coverage, add or update the ADR during implementation.

## Domain vocabulary

Use these terms consistently.

**Operator** is a human with trusted deployment/CLI access. It is not an application role, fake superuser record or private-data bypass. Operator actions are explicit CLI operations and are audited where required below.

**User** is an application identity with a stable ID, normalized email, password hash, `admin|user` role, `ACTIVE|DISABLED` status and timestamps. Email is the only login identity in M1.1. `admin` is an account/security capability marker, not private-data god mode. Use a non-sequential public stable ID; choose UUID or ULID after checking current Laravel conventions and accepted project decisions.

**Invitation** authorizes exactly one registration. Targeted invitations have `target_email != null`; generic invitations have `target_email == null`; both have `max_uses=1`. Multi-use invitations are deferred. Default expiry is 7 days and maximum expiry is 30 days. Derive status from primary data rather than storing a second mutable status. Precedence is:

```text
REVOKED → EXPIRED → EXHAUSTED → ACTIVE
```

**AuditEvent** is a production append-only record with event identifier/type, `actor_type=USER|OPERATOR|SYSTEM`, nullable `actor_user_id`, optional subject, safe metadata and timestamp. Normal application code cannot edit or delete existing events. Do not create fake User rows for CLI operators.

**Ownership** is a server-authoritative relationship between a User and a private resource. The client is never its authority. Prove the convention with an isolated test-only fixture; do not invent a production `OwnedResource`, Career model or Vacancy model for these tests.

## Security invariants

These must hold regardless of controller or UI shape:

```text
User A cannot read, mutate, delete or enumerate private resources owned by User B.
Admin does not bypass private-resource ownership.
Client user_id / owner_id / role / scope values do not authorize anything.
DISABLED users cannot keep using an authenticated session.
Registration cannot leave a partial User when invitation consumption fails.
Sensitive auth/invitation values never enter normal logs, audit metadata or serialized responses.
```

Return `404` for a foreign private resource. Use `403` when the resource/capability is intentionally known but the authenticated principal lacks the required capability.

## Email identity

Use one normalization/comparison boundary for registration, login, targeted invitation matching, uniqueness checks and operator commands that accept email. Prefer a dedicated `EmailNormalizer`-style boundary to repeated controller cleanup.

Verify the exact rule against current Laravel/PostgreSQL behavior and accepted project conventions, then document it. Do not guess RFC edge cases.

If an email already belongs to a User:

- registration fails;
- the invitation is not consumed;
- no account merge or recovery occurs through the invitation flow.

## Operator flows

### First admin

Provide a dedicated CLI command that creates the first `ACTIVE admin`.

Required behavior:

- no default credentials;
- no committed seed credential;
- no public bootstrap endpoint;
- no “first registrant becomes admin” shortcut;
- sensitive input does not appear in logs;
- successful bootstrap appends an OPERATOR audit event.

If an admin already exists, the command fails safely and changes nothing. It does not create another admin, reset an existing account or promote an arbitrary user.

General role mutation is outside M1.1.

### Invitations

Creation and revocation are CLI operations. No admin invitation API/UI is needed.

Generate tokens cryptographically, persist only a verification-safe form and reveal plaintext once at creation. Existing tokens cannot be retrieved later in plaintext and must be excluded from logs, audit metadata, serialized responses and exceptions.

A targeted invitation requires normalized registration email to match normalized `target_email`. A generic invitation accepts any valid unregistered email. Rejected registration does not consume the invitation.

Revocation updates primary state such as `revoked_at` and appends the appropriate audit event.

### Disable/enable

Provide operator CLI commands equivalent to:

```text
user:disable
user:enable
```

Disabling invalidates active sessions. Status is also enforced on every authenticated request so a stale session cannot preserve access.

Never allow an operation to leave zero ACTIVE admins. Disabling the sole ACTIVE admin fails closed with no partial change.

## Invitation URL

Treat the invitation token as browser-sensitive data. Preferred transport is:

```text
/register#token=<value>
```

The frontend must:

1. read the fragment locally;
2. move it to transient registration state;
3. immediately remove it from the visible URL/history with `history.replaceState` or equivalent;
4. submit it only in the registration request body.

Do not use a normal query parameter by default because it can leak through access logs, analytics, history or referrers.

If the selected Next.js architecture makes fragment handling unsafe or impractical, stop and document the alternative and its logging/referrer mitigations before implementation.

## Registration

The web form accepts exactly:

```text
invitation token
email
password
password confirmation
```

It does not accept role, user ID, owner ID, status or scope.

The database portion is one transaction:

```text
lock/validate invitation
→ validate target email when applicable
→ verify email is unregistered
→ create ACTIVE role=user
→ consume invitation
→ append required AuditEvent(s)
→ COMMIT
```

Only after commit:

```text
establish authenticated session
→ regenerate session ID
→ continue to authenticated shell
```

Two concurrent attempts using one invitation must produce exactly one successful registration. The losing request fails safely, creates no partial User and cannot over-consume the invitation.

Use database constraints, locking and transaction semantics rather than timing assumptions.

## Login, logout and account state

Login accepts email/password, uses canonical email normalization and framework-supported verification, applies configurable abuse rate limiting and regenerates the session ID on success.

Error semantics:

- unknown email and wrong password use the same generic credential error;
- once valid credentials identify an existing account, the app may report that it is disabled;
- do not reveal unnecessary identity-enumeration details.

Multiple sessions per user are allowed. Normal logout invalidates only the current session. Keep the architecture capable of future invalidate-all behavior without redesigning auth.

Successful login/logout belong in structured security/application logs as appropriate, not permanent `AuditEvent` rows by default.

Choose rate-limit thresholds from current official Laravel/security guidance rather than arbitrary architecture constants.

Password policy:

```text
minimum: 15 characters
maximum: 128 characters
spaces/passphrases: allowed
mandatory character-class composition: no
periodic rotation: no
```

Use current framework-supported hashing after compatibility/security verification. Plaintext credentials are never persisted, logged, audited or reversibly encrypted.

Self-service recovery and email verification are deferred.

## Authorization foundation

Create one reusable server-side ownership/policy convention for future private resources and prove it with the isolated fixture.

Authenticated identity comes from the server-side session/principal. Resource ownership comes from a trusted server-side relationship.

The convention must prove:

- owner can access own private resource;
- foreign user receives `404`;
- foreign user cannot mutate, delete or enumerate the resource;
- admin cannot bypass foreign ownership;
- known capability denial returns `403` where applicable;
- role mass-assignment/self-escalation is impossible;
- client `user_id`/`owner_id` values do not control authorization.

Do not introduce a generic production ownership abstraction without a real domain need.

## Audit

Append audit events for:

- first-admin bootstrap;
- invitation create;
- invitation revoke;
- invitation consume;
- registration;
- disable/enable;
- any other security mutation explicitly added to this slice.

Actor semantics must distinguish `USER`, `OPERATOR` and `SYSTEM`.

Audit/log metadata must never contain:

- plaintext passwords;
- invitation tokens;
- session IDs;
- CSRF/auth tokens;
- API keys or future provider credentials.

Add explicit redaction/serialization tests. Do not rely on developer memory as a security control.

## HTTP/UI surface

Build only:

- login;
- invite registration;
- authenticated shell/current-user state;
- logout.

`/app` may show current email, role, status and logout. Do not add fake Career dashboards or placeholder business navigation.

Expose one minimal current-user contract such as `/me` following accepted API conventions. Return only safe identity data needed by the shell:

```text
stable id
email
role
status
```

Follow the accepted Phase 07 design/accessibility foundation for keyboard, focus, loading, validation, error and permission states.

No admin invitation UI/API is required.

## Deterministic-before-AI boundary

M1.1 needs no LLM.

Use deterministic mechanisms for auth and access control:

- framework authentication/session primitives;
- database constraints;
- transactions and locking;
- validators;
- policies/middleware;
- deterministic tests.

Do not add AI merely because CVortex is an AI-enabled product.

## Required implementation research

Before fixing framework-specific implementation details, verify current official primary sources for the selected/installed versions of:

- Laravel authentication and session behavior;
- Laravel Sanctum SPA/session authentication;
- CSRF handling;
- password hashing support/configuration;
- rate limiting;
- session invalidation and session-storage behavior;
- PostgreSQL constraints/locking needed for atomic one-time invitation consumption;
- Next.js behavior needed for safe invitation-fragment handling.

For each material result, decide whether it is:

- implementation configuration only;
- a durable project convention that belongs in documentation;
- an architecture decision that requires an ADR.

If official evidence conflicts with an accepted project decision, do not silently override the project. Surface the conflict and reconcile the relevant ADR/documentation first.

Do not pin unrelated dependencies, LLM models or prices in this task.

## Test matrix

Automated coverage must prove failure paths as well as happy paths.

### Bootstrap

- first admin can be created safely;
- no default/seed credential path exists;
- repeat bootstrap with an existing admin changes nothing;
- plaintext password is absent from command, log and audit output.

### Invitations

- targeted invitation valid path;
- targeted normalized-email match succeeds;
- targeted email mismatch is rejected without consumption;
- generic invitation accepts an unregistered valid email;
- invalid token rejected;
- expired invitation rejected;
- revoked invitation rejected;
- exhausted invitation rejected;
- token stored only in non-plaintext form;
- token shown only at creation;
- token cannot be retrieved later;
- token absent from serialization, audit and logs;
- duplicate-email registration fails without consuming the invitation;
- concurrent use of one invitation allows exactly one successful registration.

### Registration

- registration without invitation is rejected;
- valid registration creates `ACTIVE role=user`;
- role/status cannot be client-selected;
- transaction rollback prevents partial User/invitation state;
- session is established only after transaction commit;
- session ID is regenerated.

### Login / logout / session

- normalized-email login succeeds;
- wrong password and unknown email do not enumerate identity;
- disabled account cannot log in;
- disabled authenticated account cannot continue accessing protected routes;
- current-session logout works;
- multiple sessions can coexist;
- CSRF/session protections behave as intended;
- rate-limit behavior is tested deterministically without coupling tests to arbitrary production thresholds.

### Disable / enable

- operator can disable a normal ACTIVE user;
- disable invalidates active sessions;
- disabled user is blocked on subsequent authenticated requests;
- operator can re-enable the account;
- sole ACTIVE admin cannot be disabled;
- rejected last-admin disable makes no partial changes.

### Authorization

Using the isolated ownership fixture:

- owner can access own private resource;
- foreign user receives `404`;
- foreign user cannot mutate, delete or enumerate the resource;
- admin cannot bypass foreign ownership;
- capability failure where existence is intentionally known returns `403`;
- mass-assignment/self-role escalation is blocked;
- client `user_id`/`owner_id` values do not control authorization.

### Audit and redaction

- expected security/business mutations append audit events;
- actor type/user linkage is correct for USER/OPERATOR paths;
- audit events are not updated/deleted through normal application flows;
- sensitive values are absent from audit, logs and serialized output.

### Database and application validation

Run as applicable:

- migrations up;
- migrations down/rollback safety required by project policy;
- backend automated tests;
- frontend tests/typecheck/lint/build relevant to touched code;
- repository governance/security checks required by the project.

Never report a validation step as executed unless it actually ran.

## Documentation deliverables

Update durable Markdown to match implemented behavior.

At minimum verify/update as applicable:

- auth/session architecture docs;
- API/current-user contract docs;
- security/authorization conventions;
- operator CLI usage;
- invitation lifecycle;
- audit-event semantics;
- project state and NEXT transition;
- relevant ADRs for material architecture decisions.

Do not document behavior that is not implemented.

Do not leave material architecture decisions only in this task or chat history when documentation policy requires ADR coverage.

## Acceptance criteria

M1.1 is PASS only when every applicable criterion is satisfied:

- [ ] execution authority and repository preflight were respected;
- [ ] first-admin bootstrap works safely;
- [ ] repeat bootstrap with an existing admin fails closed without side effects;
- [ ] operator CLI creates and revokes one-time targeted and generic invitations;
- [ ] invitation token is cryptographically secure;
- [ ] invitation token is stored only in non-plaintext verification-safe form;
- [ ] invitation token is shown only once and cannot be retrieved later;
- [ ] token does not leak through normal URL query, log, audit or serialization paths;
- [ ] invited registration works atomically;
- [ ] duplicate email cannot consume an invitation;
- [ ] concurrent use of one invitation cannot create two accounts;
- [ ] registration establishes a regenerated session only after commit;
- [ ] login/logout/current-user flow works;
- [ ] same-origin Sanctum session + CSRF is the implemented first-party auth model;
- [ ] email normalization/comparison is centralized and documented;
- [ ] public user identity is stable and non-sequential;
- [ ] roles/status cannot be client-escalated;
- [ ] DISABLED is enforced on every authenticated request;
- [ ] operator disable/enable path works;
- [ ] disabling invalidates active sessions;
- [ ] at least one ACTIVE admin is always preserved;
- [ ] reusable ownership convention exists and negative tests pass;
- [ ] foreign private-resource access returns `404`;
- [ ] known capability denial uses `403` where applicable;
- [ ] admin has no private-data ownership bypass;
- [ ] AuditEvent is append-only at application level;
- [ ] AuditEvent supports USER/OPERATOR/SYSTEM actor semantics;
- [ ] sensitive values are absent from logs, audit and serialized output;
- [ ] required migrations/tests/build/static checks/repository validation pass;
- [ ] docs/API/security/ADR state match the implementation;
- [ ] deferred admin, secret-management, AI and Career scope did not leak into M1.1.

## State update

Only after every applicable acceptance criterion and required validation passes:

```text
.agents/state/NEXT.md = m1-2-career-core
```

Perform this transition only if repository workflow permits the acting agent to update state as part of task completion.

If validation is incomplete or failing, do not advance `NEXT.md`. Keep M1.1 authorized and record the real blocker or remaining validation.

## STOP conditions

Stop and report the blocker instead of improvising if any of the following is true:

- M1.1 lacks execution authority and no explicit current-user override exists;
- implementation would require changing an accepted architecture decision without ADR/documentation reconciliation;
- safe session invalidation or per-request DISABLED enforcement cannot be achieved with the accepted architecture;
- invitation-token handling would knowingly expose plaintext secrets in logs, audit, history or referrers without an accepted mitigation;
- atomic one-time invitation consumption cannot be guaranteed;
- authorization cannot prevent cross-user private-resource access;
- the only way to test ownership would be to invent production Career/Vacancy/Application objects;
- a requested adjacent feature would materially expand M1.1 beyond this task;
- a framework-specific implementation detail materially affects architecture but current official evidence has not been checked.

Do not begin M1.2 Career implementation from this task.
