---
status: research-complete
date: 2026-10-24
phase: feature-gemini-provider
owner: CVortex
architecture_decision: ADR-0026
---

# Google Gemini API / Client Options for PHP

## Question

How should CVortex integrate Google Gemini as a secondary LLM provider alongside OpenAI, preserving CVortex's provider-independent architecture, strict Truth Guard, logical ModelPolicy, safe secret handling, and testability?

## Evidence

- **[E1]** Google Gemini provides official SDKs primarily for Python, TypeScript/JavaScript, Go, and Java. For PHP, no official first-party Google GenAI SDK exists; available PHP packages are community-maintained (e.g. `google-gemini-php/client`).
- **[E2]** Official Gemini REST API v1beta (`https://generativelanguage.googleapis.com/v1beta`) is stable, comprehensively documented, and supports:
  - Header-based authentication (`x-goog-api-key: <key>`), preventing query parameter secret leakage into access logs, web proxies, or browser history.
  - Endpoint: `POST /v1beta/models/{model}:generateContent`.
  - System instructions via top-level `systemInstruction: { parts: [{ text: "..." }] }`.
  - Structured output via `generationConfig.responseMimeType = "application/json"` and `generationConfig.responseSchema`.
  - Usage tracking via `usageMetadata` (`promptTokenCount`, `candidatesTokenCount`, `totalTokenCount`).
  - Controlled safety ratings and finish reasons (`STOP`, `SAFETY`, `MAX_TOKENS`, `RECITATION`, `BLOCKLIST`).
- **[E3]** Gemini supports an OpenAI-compatible endpoint (`/v1beta/openai/chat/completions`). However:
  - It adds an extra layer of abstraction emulation that can lag behind native Gemini features and parameter updates.
  - OpenAPI/JSON Schema strict mapping differs subtly from OpenAI's `strict: true` response format.
  - Error responses and safety block structures are normalized through an OpenAI proxy format rather than native error metadata.
- **[E4]** CVortex's existing `OpenAiResponsesProvider` already uses the native Laravel HTTP client (`Illuminate\Support\Facades\Http`) directly. This approach:
  - Requires zero third-party composer dependencies.
  - Eliminates dependency hell with future PHP 8.4+ and Laravel runtime upgrades.
  - Provides native test isolation and deterministic mocking via `Http::fake()`.
  - Retains full ownership of request building, error taxonomy mapping, latency tracking, retry parsing, and diagnostic redaction.

## Options

### A. Community PHP Gemini SDK (`google-gemini-php/client`)

Pros:
- Ready-made PHP object wrapper.

Cons:
- Community-maintained; not verified or security-certified by Google.
- Extra transitive dependencies that can conflict with Laravel packages.
- Additional abstraction layer between CVortex's `LlmProvider` and the transport.

### B. Gemini OpenAI-Compatible Endpoint via Existing OpenAI Adapter

Pros:
- Minimal initial lines of code.

Cons:
- Conflates provider behaviors and masks Gemini-specific error codes and safety structures.
- Violates CVortex's explicit guideline: *"Не выбирай OpenAI-compatible endpoint только потому, что он упрощает первый запрос."*

### C. Native REST API through Laravel HTTP Client (Recommended)

Pros:
- Complete parity with the design of `OpenAiResponsesProvider`.
- Zero new third-party dependencies.
- Native `Http::fake()` mocking in unit/feature tests.
- Direct support for `x-goog-api-key` header, `systemInstruction`, `generationConfig.responseSchema`, and `usageMetadata`.
- Deterministic error classification and retry handling.

Cons:
- Request/response mapping and schema sanitization are owned by CVortex.

## Decision

Adopt **Option C: Native REST API via Laravel HTTP Client** encapsulated behind `GeminiResponsesProvider`.
Support both system-managed credentials (`GEMINI_API_KEY`) and per-user BYOK with encryption at rest.
