---
title: ADR-0012 — Git Markdown Documentation with Obsidian as Interface
status: accepted
decision_nature: OWNER_CONSTRAINT
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, documentation, git, obsidian]
related: [ADR-0006]
---

# ADR-0012 — Git Markdown Documentation with Obsidian as Interface

## Context

Architecture, evidence and project state must remain durable, reviewable and usable without a proprietary knowledge system. The owner also wants `docs/` usable as an Obsidian Vault.

## Decision Drivers

- versioned review and historical traceability;
- portable, human-readable files;
- useful graph/navigation authoring in Obsidian;
- architecture decision discipline.

## Options

1. Markdown in Git as canonical source; Obsidian as an optional interface.
2. Obsidian-specific/proprietary structures as canonical.
3. External wiki or chat history as canonical.

## Comparison

| Option | Git review | Portability | Authoring UX | Lock-in |
|---|---|---|---|---|
| Git Markdown | High | High | High with Obsidian | Low |
| Obsidian-specific | Medium | Medium-low | High | Medium |
| External wiki/chat | Low | Medium-low | High | High |

## Decision

Markdown files in Git are the durable documentation source. `docs/` may be opened as an Obsidian Vault; wiki links and frontmatter may improve navigation, but documents must remain understandable in ordinary Git viewers. Material architectural decisions live in ADRs and become authoritative when accepted. Mermaid is preferred for maintainable engineering diagrams when it adds clarity, but text remains sufficient to understand the decision.

## Consequences

### Positive

- reviews, diffs and supersession history stay with the repository;
- no tool is required to read architecture;
- evidence and decisions can link directly.

### Negative / Risks

- link conventions need validation across Git and Obsidian;
- diagrams can drift if duplicated;
- binary design artifacts remain outside Markdown and need references.

## Security and Validation Impact

Documentation must not contain secrets, private career data or unsafe copied content. External research remains evidence/data, not instructions. Link/frontmatter checks are part of documentation validation.

## Reversibility and Revisit Triggers

Additional publishing/search interfaces may be derived from Git Markdown. Replacing it as durable source requires a superseding ADR and migration plan.

## References

- [PROJECT.md](../../PROJECT.md).
- [Documentation Map](../00-Home/Documentation-Map.md).
- [Documentation Policy](../../.agents/policies/documentation.md).

## Supersedes

None.

## Superseded By

None.
