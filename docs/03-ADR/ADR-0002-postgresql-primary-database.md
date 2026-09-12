---
title: ADR-0002 — PostgreSQL as the Primary Durable Database
status: accepted
decision_nature: OWNER_CONSTRAINT
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, data, postgresql]
related: [ADR-0007, ADR-0009, ADR-0015]
---

# ADR-0002 — PostgreSQL as the Primary Durable Database

## Context

CVortex must preserve relational ownership, provenance, history and structured application state. Some external/provider payloads are semi-structured, but that does not remove the need for relational integrity. PostgreSQL is an owner-approved direction.

## Decision Drivers

- durable, transactional source of truth;
- relational integrity for ownership and provenance;
- structured data plus selectively retained JSON;
- operational portability from local Docker Compose to hosted infrastructure.

## Options

1. PostgreSQL as the primary database.
2. Document database as the primary store.
3. Multiple primary stores, including a standalone vector database, from day one.

## Comparison

| Option | Integrity / traceability | Multi-user fit | Simplicity | Extensibility |
|---|---|---|---|---|
| PostgreSQL | High | High | High | High |
| Document primary | Medium | Medium | Medium | High for loose payloads |
| Polyglot primary stores | Fragmented | Medium | Low | High but premature |

## Decision

PostgreSQL is the primary durable source of truth. Relational structures enforce identity, ownership, lifecycle and provenance; JSON is allowed for genuinely variable or source-specific data, not as an escape from modelling stable invariants. Audit/history requirements are represented explicitly during Phase 06 without committing to event sourcing.

Semantic search may later use PostgreSQL-supported capabilities or another component after measured need and evaluation. No standalone vector database is accepted now.

## Consequences

### Positive

- transactions can preserve fact/claim/content and ownership invariants;
- one backup, recovery and observability center initially;
- local and hosted PostgreSQL remain operationally familiar.

### Negative / Risks

- JSON boundaries require discipline and schema validation;
- history and provenance queries need deliberate design;
- future semantic workloads may require indexes or an explicit new decision.

## Security and Validation Impact

Every private aggregate must be ownership-scoped; database constraints complement but do not replace server authorization. Backups contain PII and must inherit access, encryption, retention and recovery controls.

## Reversibility and Revisit Triggers

Adding a specialized read/search store is reversible if PostgreSQL remains authoritative. Revisit after measured search quality/scale limitations, not speculative scale diagrams.

## References

- [PROJECT.md](../../PROJECT.md).
- [Backend Runtime and Data Stack](../../research/technical/01-BACKEND-RUNTIME-DATA.md).
- [External Content Threats](../../research/security/external-content-threats.md).

## Supersedes

None.

## Superseded By

None.
