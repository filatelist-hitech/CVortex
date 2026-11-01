---
title: ADR-0026 — Google Gemini LLM Provider Integration
status: accepted
decision_nature: RESEARCH_BACKED_DECISION
owner: product-owner
created: 2026-10-24
updated: 2026-10-24
tags: [architecture, ai, llm, providers, gemini, security]
related: [ADR-0007, ADR-0010, ADR-0011]
---

# ADR-0026 — Google Gemini LLM Provider Integration

## Context

CVortex requires multi-provider resilience for runtime AI workflows (such as career extraction, vacancy requirement analysis, and application draft preparation). Until now, OpenAI has been the sole structured LLM provider implementation. Adding Google Gemini as an alternative provider must adhere to the provider-independent AI boundary (ADR-0010), logical capability-based model policy (ADR-0011), and tenant-isolated security baseline (ADR-0007).

## Decision Drivers

- Maintain provider independence without coupling domain logic to Gemini-specific formats.
- Avoid introducing uncertified community libraries or dependencies that risk conflict with Laravel/PHP.
- Support both system-managed credentials (`GEMINI_API_KEY`) and tenant-isolated BYOK encrypted at rest.
- Provide a safe, non-mutating connection test mechanism.
- Prevent unannounced or silent fallbacks between providers.
- Maintain full parity with Truth Guard and validation invariants.

## Decision

1. **Transport & Adapter**:
   Implement `GeminiResponsesProvider` using Laravel's native HTTP client (`Illuminate\Support\Facades\Http`) targeting the official Gemini REST API v1beta (`POST /v1beta/models/{model}:generateContent`).
   Authenticate requests using the `x-goog-api-key` HTTP header rather than query parameters, preventing secret exposure in request URLs and server access logs.

2. **Provider Registration & Routing**:
   Register provider ID `'gemini'` in `ConfiguredLlmProvider`.
   `ModelPolicyResolver` resolves the requested `ModelPolicy` by checking:
   - Authenticated user's explicit provider preference (from `user_ai_settings`), if configured and active.
   - Or the system-configured default provider/policy mapping (`config('ai.model_policies.{policy}')`).
   Silent fallbacks are strictly prohibited: if the active provider fails, the error is normalized to `LlmProviderException` and surfaced directly.

3. **Structured Output & Schema Adaptation**:
   Pass structured schemas via `generationConfig.responseSchema` with `responseMimeType: "application/json"`.
   Sanitize application JSON schemas to strict Gemini OpenAPI 3.0 requirements:
   - Inlines and dereferences local `$ref` pointers (from `$defs` or `definitions`) into a flat schema and removes top-level `$defs`/`definitions`.
   - Normalizes nullable unions (`type: ["string", "null"]` → `type: "string", nullable: true`).
   - Removes `additionalProperties` only as an object schema directive while strictly preserving user domain properties named `additionalProperties`.
   - Strips metadata keywords (`$schema`, `$id`) while preserving all semantic constraints (`required`, `enum`, `items`, `minItems`, `maxItems`, `minimum`, `maximum`, `minLength`, `maxLength`).
   - Rejects unsupported circular or external `$ref` safely (`LlmProviderException::INVALID_CONFIGURATION`).
   - Maintains server-side domain validation in CVortex services as the primary authority regardless of provider adaptation.
   Map finish reasons: `STOP` → success, `SAFETY`/`RECITATION`/`BLOCKLIST` → `LlmProviderException::REFUSAL`, `MAX_TOKENS` → `LlmProviderException::INCOMPLETE`.
   Map errors: 400/401/403 → `INVALID_CONFIGURATION`, 429 → `RATE_LIMITED` (with `Retry-After`), 500/503 → `TEMPORARY_UNAVAILABLE`, timeouts → `TRANSPORT`.

4. **Credential Security, BYOK & Pricing Isolation**:
   - System credentials live in environment configuration (`GEMINI_API_KEY`) and are never written to Git.
   - User BYOK keys are persisted in `user_ai_settings` using Laravel's native `'encrypted'` Eloquent cast (AES-256-CBC at rest).
   - PostgreSQL Row-Level Security (`cvortex.owner_id`) isolates settings between users under non-privileged runtime roles (`cvortex_app`).
   - Conflicting BYOK mutation requests (submitting a new key while simultaneously setting `clear_*_key: true`) are rejected with `422 Unprocessable Content`.
   - Credentials are hidden (`$hidden`) and are never returned to the frontend or written to application logs.
   - Cost estimation strictly isolates provider models: custom or unknown models without per-model configured pricing return `null` rather than silently inheriting default model costs. Zero-cost free models are distinguished as `0` micros.

5. **Safe Connection Testing**:
   Expose `POST /api/v1/ai/test-connection` protected by rate-limiting (`throttle:10,1`). The test sends a minimal probe (`ping`) with `maxOutputTokens: 5`. It touches zero career facts, vacancies, or domain models, and returns latency/status without secret leakage.

## Consequences

### Positive
- Zero external package dependencies added.
- True multi-provider choice for CVortex users and operators.
- Strict isolation of keys and no silent degradation.
- Direct testability with Laravel's `Http::fake()`.

### Negative / Risks
- Ongoing maintenance of Gemini-specific schema adjustments.
- Quota and rate-limit variances between OpenAI and Gemini require distinct observability.

## Amendment — 2026-11-01: Schema Sanitization for Google Developer API v1beta

In Section 3, the schema sanitizer behavior is amended for compatibility with Google Gemini REST API v1beta (`generativelanguage.googleapis.com`):
1. **Keyword Sanitization**: Google's `v1beta/models/{model}:generateContent` endpoint rejects standard OpenAPI keywords (`minItems`, `maxItems`, `minimum`, `maximum`, `minLength`, `maxLength`, `pattern`, `default`, `title`, `examples`) with `HTTP 400 INVALID_ARGUMENT (unknown field / unsupported property)`.
2. **Deterministic Enforcement Boundary**: In accordance with the *Deterministic Before AI* architectural principle, these keywords are stripped in `GeminiResponsesProvider::sanitizeSchema()` before payload transmission.
3. **Backend Authority**: Strict validation of string lengths, array bounds (`count($facts) <= 50`), and numeric ranges is enforced deterministically by CVortex backend domain validators (`CareerExtractionService`, `VacancyRequirementValidator`, etc.) before any data is persisted.

## Amendment — 2026-11-01: Deterministic Truth-First Semantic Validation of Career Fact Categories

Under ADR-0009 (Strict Truth Guard), extraction and validation fail closed:
1. **Deterministic Category Whitelist**: `CareerSemanticValidator` deterministically validates syntax and contextual cues for supported categories (`SKILL`, `EXPERIENCE`, `LEADERSHIP`, `RESPONSIBILITY`, `EMPLOYMENT_PERIOD`, `SENIORITY`, `TECHNOLOGY_DEPTH`).
2. **Fail-Closed Unchecked Categories**: Fact categories without deterministic semantic validation rules (e.g. `education`, `achievement`, `language`, `certification`) are intentionally rejected (`default => false`). Syntactically well-formed LLM extractions in unvalidated categories never bypass validation into candidate facts without verified deterministic checks.

## References

- [ADR-0010 Provider-independent LLM Boundary](ADR-0010-provider-independent-llm.md)
- [ADR-0011 Logical Capability-based Model Policy](ADR-0011-logical-model-policy.md)
- [Google Gemini Integration Research](../../research/technical/12-GOOGLE-GEMINI-INTEGRATION.md)
