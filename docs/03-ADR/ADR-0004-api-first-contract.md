---
title: ADR-0004 — Shared API-first Application Boundary
status: accepted
decision_nature: OWNER_CONSTRAINT
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, api, clients]
related: [ADR-0001, ADR-0005, ADR-0007]
---

# ADR-0004 — Shared API-first Application Boundary

## Context

CVortex begins with a web/PWA client and anticipates future mobile and browser-extension clients. Product rules must stay consistent across those surfaces and cannot live exclusively in a frontend.

## Decision Drivers

- one authoritative business/security boundary;
- client portability and testability;
- local-to-hosted deployment portability;
- prevention of divergent client-side rules.

## Options

1. Shared backend API contract for all clients.
2. Frontend-owned business logic with direct data access.
3. Independent backend-for-frontend implementations per client from the start.

## Comparison

| Option | Consistency | Security | Client extensibility | Complexity |
|---|---|---|---|---|
| Shared API | High | High | High | Moderate |
| Frontend-owned | Low | Low | Low | Low initially |
| Multiple BFFs | Medium | Medium | Medium | High |

## Decision

All product clients consume a shared application API contract:

```text
Frontend / PWA / Browser Extension / Future Clients
                         ↓
                   API contract
                         ↓
                 Laravel backend
```

Business invariants, authorization, truth validation and durable workflow transitions execute on the backend. Clients may own presentation, local interaction state and safe optimistic UX, but never become the sole implementation of a business rule.

The exact protocol shape, versioning policy and OpenAPI specification are deferred to Phase 06.

## Consequences

### Positive

- uniform behavior and authorization across clients;
- contract-focused integration testing;
- future clients do not bypass the product core.

### Negative / Risks

- API evolution requires compatibility discipline;
- poor contract design can create chatty clients;
- offline UX needs explicit synchronization design later.

## Security and Validation Impact

The API derives acting identity from authenticated server context, validates all input and never trusts client-supplied ownership. External content remains data. Contract tests must cover denial and cross-user paths.

## Reversibility and Revisit Triggers

Individual BFF/read-model layers may be added if measured client needs justify them, while the authoritative backend boundary remains. Replacing the shared boundary requires a superseding ADR.

## References

- [PROJECT.md](../../PROJECT.md).
- [Product Principles](../01-Product/Principles.md).
- [External Content Threats](../../research/security/external-content-threats.md).

## Supersedes

None.

## Superseded By

None.
