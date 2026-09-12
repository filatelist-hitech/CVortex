---
title: ADR-0018 — Repository Runtime AI Skills Location
status: accepted
decision_nature: DERIVED_ARCHITECTURAL_DECISION
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, ai, skills, repository]
related: [ADR-0006, ADR-0010, ADR-0011]
---

# ADR-0018 — Repository Runtime AI Skills Location

## Context

Runtime product Skills need a canonical versioned location. `.agents/` is reserved for development agents, while a Laravel-specific location would prematurely bind provider-independent product assets to one runtime.

## Options

1. `/runtime-ai/` at repository root.
2. `/ai/` at repository root.
3. `apps/backend/resources/ai/`.
4. `.agents/`.

## Decision

Use `/runtime-ai/` as the canonical repository location for versioned runtime product Skill definitions, prompts and eval fixtures. It is explicit, discoverable, reusable by future clients/services, independently versioned with prompt registry references, and can be packaged/extracted later. Runtime loading adapters remain implementation details; this does not create a second application runtime. `.agents/` remains development-only.

## Consequences

The directory starts documentation-only. Future runtime assets must use the contracts in Phase 06 AI design and stay independent of provider SDKs. A future package extraction preserves identifiers and history; moving the canonical location requires a new ADR.

## References

- [ADR-0006](ADR-0006-monorepo-strategy.md)
- [ADR-0010](ADR-0010-provider-independent-llm.md)
- [Phase 06 AI Architecture](../06-AI/Phase-06-AI-Design.md)
