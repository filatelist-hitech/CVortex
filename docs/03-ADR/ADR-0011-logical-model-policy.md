---
title: ADR-0011 — Logical Capability-based Model Policy
status: accepted
decision_nature: OWNER_CONSTRAINT
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, ai, model-routing, cost]
related: [ADR-0009, ADR-0010]
---

# ADR-0011 — Logical Capability-based Model Policy

## Context

Provider catalogs, model names, pricing and parameters change faster than domain workflows. CVortex also needs cost-aware escalation based on measurable task risk and validation results.

## Decision Drivers

- stable domain intent despite provider churn;
- measurable quality/cost/latency trade-offs;
- deterministic validation before escalation;
- provider-independent configuration.

## Options

1. Logical capability/quality/cost policies mapped through mutable configuration.
2. Hardcode concrete model names in workflows.
3. Always use the most capable model.
4. Let an LLM choose another LLM without policy gates.

## Comparison

| Option | Stability | Cost control | Explainability | Quality control |
|---|---|---|---|---|
| Logical policy | High | High | High | High with evals |
| Hardcoded names | Low | Medium | Medium | Medium |
| Always strongest | Medium | Low | High | High but wasteful |
| Free-form routing | Low | Low | Low | Unreliable |

## Decision

ModelPolicy selects logical capabilities such as:

- low-cost extraction/classification;
- standard semantic generation/analysis;
- advanced reasoning/escalation.

Mutable configuration resolves:

```text
logical capability → provider → model → provider-specific parameters
```

No ADR permanently fixes a model name, price, reasoning preset or provider catalog. Routing considers task capability, error risk, validation result, context size, latency, cost ceiling, required reasoning, structured-output capability and fallback policy.

Escalation must be triggered by measurable reasons: schema/invariant failure, confidence/risk threshold, unsupported capability, context constraints, provider error or evaluation policy. “Make it prettier” is not telemetry.

## Consequences

### Positive

- controlled spend and explicit quality trade-offs;
- provider/model changes are configuration changes validated by evals;
- observable routing and fallbacks.

### Negative / Risks

- policies and mappings need versioning, metrics and evaluation data;
- bad thresholds can under-route or overspend;
- provider capability differences still leak into adapter configuration.

## Security and Validation Impact

Routing cannot bypass Truth Guard, authorization or context-minimization rules. Later validation records policy version, actual provider/model, latency, token/usage data and escalation reason where available.

## Reversibility and Revisit Triggers

Tier names and mappings are intentionally reversible configuration. Revisit the architecture only if policy evaluation cannot express a proven routing need.

## References

- [PROJECT.md](../../PROJECT.md).
- [Product Principles](../01-Product/Principles.md).
- [OpenAI API and Models](../../research/technical/04-OPENAI-API-MODELS.md) — evidence checked 2026-09-12; catalog and prices are volatile.
- [Technical Decision Candidates](../../research/technical/DECISION-CANDIDATES.md).

## Supersedes

None.

## Superseded By

None.
