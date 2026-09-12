---
status: research-complete
date: 2026-09-12
phase: 03-technical-research
owner: CVortex
architecture_decision: none
---

# OpenAI API / Client Options for PHP

## Question

How should a Laravel/PHP core call OpenAI while preserving CVortex's provider-independent business architecture?

## Evidence

- **[E1]** OpenAI's official SDK list currently includes JavaScript/TypeScript, Python, .NET, Java, Go and Ruby. PHP clients are listed under **Community libraries**.
- **[E1]** OpenAI explicitly says it does not verify the correctness or security of community libraries.
- **[E2]** `openai-php/client` is actively maintained and had v0.20.1 released 2026-07-20.
- **[E3]** Laravel 13 ships a first-party Laravel AI SDK that exposes multiple providers, structured output, tools, prompt caching, testing/fakes and failover.
- **[E3]** Laravel AI SDK also supports OpenAI-compatible endpoints and provider-specific options.

## Options

### A. Laravel AI SDK behind CVortex `LlmProvider`

Pros:
- Laravel-native lifecycle/testing ergonomics.
- Multi-provider capability aligns with future provider abstraction.
- Structured output/caching/failover already represented.

Cons:
- Its concept of an `Agent` must not become CVortex's domain definition of Agent/Skill/Workflow by accident.
- Abstraction may lag newly released provider-specific controls.
- Provider-neutral APIs can hide cost/performance knobs if wrapped carelessly.

### B. `openai-php/client` behind CVortex `OpenAIProvider`

Pros:
- Familiar PHP API and active maintenance.
- Closer to OpenAI primitives than a cross-provider framework.

Cons:
- Community-maintained; OpenAI does not security/correctness-certify it.
- Still requires an internal abstraction and careful upgrade testing.

### C. Laravel HTTP client / direct REST implementation

Pros:
- Maximum control and immediate access to newly released OpenAI fields.
- Minimal transitive abstraction risk.
- Very clear provider boundary.

Cons:
- More request/response mapping, streaming, retry/error taxonomy and schema work owned by CVortex.
- Easy to rebuild a mediocre SDK one endpoint at a time, a classic human tradition.

### D. Generated client from OpenAI OpenAPI specification

Pros:
- Can closely mirror official schema.
- Repeatable generation.

Cons:
- Codegen versioning and breaking diffs become operational work.
- Generated APIs may be awkward in Laravel.

## Candidate recommendation, not ADR

1. Keep a **CVortex-owned `LlmProvider` contract** regardless of underlying transport.
2. Spike **Laravel AI SDK** and **direct/OpenAI-specific client access** against the actual CVortex capability matrix: Responses, Structured Outputs, prompt cache accounting, Batch orchestration, streaming, usage metadata, errors/rate limits and BYOK.
3. Do not expose Laravel AI SDK classes or `openai-php` DTOs beyond the infrastructure/provider adapter boundary.
4. Keep a provider-specific escape hatch for options not yet represented by a generic abstraction.
5. Do not use the Laravel AI SDK's agent taxonomy as the product's runtime AI architecture. CVortex's `Skill / Agent / Workflow / Provider / ModelPolicy / Tool` remains its own domain.

## Validation required before Phase 05

- Can the option expose all needed usage fields, especially cached/cache-write tokens?
- Can per-request API keys support BYOK safely without global mutable configuration?
- Can provider-specific OpenAI request fields be passed without forking the library?
- Can errors be normalized without losing original request IDs/rate-limit metadata?
- Is Batch API covered directly, or should it use a separate OpenAI adapter?
- Are release cadence and PHP 8.5/Laravel 13 compatibility acceptable?

## Confidence

High on ecosystem facts; medium on final library choice until a small adapter spike is executed later.

## Citations

- **[E1] OpenAI SDKs and CLI**, official docs, accessed 2026-09-12: https://developers.openai.com/api/docs/libraries
- **[E2] openai-php/client releases**, community project, accessed 2026-09-12: https://github.com/openai-php/client/releases
- **[E3] Laravel AI SDK**, Laravel 13.x official docs, accessed 2026-09-12: https://laravel.com/framework/docs/13.x/ai-sdk
- **[E4] OpenAI OpenAPI repository**, referenced by OpenAI SDK docs: https://github.com/openai/openai-openapi
