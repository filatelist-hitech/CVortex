---
title: ADR-0019 — Stateful Sanctum Authentication for First-party Web
status: accepted
decision_nature: DERIVED_ARCHITECTURAL_DECISION
owner: project
created: 2026-09-13
updated: 2026-09-13
tags: [architecture, security, access, authentication, sanctum]
related: [ADR-0001, ADR-0004, ADR-0008]
---

# ADR-0019 — Stateful Sanctum Authentication for First-party Web

## Context

CVortex has a browser/PWA-first client and a shared API. M1.1 uses a same-origin deployment model for the MVP. First-party authentication requires CSRF protection, session invalidation on logout and operator disable, and enforcement of disabled-account state on authenticated requests. Bearer/JWT authentication is not required for this first-party web path.

## Decision

M1.1 uses Laravel Sanctum stateful cookie/session authentication for the first-party web/PWA client:

- Laravel session authentication uses the accepted server-side session store;
- CSRF protection follows the Sanctum/Laravel first-party flow;
- successful authentication regenerates the session ID;
- logout invalidates the current session;
- operator disable invalidates active sessions;
- authenticated requests enforce ACTIVE account state;
- bearer/API-token authentication is not the first-party browser mechanism.

## Consequences

Frontend and API deployment must remain within the accepted same-site/origin assumptions. Stateful-domain and cookie settings are deployment-sensitive, and CSRF protection is mandatory. Future native, mobile or third-party API authentication requires a separate decision; this ADR does not approve Sanctum personal access tokens for those uses.

## Alternatives considered

- JWT/bearer authentication for the first-party browser;
- a custom session/token implementation;
- Sanctum personal access tokens for browser authentication.

## References

- [Laravel Sanctum documentation](https://laravel.com/docs/13.x/sanctum)
- [Laravel authentication documentation](https://laravel.com/docs/13.x/authentication)
- [M1.1 Access Core Operations](../10-Operations/M1-Access-Core.md)
