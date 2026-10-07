---
title: ADR-0023 — Local CVortex chat using ChatGPT plan usage
status: accepted
owner: project
created: 2026-10-04
updated: 2026-10-04
tags: [architecture, ai, oauth, chat]
related: [ADR-0007, ADR-0009, ADR-0010, ADR-0019, ADR-0021]
---

# ADR-0023 — Local CVortex chat using ChatGPT plan usage

## Context and authority

The user explicitly requests analysis using their career data, persisted results, and a chat inside CVortex rather than a conversation in ChatGPT. This is a bounded override of the Preview task pointer, not completion of Preview or permission to start M2. The expanded explicit user request authorizes embedded chat and a single controlled MCP draft write. On 2026-10-04 the user reported a completed live OAuth/streaming connection check with “Hello, CVortex.” This is live user-reported evidence; it is not yet vacancy-chat or write-back acceptance.

The previous MCP interface returned summary fields without raw text and empty facts when analysis was not COMPLETED. This implementation adds bounded source snapshots and relevant confirmed facts to the preserved read tools. ADR-0021 originally permitted only two read tools. Adding a write tool would enable a different experience in ChatGPT, not embed a model in CVortex.

Official OpenAI documentation now describes ChatGPT plan usage through Sign in with ChatGPT for open-source and locally hosted apps. This corrects the earlier blanket statement that subscription inference cannot be used by an application. Live account permission and completed plan inference were verified for this local installation; broader workspace/deployment eligibility is not implied. Composer's MIT field alone does not establish the whole application's licensing/eligibility.

## Options

| Option | Chat in CVortex | API project credits | Boundary |
| --- | --- | --- | --- |
| Existing OpenAI API-key provider | Yes, with a new chat UI | Required | Existing provider billing |
| ChatGPT with MCP result submission | No, chat in ChatGPT | No application inference request | Requires superseding ADR-0021 |
| Sign in with ChatGPT plan usage | Yes, with integration | Uses eligible account plan | New outbound OAuth connection; account limits apply |

## Decision

Use the documented direct Sign in with ChatGPT flow for the local deployment, conditional on verified eligibility and real account consent. Amend ADR-0021 only for `vacancy_analysis_draft_save`: it creates an owned, validated, idempotent DRAFT and invokes the same application service as embedded Save analysis. Keep both read tools; no fact mutation, approval or application submission through MCP. The draft-save tool requires the separate `mcp:draft:write` OAuth scope in addition to `mcp:use`; consent must disclose that it creates unapproved drafts, and older read-only grants must not authorize this write. Do not use existing CVortex MCP tokens, Codex credentials, browser cookies or ChatGPT private endpoints for model inference.

Separate first-party CVortex identity from the outbound ChatGPT account connection. Bind each connection and conversation to its CVortex owner; no shared administrator credential. Preserve provider independence through a distinct provider implementation, not replacing the API-key provider. Do not silently fall back to paid API-key inference.

### User flow

1. The authenticated user selects Continue with ChatGPT and authorizes plan usage in the system browser.
2. The user opens a saved vacancy and its CVortex chat. Before sending, display the source/context that will leave the local application.
3. CVortex supplies the owned current vacancy snapshot and bounded confirmed career evidence, including when existing API-based analysis failed. Treat all imported text as untrusted input.
4. Stream the assistant answer into CVortex and persist the owned conversation locally. Interrupted streams remain incomplete; do not publish them as successful analysis.
5. A separate Save analysis action validates extracted requirements against source excerpts and creates a DRAFT; explicit Approve analysis recomputes deterministic matching. Candidate statements remain proposals until supported by confirmed facts and validated through existing Truth Guard rules.

### OAuth and inference contract

- Persist an opaque host identifier before sign-in; use a separate pending state, OIDC nonce and S256 PKCE verifier per attempt.
- Initial registration uses the documented dynamic registration entrypoint. Retain the issued client identifier after a validated-state callback even if code exchange fails; only activate credentials after identity validation; returning connections reuse it.
- Use exact loopback 127.0.0.1 callback matching. Preserve the callback URI throughout code exchange.
- Verify OpenAI ID-token signature/JWKS, issuer, issued-client audience, nonce and expiry. Validate granted plan-usage scopes separately from identity.
- Encrypt/protect owner-bound access and refresh credentials server-side. Never expose them to frontend state, logs, repository or diagnostics. Refresh atomically with rotation and concurrency control; disconnect removes local connection credentials.
- Retrieve the model catalog for the signed-in account. Do not hardcode assumed model availability.
- Use the documented public Responses endpoint with store=false, stream=true and required history in input. Current preview does not support hosted MCP/connectors or persistent conversation state on this route; build bounded context from CVortex services directly.
- Never fabricate completion or auto-retry uncertain writes. Fence saved analysis by current snapshot/career signature and use idempotency and existing owner/RLS boundaries.

## Verification gate — passed 2026-10-04

Implement and validate a minimal owner-bound local sign-in and text-only inference spike first. Human sign-in/consent is required. Establish eligibility, permissions, actual model availability and a completed streamed response without an API-project key. A mocked test is not evidence for live account support. If unavailable, report BLOCKED_EXTERNAL without switching billing routes or automating the ChatGPT website.

After that gate, implement chat persistence/UI and the analysis-save domain path with owner isolation, CSRF, output limits, provenance and stale-result tests. Do not claim existing semantic Truth Guard can run without credits until its dependencies have been inspected and validated.

## Consequences

The initial gate passed through user-reported consent/greeting and agent-observed vacancy streaming. Adds outbound OAuth credential lifecycle and locally persisted conversations. Account plan limits replace project-credit billing for this connection, not unlimited inference. Multi-user data and generated proposals remain private and untrusted. ADR-0021 is amended by this decision for the one draft-save tool. Other restrictions remain authoritative. Remote/paid deployment is outside this decision and requires separate eligibility review.

## Sources checked 2026-10-04

- [Plan usage overview](https://developers.openai.com/siwc/token-sharing-open-source)
- [Registration and sign-in](https://developers.openai.com/siwc/token-sharing-open-source/sign-in)
- [Models and inference](https://developers.openai.com/siwc/token-sharing-open-source/models-and-inference)
- [Preview limitations](https://developers.openai.com/siwc/token-sharing-open-source/preview-limitations)

## Implementation details verified 2026-10-04

Direct HTTP Responses uses only model, instructions, bounded input, store=false and stream=true. UUIDv4 URN host identity is a supported alternative to the recommended JWK thumbprint. Model discovery reads models/visibility/slug/display_name. Credentials extend the existing server-side provider boundary: no reusable outbound credential store existed; the API-key configuration remains intact. Optional ID-token hints are omitted and ID tokens are discarded after verification, keeping secrets out of authorization URLs. Refresh uses a database row lock and commits terminal credential invalidation before raising its safe error.

Draft requirements use the existing requirement validator and domain enums. Approval recomputes deterministic matching; it never trusts proposed matches. Existing canonical analysis cannot be silently replaced: conflicting approved/extracted requirements block promotion. No Employer Memory or Career Track aggregate currently exists; bounded prior approved statements for the same company supply available employer context, and absent track context is explicit.

Additional official sources: [Accounts/sessions](https://developers.openai.com/siwc/token-sharing-open-source/profiles-and-sessions), [Token reference](https://developers.openai.com/siwc/token-sharing-open-source/token-reference), [Errors/recovery](https://developers.openai.com/siwc/token-sharing-open-source/errors-and-recovery), [UI/UX](https://developers.openai.com/siwc/ui-ux-guidelines). These preview contracts must be rechecked when integration behavior changes.

Literal recognized requirement/nice-to-have section headings supply classification when the existing single-clause validator yields UNCERTAIN. Unrecognized headings and responsibilities remain uncertain. Labels still require literal source support; model classification cannot override the source.
