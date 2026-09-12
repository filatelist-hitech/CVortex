---
title: ADR-0008 — Invite-only Registration Boundary
status: accepted
decision_nature: OWNER_CONSTRAINT
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, security, access, invitations]
related: [ADR-0004, ADR-0007]
---

# ADR-0008 — Invite-only Registration Boundary

## Context

Registration is owner-approved as invite-only. This reduces uncontrolled enrollment but does not replace authentication, authorization or resource ownership.

## Decision Drivers

- controlled onboarding for an early PII-heavy product;
- clear security responsibilities;
- support admin/user roles without coupling invitation state to ongoing access.

## Options

1. Invite-only registration with separate invitation, authentication, authorization and role boundaries.
2. Public self-registration.
3. Manually provision every account without an invitation lifecycle.

## Comparison

| Option | Product fit | Abuse surface | Operability | Future flexibility |
|---|---|---|---|---|
| Invite-only | High | Lower | High | High |
| Public | Low now | Higher | High | High |
| Manual provisioning | Medium | Low | Low | Low |

## Decision

Registration requires a valid invitation. Responsibilities remain distinct:

- **Invitation** authorizes a bounded account-creation opportunity.
- **Authentication** proves the current identity.
- **Authorization** decides whether that identity may perform an action on a resource.
- **Role** supplies coarse capabilities (`admin`, `user`) and never substitutes for ownership checks.

Invitation token lifecycle, auth package and endpoint contracts are deferred to Phase 06. Invitations must be revocable/expiring and single-use or otherwise bounded; successful registration does not confer access to another user's data.

## Consequences

### Positive

- controlled user growth and reduced enrollment abuse;
- clean boundary for later public-registration reconsideration;
- invitations can be audited independently of sessions.

### Negative / Risks

- invitation delivery and recovery add workflow complexity;
- leaked or replayed tokens are an account-creation risk;
- admin invitation authority needs abuse controls.

## Security and Validation Impact

Future design must use high-entropy tokens, limited disclosure, expiry, replay protection and transactional consumption. Negative tests must cover invalid, expired, reused and wrong-recipient cases.

## Reversibility and Revisit Triggers

Public registration may be proposed when product readiness and abuse controls justify it. That is a product/security change requiring a superseding ADR.

## References

- [PROJECT.md](../../PROJECT.md).
- [Product Scope](../01-Product/Scope.md).
- [Laravel Authentication, Authorization and Security](../../research/technical/02-LARAVEL-AUTH-SECURITY.md).

## Supersedes

None.

## Superseded By

None.
