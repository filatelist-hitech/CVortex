---
title: ADR-0009 — Strict Truth Guard and Provenance Invariant
status: accepted
decision_nature: OWNER_CONSTRAINT
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, truth, provenance, validation]
related: [ADR-0002, ADR-0004, ADR-0010, ADR-0011]
---

# ADR-0009 — Strict Truth Guard and Provenance Invariant

## Context

The product's central safety property is that candidate-facing statements remain truthful, traceable and consistent with previously used employer-specific statements. AI extraction is probabilistic and cannot establish truth by itself.

## Decision Drivers

- enforce `FACT → CLAIM → GENERATED CONTENT` traceability;
- keep confirmation under human control;
- produce deterministic, testable blocking behavior for unsupported or conflicting claims;
- apply the rule across workflows rather than inside one AI persona.

## Options

1. Strict cross-cutting validation with explicit provenance and state.
2. Prompt-only instruction telling the LLM to be truthful.
3. Generate freely and show warnings after the fact.

## Comparison

| Option | Truth assurance | Testability | Explainability | UX cost |
|---|---|---|---|---|
| Strict validation | High | High | High | Controlled review/block steps |
| Prompt-only | Low | Low | Low | Low initially |
| Warning-only | Medium-low | Medium | Medium | Low but unsafe |

## Decision

Strict Truth Guard is a cross-cutting validation capability and system invariant, not a freely reasoning AI persona:

```text
CONFIRMED CAREER FACT
        ↓
      CLAIM
        ↓
GENERATED CONTENT
```

Every candidate statement eligible for employer-facing use must resolve through claims to `CONFIRMED` Career Facts. AI-extracted or inferred potential facts enter `PENDING`; only explicit human confirmation can transition them to `CONFIRMED`. An LLM cannot perform that transition.

Unsupported provenance is a validation failure. A confirmed contradiction with a statement previously used for the same employer causes a controlled block and explicit user resolution, not a silent warning. Phase 06 defines lifecycle/state contracts without weakening these rules.

## Consequences

### Positive

- auditable and explainable generated material;
- deterministic invariant tests around probabilistic generation;
- employer consistency becomes enforceable product behavior.

### Negative / Risks

- more metadata and review states;
- legitimate nuance may require explicit conflict resolution;
- incomplete Career Facts can block otherwise plausible text.

## Security and Validation Impact

Untrusted external text cannot become fact or instruction authority. Validation must fail closed for missing provenance and confirmed employer contradictions. Authorization must protect facts, claims and employer memory together.

## Reversibility and Revisit Triggers

This owner constraint is intentionally hard to reverse. Workflow ergonomics may evolve, but relaxing confirmation, provenance or controlled blocking requires an owner-approved superseding ADR.

## References

- [PROJECT.md](../../PROJECT.md).
- [Product Principles](../01-Product/Principles.md).
- [Glossary](../01-Product/Glossary.md).
- [Resume Practices](../../research/recruitment/resume-practices.md).
- [External Content Threats](../../research/security/external-content-threats.md).

## Supersedes

None.

## Superseded By

None.
