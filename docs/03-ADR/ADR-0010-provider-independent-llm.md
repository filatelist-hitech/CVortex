---
title: ADR-0010 — Provider-independent LLM Boundary
status: accepted
decision_nature: OWNER_CONSTRAINT
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, ai, llm, providers]
related: [ADR-0001, ADR-0009, ADR-0011, ADR-0017]
---

# ADR-0010 — Provider-independent LLM Boundary

## Context

CVortex uses LLMs for semantic work but cannot couple domain logic, truth rules or workflows to one provider SDK. It must support system-managed credentials, BYOK and later providers while avoiding abstraction cosplay.

## Decision Drivers

- provider portability and testability;
- secure per-request credential ownership;
- separation of product taxonomy from SDK taxonomy;
- controlled access to provider-specific capabilities.

## Options

1. A small CVortex-owned provider boundary and normalized contracts.
2. Use provider SDK objects directly throughout business logic.
3. Build a large universal AI framework before implementation.

## Comparison

| Option | Independence | Simplicity | Provider features | Testability |
|---|---|---|---|---|
| Small owned boundary | High | High | Via adapters/escape hatch | High |
| Direct SDK coupling | Low | High initially | High | Medium-low |
| Universal framework | High in theory | Low | Often lowest-common-denominator | Medium |

## Decision

Keep these concepts distinct:

```text
Provider | ModelPolicy | Skill | Agent | Workflow | Tool | Prompt | ContextBuilder
```

Business/domain logic depends on CVortex-owned contracts, not provider SDK types. A Provider adapter handles transport, normalized responses/errors/usage and bounded provider-specific parameters. `ContextBuilder` selects authorized, task-relevant context; Skills and Workflows retain domain meaning even if an underlying framework also uses the word “agent”.

OpenAI may be the first implementation adapter, but that is not an immutable domain dependency. System-managed credentials and BYOK are supported conceptually through explicit credential ownership/resolution at the provider boundary. Concrete encryption and transport libraries are deferred.

## Consequences

### Positive

- providers and transports can change without rewriting core workflows;
- fakes/evals can target stable contracts;
- credential and usage policy have one boundary.

### Negative / Risks

- adapters require maintenance and error normalization;
- an over-generic interface may hide useful provider features;
- BYOK adds rotation, redaction and tenant-scope complexity.

## Security and Validation Impact

Credentials never enter prompts, logs or client responses. Context is least-privileged and ownership-scoped. Provider adapters must preserve request IDs and safe diagnostics without secret leakage. External content cannot grant tools or instruction authority.

## Reversibility and Revisit Triggers

The interface can evolve version-by-version. A single-provider hard coupling or a new provider architecture requires a superseding ADR; selecting a concrete adapter library does not.

## References

- [PROJECT.md](../../PROJECT.md).
- [OpenAI PHP Integration](../../research/technical/05-OPENAI-PHP-INTEGRATION.md).
- [OpenAI API and Models](../../research/technical/04-OPENAI-API-MODELS.md) — dated capability evidence only.
- [External Content Threats](../../research/security/external-content-threats.md).

## Supersedes

None.

## Superseded By

None.
