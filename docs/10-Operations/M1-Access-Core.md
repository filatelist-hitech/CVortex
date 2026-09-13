---
title: M1.1 Access Core Operations
status: implemented
owner: project
created: 2026-09-12
updated: 2026-09-13
tags: [access, auth, invitations, security]
related: ["[[../03-ADR/ADR-0007-multi-user-ownership|ADR-0007]]", "[[../03-ADR/ADR-0008-invite-only-access|ADR-0008]]"]
canonical_auth_decision: "[[../03-ADR/ADR-0019-sanctum-stateful-first-party-auth|ADR-0019]]"
---

# M1.1 Access Core Operations

## First-party access model

`Next.js → same-origin Nginx → Laravel → Sanctum stateful session` is the only M1.1 web authentication path. No JWT, bearer token, OAuth or browser-persistent auth material is used. The frontend requests `/sanctum/csrf-cookie`, then sends the decoded `XSRF-TOKEN` as `X-XSRF-TOKEN` for state-changing calls.

`SANCTUM_STATEFUL_DOMAINS` includes `__SANCTUM_CURRENT_REQUEST_HOST__`; this preserves the same-origin model for a configured local port and for a future deployment host without turning arbitrary cross-origin requests into stateful ones.

Users have ULID public IDs, a lowercase-trimmed email identity, an Argon/bcrypt framework-managed password hash, `admin|user` role and `ACTIVE|DISABLED` status. Email validity uses the shared Laravel `email:rfc` boundary, while comparison remains exactly `trim` plus Unicode lowercase; no speculative provider/DNS assertion is made.

An authenticated request checks `ACTIVE`; disabling also deletes that user's database-backed sessions. `admin` never bypasses private-resource ownership. Future private policies must compare the session-derived user ID with the trusted server-side owner relationship and use `404` for foreign resources.

## Operator commands

Run commands inside the backend container. Password entry for bootstrap is hidden and is never echoed or persisted in audit/log metadata.

```bash
docker compose exec backend php artisan user:bootstrap-admin admin@example.test
```

The command fails without side effects if any admin exists.

```bash
docker compose exec backend php artisan invitation:create --email=person@example.test --expires=7
docker compose exec backend php artisan invitation:create --expires=7
docker compose exec backend php artisan invitation:revoke <invitation-ulid>
docker compose exec backend php artisan user:disable <user-ulid>
docker compose exec backend php artisan user:enable <user-ulid>
```

`invitation:create` prints the invitation ULID for a later `invitation:revoke` call and prints the one-time registration URL on a separate line. Invitation expiry is 7 days by default, constrained to 1–30 days. Tokens are 32 random bytes represented as hex, HMAC-SHA-256 protected at rest, printed only once and expected in `/register#token=<value>`. The `/register` Client Component reads the fragment into transient in-memory state, immediately removes it with `history.replaceState`, and submits it only in the registration body; it never copies the token to a query string, persistent browser storage or a referrer-bearing navigation.

## Data and audit

The shared email identity boundary validates input with Laravel 13 RFC email validation, then applies the existing trim-plus-Unicode-lowercase canonical comparison. CLI and HTTP paths use the same service rules.

Invitation consumption locks its row in one transaction, verifies status/target email/unique email, creates the user, consumes the one allowed use and appends audit events. Audit events are application append-only: `AuditEventQueryBuilder` permits inserts used by `AuditLogger`, but rejects query-level `update()` and `delete()`; model instance mutation is rejected as well. This is not database-level immutability: raw `DB` access can still mutate the table and is outside the M1.1 supported application boundary. Audit events contain actor type (`USER`, `OPERATOR`, `SYSTEM`), optional user actor, subject and key-based recursively redacted metadata. Passwords, invitation tokens, session IDs and CSRF/auth tokens are excluded when they occur in protected keys; arbitrary values under benign keys are not content-inspected.

The first-admin command acquires a fixed PostgreSQL transaction-level advisory lock, then re-checks for an existing admin inside the transaction before inserting. This serializes only the bootstrap invariant, releases automatically at transaction end, and avoids distributed-lock infrastructure. SQLite test runs skip the PostgreSQL-specific lock because their single-process test database has no equivalent; the real Docker PostgreSQL concurrency harness covers the database boundary. The same harness also runs the distinct two-independent-invitations/one-normalized-email case: one registration succeeds, one loses at the database `UNIQUE` boundary, one User and one invitation consumption are committed, and the losing invitation remains unused with no losing audit state.

Login abuse limiting is centralized in the named Laravel `login` limiter. Project defaults are 5 attempts per 60 seconds, configurable through `AUTH_LOGIN_RATE_LIMIT_ATTEMPTS` and `AUTH_LOGIN_RATE_LIMIT_DECAY_SECONDS`; these are CVortex operating defaults, not values mandated by Laravel. The limiter key is the normalized email plus source IP, so distinct identities do not share a bucket. Laravel returns `429` when the configured limit is exceeded.

This implementation configuration follows accepted ADR-0019 and current Laravel 13 Sanctum SPA documentation: stateful API middleware, CSRF cookie bootstrap and cookie sessions. PostgreSQL row locking serializes one-time invitation consumption, while the fixed transaction-level advisory lock serializes admin status transitions. Invitation creation commits its business row and required audit event atomically. The executable validation is split between focused regressions and `scripts/test-access-core-postgres-concurrency.sh`, which creates and removes an isolated temporary PostgreSQL database for simultaneous bootstrap, invitation-consumption and admin-disable attempts. Validation counts are intentionally not hard-coded here because they change with the test matrix.

## M1.1 readiness record

Final local validation for PR #23 review fixes: AccessCore focused regression — 28 tests / 144 assertions; complete backend PHPUnit suite — 33 tests / 157 assertions; Vitest — 2 tests / 2 assertions. Pint — 45 files; Larastan — no errors. PostgreSQL concurrency — PASS for bootstrap, single-invitation registration, independent-invitation same-normalized-email registration and concurrent admin disable. Same-origin auth — PASS (204/201/204/200/204/401); production frontend build — PASS; migrations up/down/re-up — PASS. Frontend lint and typecheck also pass. The ordinary compose frontend build remains the known `NODE_ENV=development`/`/_global-error` environment failure documented below.

The compose development container sets a non-standard `NODE_ENV=development`, which can reproduce the historical frontend `/_global-error` `useContext(null)` failure during `next build`. The required production command passes with `NODE_ENV=production`; no product-code root cause is asserted for the development-environment failure. Compose missing-variable warnings reproduce on the baseline and remain pre-existing environment noise.

The frontend package-manager metadata mismatch and absent `pnpm-lock.yaml` are tracked separately as pre-existing repository debt and are outside M1.1 scope.
