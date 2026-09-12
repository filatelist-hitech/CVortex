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

Implement this task only when `.agents/state/NEXT.md` is `m1-1-access-core`, or when the current user gives a bounded override allowed by `.agents/policies/git-workflow.md`. Otherwise stop before product changes. Editing this document does not authorize implementation.

Before implementation, read `PROJECT.md`, current state, this task, relevant scoped `AGENTS.md`, Git/documentation policies and accepted auth/security ADRs. Run the repository write preflight when available and work on a short-lived branch based on current `stage`. Never write product changes directly to `main` or `stage`.

Accepted project decisions outrank external guidance. If current official framework/security research conflicts with one, identify the conflict and update or propose the relevant ADR before changing architecture.

## Scope

`legacy/phase-09-hardened-pr17.md` is requirement evidence, not active scope. M1.1 includes only the access foundation First Value needs.

Deferred unless a concrete M1.1 security blocker proves otherwise: broad admin UX, role management, persisted secret/BYOK/provider-credential management, email verification/recovery, device/session UI, user deletion, speculative soft deletes, Career/Vacancy/Application data and runtime AI. Do not pull legacy features back in merely because they were designed earlier.

## Authentication model

The first-party path is fixed:

```text
Next.js → same-origin Nginx → Laravel → Sanctum SPA/session auth
```

Use secure session cookies and CSRF protection for state-changing authenticated requests. Regenerate the session ID after successful login and registration. Do not store auth material in browser persistent storage or introduce JWT/PAT/bearer, OAuth or future-client flows in this slice.

Middleware order, cookie names and exact Sanctum configuration are implementation details. Verify them against current official Laravel/Sanctum documentation. If this model lacks accepted ADR coverage, add or update the ADR during implementation.

## Domain vocabulary

**Operator** is a human with trusted deployment/CLI access. It is not an application role, fake superuser record or private-data bypass. Operator actions are explicit CLI operations and are audited where required below.

**User** is an application identity with a stable ID, normalized email, password hash, `admin|user` role, `ACTIVE|DISABLED` status and timestamps. Email is the only login identity in M1.1. `admin` is an account/security capability marker, not private-data god mode. Use a non-sequential public stable ID; choose UUID or ULID after checking current Laravel conventions and accepted project decisions.

**Invitation** authorizes exactly one registration. Targeted invitations have `target_email != null`; generic invitations have `target_email == null`; both have `max_uses=1`. Multi-use invitations are deferred. Default expiry is 7 days and maximum expiry is 30 days. Derive status from primary data rather than storing a second mutable status. Precedence is `REVOKED → EXPIRED → EXHAUSTED → ACTIVE`.

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

Verify the exact rule against current Laravel/PostgreSQL behavior and accepted project conventions, then document it. Do not guess RFC edge cases. If an email already belongs to a User, registration fails without consuming the invitation; invitations do not merge or recover accounts.

## Operator flows

### First admin

Provide a dedicated CLI command that creates the first `ACTIVE admin`. It has no default credentials, committed seed credential, public bootstrap endpoint or “first registrant becomes admin” shortcut. Sensitive input must not appear in logs. Successful bootstrap appends an OPERATOR audit event.

If an admin already exists, the command fails safely and changes nothing. It does not create another admin, reset an existing account or promote an arbitrary user. General role mutation is outside M1.1.

### Invitations

Creation and revocation are CLI operations. No admin invitation API/UI is needed.

Generate tokens cryptographically, persist only a verification-safe form and reveal plaintext once at creation. Existing tokens cannot be retrieved later in plaintext and must be excluded from logs, audit metadata, serialized responses and exceptions.

A targeted invitation requires normalized registration email to match normalized `target_email`. A generic invitation accepts any valid unregistered email. Rejected registration does not consume the invitation. Revocation updates primary state such as `revoked_at` and appends the appropriate audit event.

### Disable/enable

Provide operator CLI commands equivalent to `user:disable` and `user:enable`. Disabling invalidates active sessions. Status is also enforced on every authenticated request so a stale session cannot preserve access.

Never allow an operation to leave zero ACTIVE admins. Disabling the sole ACTIVE admin fails closed with no partial change.

## Invitation URL

Treat the invitation token as browser-sensitive data. Preferred transport is `/register#token=<value>`. The frontend reads the fragment locally, moves it to transient registration state, immediately removes it from the visible URL/history with `history.replaceState` or equivalent, then sends it only in the registration request body.

Do not use a normal query parameter by default because it can leak through access logs, analytics, history or referrers. If the selected Next.js architecture makes fragment handling unsafe or impractical, stop and document the alternative and its mitigations before implementation.

## Registration

The web form accepts invitation token, email, password and password confirmation. It does not accept role, user ID, owner ID, status or scope.

The database portion is one transaction:

```text
lock/validate invitation
→ validate target email when applicable
→ verify email is unregistered
→ create ACTIVE role=user
→ consume invitation
→ append required AuditEvent(s)
→ commit
```

Only after commit, establish the authenticated session, regenerate its ID and continue to the authenticated shell.

Two concurrent attempts using one invitation must produce exactly one successful registration. The losing request fails safely, creates no partial User and cannot over-consume the invitation. Use database constraints, locking and transaction semantics rather than timing assumptions.

## Login, logout and account state

Login accepts email/password, uses canonical email normalization and framework-supported verification, applies configurable abuse rate limiting and regenerates the session ID on success. Unknown email and wrong password use the same generic credential error. Once valid credentials identify an existing account, the app may report that it is disabled.

Multiple sessions per user are allowed. Normal logout invalidates only the current session. Keep the architecture capable of future invalidate-all behavior without redesigning auth.

Successful login/logout belong in structured security/application logs as appropriate, not permanent AuditEvent rows by default. Choose rate-limit thresholds from current official Laravel/security guidance rather than arbitrary architecture constants.

Password policy remains 15–128 characters, spaces/passphrases allowed, no mandatory character-class composition and no periodic rotation. Use current framework-supported hashing after compatibility/security verification. Plaintext credentials are never persisted, logged, audited or reversibly encrypted. Self-service recovery and email verification are deferred.

## Authorization foundation

Create one reusable server-side ownership/policy convention for future private resources and prove it with the isolated fixture. Tests must show owner access; foreign-user `404` for read/mutate/delete/enumerate; no admin ownership bypass; `403` for known capability denial where applicable; no self-role escalation; and no authorization based on client `user_id`/`owner_id`.

Authenticated identity comes from the server-side session/principal. Resource ownership comes from a trusted server-side relationship.

## Audit

Append events for first-admin bootstrap, invitation create/revoke/consume, registration, disable/enable and any other security mutation explicitly added to this slice. Actor semantics must distinguish USER, OPERATOR and SYSTEM.

Add explicit redaction/serialization tests for credentials, invitation tokens, session/CSRF/auth values, API keys and future provider credentials. Do not rely on developer memory as a security control.

## HTTP/UI surface

Build only login, invite registration, authenticated shell/current-user state and logout. `/app` may show current email, role, status and logout. Do not add fake Career dashboards or placeholder business navigation.

Expose one minimal current-user contract such as `/me` following accepted API conventions. Return only safe identity data needed by the shell: stable ID, email, role and status.

Follow the accepted Phase 07 design/accessibility foundation for keyboard, focus, loading, validation, error and permission states. No admin invitation UI/API is required.

## Deterministic boundary and research

M1.1 needs no LLM. Use framework auth primitives, database constraints/transactions, validators, policies/middleware and deterministic tests.

Before fixing framework-specific details, verify official primary sources for the selected versions of Laravel auth/sessions, Sanctum SPA auth/CSRF, hashing, rate limiting, session invalidation/storage, PostgreSQL locking/constraints for one-time consumption and Next.js fragment handling. Record architecture-affecting conclusions in durable docs/ADR. Do not pin unrelated dependencies, LLM models or prices.

## Validation

Automated coverage must prove failure paths as well as happy paths.

- Bootstrap: first creation, safe repeat failure, no default credential path, no sensitive output.
- Invitations: targeted/generic success, normalized email match/mismatch, invalid/expired/revoked/exhausted cases, one-time reveal, non-plaintext persistence, no retrieval/leak, duplicate-email no-consume behavior, and exactly one winner under concurrent use.
- Registration: invitation required, `ACTIVE role=user`, no client-selected role/status, rollback on failure, session only after commit, regenerated session ID.
- Login/session: normalized login, no identity enumeration for unknown/wrong credentials, disabled login blocked, disabled existing session blocked, current-session logout, multiple sessions, CSRF behavior and deterministic rate-limit tests.
- Disable/enable: session invalidation, re-enable, sole-active-admin protection and no partial change on rejected disable.
- Authorization: owner success, foreign `404`, foreign mutation/delete/enumeration blocked, no admin bypass, applicable `403`, no mass-assignment/self-escalation and no client-controlled ownership.
- Audit: expected mutations append events with correct actors; normal flows cannot update/delete events; sensitive values are absent from audit/log/serialization.

Run migration up/down checks required by project policy, relevant backend tests, frontend tests/typecheck/lint/build for touched code and repository governance/security checks. Never report a validation step as executed unless it actually ran.

## Documentation

Update durable Markdown for auth/session architecture, current-user API, authorization conventions, operator CLI, invitation lifecycle, audit semantics and project state. Add/update ADRs for material architecture decisions. Document implemented behavior only.

## Completion criteria

M1.1 is PASS only when:

- [ ] execution authority and repository preflight were respected;
- [ ] first-admin bootstrap and safe repeat behavior work;
- [ ] operator CLI creates/revokes one-time targeted and generic invitations;
- [ ] invitation tokens are secure, stored non-plaintext, shown once and excluded from normal leak paths;
- [ ] registration is atomic, duplicate email cannot consume an invitation, and concurrent token use cannot create two accounts;
- [ ] registration establishes a regenerated session only after commit;
- [ ] login/logout/current-user work through same-origin Sanctum session auth + CSRF;
- [ ] email normalization/comparison is centralized and documented;
- [ ] public user identity is stable and non-sequential;
- [ ] roles/status cannot be client-escalated and DISABLED is enforced on every authenticated request;
- [ ] disable/enable invalidates sessions and preserves at least one ACTIVE admin;
- [ ] ownership convention and negative tests pass with no admin bypass;
- [ ] AuditEvent is append-only with USER/OPERATOR/SYSTEM actor semantics;
- [ ] sensitive values are absent from logs, audit and serialized output;
- [ ] required tests, migrations, app checks and repository validation pass;
- [ ] docs/API/security/ADR state match implementation;
- [ ] deferred admin, secret-management, AI and Career scope did not leak into M1.1.

## State update

After every applicable criterion and required validation passes, set `.agents/state/NEXT.md` to `m1-2-career-core` if repository workflow permits it. If validation is incomplete or failing, keep M1.1 authorized and record the real blocker.

## STOP

Stop and report the blocker instead of improvising if M1.1 lacks authority; an accepted architecture decision must change without ADR reconciliation; the session design cannot enforce DISABLED safely; invitation handling would knowingly leak sensitive values without accepted mitigation; one-time consumption cannot be atomic; cross-user isolation cannot be enforced; tests would require inventing Career/Vacancy/Application objects; or an adjacent feature would materially expand this slice.

Do not begin M1.2 from this task.
