---
title: ADR-0014 — Git-held DTCG Tokens as Machine-readable Canonical Source
status: accepted
decision_nature: RESEARCH_BACKED_DECISION
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, design, tokens, dtcg]
related: [ADR-0006, ADR-0013]
---

# ADR-0014 — Git-held DTCG Tokens as Machine-readable Canonical Source

## Context

Figma is the visual source of truth, but builds need deterministic, reviewable tokens. Two equal token authorities or fragile bidirectional synchronization would make drift resolution ambiguous.

## Decision Drivers

- deterministic builds and Git review;
- usable Figma variables;
- frontend/tool portability;
- explicit drift ownership and low automation fragility.

## Options

1. Figma-first: Variables are canonical and exported to Git.
2. Code/token-file-first: Git-held DTCG tokens are canonical and synchronized to Figma.
3. Bidirectional synchronization with equal authority.

## Comparison

| Option | Git/versioning | Build reliability | Figma UX | Drift ambiguity |
|---|---|---|---|---|
| Figma-first | Medium | Depends on export | High | Medium |
| Git DTCG first | High | High | Medium-high with sync | Low |
| Bidirectional equal | Medium | Medium | High | High |

## Decision

The canonical machine-readable token source is a Git-held token set using the stable DTCG 2025.10 format. Frontend/theme outputs and Figma Variables are derived or synchronized views. Figma remains the visual/component source of truth under ADR-0013, but it is not a second equal machine token authority.

Synchronization must be explicit and drift-detectable; conflicts resolve through review and an update to the Git token source. A live Figma connection is never required for a deterministic build. Exact token file location, transformer and sync mechanism are deferred until implementation/spike validation. Style Dictionary is a candidate, not frozen architecture, because complete DTCG support was not confirmed.

## Consequences

### Positive

- reproducible builds, code review and rollback;
- unambiguous conflict resolution;
- tooling can change around a stable interchange format.

### Negative / Risks

- designers need a disciplined sync workflow;
- unsupported token types may require constrained conventions;
- delayed synchronization can make Figma temporarily stale.

## Security and Validation Impact

Tokens must contain no secrets or private data. Future validation checks schema, references, generated outputs and drift. Tool credentials for Figma remain outside token files and Git.

## Reversibility and Migration Implications

The DTCG source can migrate through reviewed transformations. Revisit if a reliable audited Figma-first workflow proves deterministic and materially better; changing canonical authority requires a superseding ADR and one-time reconciliation.

## References

- [Design Tokens, Figma MCP and Code Connect](../../research/technical/10-DESIGN-TOKENS-FIGMA.md).
- [Technical Decision Candidates](../../research/technical/DECISION-CANDIDATES.md).
- [PROJECT.md](../../PROJECT.md).

## Supersedes

None.

## Superseded By

None.
