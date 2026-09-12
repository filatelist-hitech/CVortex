---
title: ADR-0013 — Figma as the Reviewed Visual Source of Truth
status: accepted
decision_nature: OWNER_CONSTRAINT
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, design, figma, accessibility]
related: [ADR-0014]
---

# ADR-0013 — Figma as the Reviewed Visual Source of Truth

## Context

CVortex needs a coherent visual system across responsive states. The owner defines Figma as the visual source of truth, while code must remain testable and buildable without live Figma access.

## Decision Drivers

- reviewed visual intent and reusable design components;
- responsive and accessibility states visible before implementation;
- design/code traceability;
- no dependence on unverified automation capability.

## Options

1. Figma as reviewed visual/component authority with explicit code mapping.
2. Code screenshots as the only design authority.
3. Autonomous tool-generated Figma output as authority.

## Comparison

| Option | Design coherence | Reviewability | Build determinism | Automation risk |
|---|---|---|---|---|
| Reviewed Figma | High | High | High with separate tokens | Controlled |
| Code-only | Medium | High | High | Low |
| Autonomous canvas | Variable | Low | Medium | High |

## Decision

Figma is the reviewed visual source of truth for approved interface appearance, component states, responsive behavior and design-system intent. Variables, reusable components, accessibility annotations/states and code mappings should support consistency.

Figma tooling/MCP may assist reading and authoring only within capabilities confirmed by dated research and available permissions. Automated canvas writes are proposals requiring human review; no beta/tool capability becomes an architectural dependency. This ADR does not create screens or components.

The machine-readable token authority is decided separately in ADR-0014; Figma visual authority does not create two equal token sources.

## Consequences

### Positive

- one reviewed visual language across product surfaces;
- implementation can be compared against explicit states;
- reusable components and variables reduce design drift.

### Negative / Risks

- design/code drift still requires process and checks;
- seats, permissions and tool capabilities can change;
- accessibility requires implementation testing, not only annotations.

## Security and Validation Impact

Do not place secrets or private candidate data into Figma. Tool-produced changes require review. Future UI validation covers responsive states, keyboard/focus behavior, contrast and semantic accessibility.

## Reversibility and Revisit Triggers

Tooling choices are replaceable. Changing the approved visual authority requires an owner-approved superseding ADR.

## References

- [PROJECT.md](../../PROJECT.md).
- [Design Tokens, Figma MCP and Code Connect](../../research/technical/10-DESIGN-TOKENS-FIGMA.md) — capabilities checked 2026-09-12.
- [Product Vision](../01-Product/Vision.md).

## Supersedes

None.

## Superseded By

None.
