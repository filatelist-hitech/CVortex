---
title: Architecture Decision Index
status: accepted
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, adr, index, phase-05]
related:
  - "[[../02-Architecture/Architecture-Baseline|Architecture Baseline]]"
---

# Architecture Decision Index

This index is the canonical inventory of CVortex architecture decisions. An `accepted` ADR is authoritative within its scope. Changes to an accepted decision require an explicit amending or superseding ADR; implementation details not fixed by an ADR remain changeable.

## Status vocabulary

- `proposed` — under review; not authoritative.
- `accepted` — current authoritative decision.
- `superseded` — replaced by a newer ADR; retained as history.
- `deprecated` — no longer recommended, with no direct replacement.

## Decision nature

- `OWNER_CONSTRAINT` — explicitly approved product/technology boundary.
- `RESEARCH_BACKED_DECISION` — selected after evaluating researched alternatives.
- `DERIVED_ARCHITECTURAL_DECISION` — simplest boundary derived from accepted constraints and system invariants.

## Decisions

| ID | Title | Status | Nature | Date | Area | Supersedes | Superseded by |
|---|---|---|---|---|---|---|---|
| [ADR-0001](ADR-0001-laravel-core-backend.md) | Laravel as the Core Application Backend | accepted | OWNER_CONSTRAINT | 2026-09-12 | Backend | — | — |
| [ADR-0002](ADR-0002-postgresql-primary-database.md) | PostgreSQL as the Primary Durable Database | accepted | OWNER_CONSTRAINT | 2026-09-12 | Data | — | — |
| [ADR-0003](ADR-0003-redis-queues-horizon.md) | Redis-backed Laravel Queues and Horizon | accepted | OWNER_CONSTRAINT | 2026-09-12 | Background processing | — | — |
| [ADR-0004](ADR-0004-api-first-contract.md) | Shared API-first Application Boundary | accepted | OWNER_CONSTRAINT | 2026-09-12 | API / clients | — | — |
| [ADR-0005](ADR-0005-local-first-docker-compose.md) | Local-first Docker Compose Deployment Baseline | accepted | OWNER_CONSTRAINT | 2026-09-12 | Deployment | — | — |
| [ADR-0006](ADR-0006-monorepo-strategy.md) | Incremental Monorepo Strategy | accepted | DERIVED_ARCHITECTURAL_DECISION | 2026-09-12 | Repository | — | — |
| [ADR-0007](ADR-0007-multi-user-ownership.md) | Shared-schema Multi-user Ownership and Isolation | accepted | OWNER_CONSTRAINT | 2026-09-12 | Security / tenancy | — | — |
| [ADR-0008](ADR-0008-invite-only-access.md) | Invite-only Registration Boundary | accepted | OWNER_CONSTRAINT | 2026-09-12 | Access | — | — |
| [ADR-0009](ADR-0009-strict-truth-guard.md) | Strict Truth Guard and Provenance Invariant | accepted | OWNER_CONSTRAINT | 2026-09-12 | Truth / provenance | — | — |
| [ADR-0010](ADR-0010-provider-independent-llm.md) | Provider-independent LLM Boundary | accepted | OWNER_CONSTRAINT | 2026-09-12 | AI | — | — |
| [ADR-0011](ADR-0011-logical-model-policy.md) | Logical Capability-based Model Policy | accepted | OWNER_CONSTRAINT | 2026-09-12 | AI / cost | — | — |
| [ADR-0012](ADR-0012-git-markdown-obsidian.md) | Git Markdown Documentation with Obsidian as Interface | accepted | OWNER_CONSTRAINT | 2026-09-12 | Documentation | — | — |
| [ADR-0013](ADR-0013-figma-visual-source.md) | Figma as the Reviewed Visual Source of Truth | accepted | OWNER_CONSTRAINT | 2026-09-12 | Design | — | — |
| [ADR-0014](ADR-0014-git-design-tokens.md) | Git-held DTCG Tokens as Machine-readable Canonical Source | accepted | RESEARCH_BACKED_DECISION | 2026-09-12 | Design tokens | — | — |
| [ADR-0015](ADR-0015-file-storage-abstraction.md) | File Storage Abstraction with Local Initial Backend | accepted | DERIVED_ARCHITECTURAL_DECISION | 2026-09-12 | File storage | — | — |
| [ADR-0016](ADR-0016-deterministic-document-rendering.md) | Deterministic DOCX-to-PDF Rendering Pipeline | accepted | OWNER_CONSTRAINT | 2026-09-12 | Documents | — | — |
| [ADR-0017](ADR-0017-untrusted-external-content.md) | Untrusted External Content Boundary | accepted | RESEARCH_BACKED_DECISION | 2026-09-12 | Security / ingestion | — | — |
| [ADR-0018](ADR-0018-runtime-ai-skills-location.md) | Repository Runtime AI Skills Location | accepted | DERIVED_ARCHITECTURAL_DECISION | 2026-09-12 | AI / repository | — | — |
| [ADR-0019](ADR-0019-sanctum-stateful-first-party-auth.md) | Stateful Sanctum Authentication for First-party Web | accepted | DERIVED_ARCHITECTURAL_DECISION | 2026-09-13 | Access / security | — | — |

## Freeze rule

Phase 06 and later work must operate inside accepted ADR boundaries. Architecture freeze means changes are explicit, traceable and versioned; it does not mean architecture can never evolve.
