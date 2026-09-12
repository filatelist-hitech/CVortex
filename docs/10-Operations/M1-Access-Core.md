---
title: M1.1 Access Core Operations
status: implemented
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [access, auth, invitations, security]
related: ["[[../03-ADR/ADR-0007-multi-user-ownership|ADR-0007]]", "[[../03-ADR/ADR-0008-invite-only-access|ADR-0008]]"]
---

# M1.1 Access Core Operations

## First-party access model

`Next.js → same-origin Nginx → Laravel → Sanctum stateful session` is the only M1.1 web authentication path. No JWT, bearer token, OAuth or browser-persistent auth material is used. The frontend requests `/sanctum/csrf-cookie`, then sends the decoded `XSRF-TOKEN` as `X-XSRF-TOKEN` for state-changing calls.

`SANCTUM_STATEFUL_DOMAINS` includes `__SANCTUM_CURRENT_REQUEST_HOST__`; this preserves the same-origin model for a configured local port and for a future deployment host without turning arbitrary cross-origin requests into stateful ones.

Users have ULID public IDs, a lowercase-trimmed email identity, an Argon/bcrypt framework-managed password hash, `admin|user` role and `ACTIVE|DISABLED` status. Email comparison is exactly `trim` plus Unicode lowercase; validation remains Laravel `email:rfc`, so no speculative provider/DNS assertion is made.

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

Invitation expiry is 7 days by default, constrained to 1–30 days. Tokens are 32 random bytes represented as hex, HMAC-SHA-256 protected at rest, printed only once and expected in `/register#token=<value>`. The frontend removes the fragment from history before it sends the registration body.

## Data and audit

Invitation consumption locks its row in one transaction, verifies status/target email/unique email, creates the user, consumes the one allowed use and appends audit events. Audit events are application append-only and contain actor type (`USER`, `OPERATOR`, `SYSTEM`), optional user actor, subject and safe metadata. Passwords, invitation tokens, session IDs and CSRF/auth tokens are excluded.

This implementation configuration follows current Laravel 13 Sanctum SPA documentation: stateful API middleware, CSRF cookie bootstrap and cookie sessions. PostgreSQL row locking (`SELECT … FOR UPDATE`) serializes one-time consumption. Next.js App Router supports `history.replaceState` for fragment removal. A clean Docker runtime validation exercises CSRF registration, authenticated `/me`, disabled-session invalidation and two concurrent registrations with a one-time invitation.

## M1.1 readiness record

Current validation is PASS for PHPUnit (22 tests / 88 assertions), Pint, Larastan, frontend lint, typecheck, Vitest, production build, clean `origin/stage` production build, same-origin auth runtime, migration up, full rollback/down safety, repeated migration up, agent-contract governance checks and `git diff --check`.

The previously observed frontend `/_global-error` `useContext(null)` build failure is **NOT REPRODUCED / TRANSIENT**: the required production build passes on both the feature branch and clean `origin/stage`. No root cause is asserted. Compose missing-variable warnings reproduce on the baseline and remain pre-existing environment noise.

The frontend package-manager metadata mismatch and absent `pnpm-lock.yaml` are tracked separately as pre-existing repository debt and are outside M1.1 scope.
