---
title: ADR-0001 — Laravel as the Core Application Backend
status: accepted
decision_nature: OWNER_CONSTRAINT
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, backend, laravel]
related: [ADR-0003, ADR-0004, ADR-0006]
---

# ADR-0001 — Laravel as the Core Application Backend

## Context

CVortex needs one authoritative application backend for identity, authorization, workflows, persistence coordination and the shared API. The owner-approved direction is PHP/Laravel. Phase 03 found Laravel a strong greenfield fit and found no measured workload that requires moving the core to Go.

## Decision Drivers

- preserve the approved stack and API-first boundary;
- maximize delivery speed, testability and operational simplicity;
- keep future specialized processing possible without premature microservices.

## Options

1. Laravel modular application as API and control plane.
2. Rewrite the core in Go before implementation.
3. Split the core into Laravel and Go services immediately.

## Comparison

| Option | Product fit | Simplicity | Operability | Migration cost | Premature complexity |
|---|---|---|---|---|---|
| Laravel core | High | High | High for the initial team | Low now | Low |
| Go core | Unproven | Medium | Medium | High now | High |
| Early split | Unproven | Low | Low | High | Very high |

## Decision

Laravel is the core application backend and authoritative API/control plane. It owns domain orchestration, authorization, durable transaction boundaries and dispatch of background work. The core is not moved to Go without measured evidence.

Future workers or services may use another runtime only for a demonstrated capability or performance boundary. They must consume explicit contracts, remain subordinate to backend authorization and provenance rules, and must not create a second source of business truth.

## Consequences

### Positive

- one coherent security and transaction boundary;
- fast local development and strong Laravel-native queue/testing support;
- specialized services remain possible later.

### Negative / Risks

- Laravel can become a large application unless module boundaries stay explicit;
- compute-heavy work may eventually require isolated workers;
- a later extraction has contract and migration cost.

## Security and Validation Impact

Server-side authorization, input validation, secret redaction and audit correlation live at or behind this boundary. Phase 06 must define module boundaries and security contracts; it must not implement business rules only in clients.

## Reversibility and Revisit Triggers

Reversible through explicit service extraction. Revisit only with profiling, isolation, dependency or reliability evidence that Laravel cannot meet a bounded workload. A change to the core runtime requires a superseding ADR.

## References

- [PROJECT.md](../../PROJECT.md) — approved backend and no-premature-complexity constraints.
- [Backend Runtime and Data Stack](../../research/technical/01-BACKEND-RUNTIME-DATA.md).
- [OpenAI API / Client Options for PHP](../../research/technical/05-OPENAI-PHP-INTEGRATION.md).

## Supersedes

None.

## Superseded By

None.
