---
title: ADR-0007 — Shared-schema Multi-user Ownership and Isolation
status: accepted
decision_nature: OWNER_CONSTRAINT
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, security, authorization, multi-user]
related: [ADR-0002, ADR-0004, ADR-0008, ADR-0015]
---

# ADR-0007 — Shared-schema Multi-user Ownership and Isolation

## Context

CVortex is multi-user from its first production-capable implementation. Career facts, applications, employer memory, credentials, files and generated artifacts contain private PII. Early low user count is not permission to bake in single-user assumptions.

## Decision Drivers

- prevent IDOR and cross-user access;
- keep local-first operation simple;
- support `admin` and `user` roles without confusing roles with ownership;
- make isolation testable.

## Options

1. Shared PostgreSQL schema with explicit resource ownership and server authorization.
2. Database/schema per user.
3. Single-user data model with later retrofit.

## Comparison

| Option | Isolation | Simplicity | Operability | Migration cost |
|---|---|---|---|---|
| Shared schema + ownership | High with controls | High | High | Low now |
| Per-user DB/schema | Strong physical boundary | Low | Low | High |
| Retrofit later | Low initially | High initially | Medium | Very high later |

## Decision

Use a shared relational database/schema with explicit ownership for every private resource or aggregate. Acting identity comes only from authenticated server context; client-provided `user_id` is never authorization evidence. Queries, commands, jobs, caches, files and LLM contexts must retain tenant/owner scope.

MVP roles are `admin` and `user`. Role capability does not silently grant access to arbitrary private data; any administrative access must be explicit, authorized and auditable. Detailed schema and policy mechanics are Phase 06 work.

## Consequences

### Positive

- production-capable isolation without per-tenant infrastructure;
- straightforward transactions, backups and migrations;
- systematic negative authorization testing.

### Negative / Risks

- one missing scope can cause severe leakage;
- shared caches/queues/storage need consistent ownership keys;
- admin behavior requires precise later design.

## Security and Validation Impact

Phase 06 must define authorization matrices and ownership propagation. Later tests must attempt cross-user reads, writes, enumeration, queued processing, file access and context assembly. Logging and error messages must not leak object existence or PII.

## Reversibility and Revisit Triggers

Physical tenant separation may be added for regulatory or enterprise requirements via a superseding ADR. It is not justified now.

## References

- [PROJECT.md](../../PROJECT.md).
- [Laravel Authentication, Authorization and Security](../../research/technical/02-LARAVEL-AUTH-SECURITY.md).
- [External Content Threats](../../research/security/external-content-threats.md).

## Supersedes

None.

## Superseded By

None.
