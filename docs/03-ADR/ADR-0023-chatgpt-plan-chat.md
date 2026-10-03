---
title: ADR-0023 — Local CVortex chat using ChatGPT plan usage
status: proposed
owner: project
created: 2026-10-04
updated: 2026-10-04
tags: [architecture, ai, oauth, chat]
related: [ADR-0007, ADR-0009, ADR-0010, ADR-0019, ADR-0021]
---

# ADR-0023 — Local CVortex chat using ChatGPT plan usage

## Context and authority

The user explicitly requests analysis using their career data, persisted results, and a chat inside CVortex rather than a conversation in ChatGPT. This is a bounded override of the Preview task pointer, not completion of Preview or permission to start M2. This document is a proposal; no provider integration, OAuth registration, inference or product-data write has been implemented or verified.

The existing MCP interface cannot supply that experience: vacancy_get returns summary fields without raw text, and application_context_get returns empty facts when analysis is not COMPLETED. ADR-0021 permits only two read tools. Adding a write tool would enable a different experience in ChatGPT, not embed a model in CVortex.

Official OpenAI documentation now describes ChatGPT plan usage through Sign in with ChatGPT for open-source and locally hosted apps. This corrects the earlier blanket statement that subscription inference cannot be used by an application. Actual account/workspace permission and deployment eligibility remain unverified. Composer's MIT field alone does not establish the whole application's licensing/eligibility.

## Options

| Option | Chat in CVortex | API project credits | Boundary |
| --- | --- | --- | --- |
| Existing OpenAI API-key provider | Yes, with a new chat UI | Required | Existing provider billing |
| ChatGPT with MCP result submission | No, chat in ChatGPT | No application inference request | Requires superseding ADR-0021 |
| Sign in with ChatGPT plan usage | Yes, with integration | Uses eligible account plan | New outbound OAuth connection; account limits apply |

## Proposed decision

Use the documented direct Sign in with ChatGPT flow for the local deployment, conditional on verified eligibility and real account consent. Keep the inbound MCP surface unchanged. Do not use existing CVortex MCP tokens, Codex credentials, browser cookies or ChatGPT private endpoints for model inference.

Separate first-party CVortex identity from the outbound ChatGPT account connection. Bind each connection and conversation to its CVortex owner; no shared administrator credential. Preserve provider independence through a distinct provider implementation, not replacing the API-key provider. Do not silently fall back to paid API-key inference.

### User flow

1. The authenticated user selects Continue with ChatGPT and authorizes plan usage in the system browser.
2. The user opens a saved vacancy and its CVortex chat. Before sending, display the source/context that will leave the local application.
3. CVortex supplies the owned current vacancy snapshot and bounded confirmed career evidence, including when existing API-based analysis failed. Treat all imported text as untrusted input.
4. Stream the assistant answer into CVortex and persist the owned conversation locally. Interrupted streams remain incomplete; do not publish them as successful analysis.
5. A separate Save analysis action validates extracted requirements against source excerpts and recomputes deterministic matching. Candidate statements remain proposals until supported by confirmed facts and validated through existing Truth Guard rules.

### OAuth and inference contract

- Persist an opaque host identifier before sign-in; use a separate pending state, OIDC nonce and S256 PKCE verifier per attempt.
- Initial registration uses the documented dynamic registration entrypoint. Persist the issued client identifier only after callback and identity validation; returning connections reuse it.
- Use exact loopback 127.0.0.1 callback matching. Preserve the callback URI throughout code exchange.
- Verify OpenAI ID-token signature/JWKS, issuer, issued-client audience, nonce and expiry. Validate granted plan-usage scopes separately from identity.
- Encrypt/protect owner-bound access and refresh credentials server-side. Never expose them to frontend state, logs, repository or diagnostics. Refresh atomically with rotation and concurrency control; disconnect removes local connection credentials.
- Retrieve the model catalog for the signed-in account. Do not hardcode assumed model availability.
- Use the documented public Responses endpoint with store=false, stream=true and required history in input. Current preview does not support hosted MCP/connectors or persistent conversation state on this route; build bounded context from CVortex services directly.
- Never fabricate completion or auto-retry uncertain writes. Fence saved analysis by current snapshot/career signature and use idempotency and existing owner/RLS boundaries.

## Verification gate before product implementation

Implement and validate a minimal owner-bound local sign-in and text-only inference spike first. Human sign-in/consent is required. Establish eligibility, permissions, actual model availability and a completed streamed response without an API-project key. A mocked test is not evidence for live account support. If unavailable, report BLOCKED_EXTERNAL without switching billing routes or automating the ChatGPT website.

After that gate, implement chat persistence/UI and the analysis-save domain path with owner isolation, CSRF, output limits, provenance and stale-result tests. Do not claim existing semantic Truth Guard can run without credits until its dependencies have been inspected and validated.

## Consequences

Adds outbound OAuth credential lifecycle and locally persisted conversations. Account plan limits replace project-credit billing for this connection, not unlimited inference. Multi-user data and generated proposals remain private and untrusted. Inbound MCP ADR-0021 does not need to be superseded for this selected path. Remote/paid deployment is outside this proposal and requires separate eligibility review.

## Sources checked 2026-10-04

- [Plan usage overview](https://developers.openai.com/siwc/token-sharing-open-source)
- [Registration and sign-in](https://developers.openai.com/siwc/token-sharing-open-source/sign-in)
- [Models and inference](https://developers.openai.com/siwc/token-sharing-open-source/models-and-inference)
- [Preview limitations](https://developers.openai.com/siwc/token-sharing-open-source/preview-limitations)
