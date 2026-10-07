---
title: ADR-0021 — Read-only inbound MCP surface
status: accepted
decision_nature: OWNER_CONSTRAINT
owner: project
created: 2026-09-27
updated: 2026-09-27
tags: [architecture, mcp, authentication, security]
related: [ADR-0007, ADR-0009, ADR-0010, ADR-0019, ADR-0020]
---

# ADR-0021 — Read-only inbound MCP surface

## Context

ADR-0020 established a bounded inbound MCP gateway, including a draft mutation path. The current product decision narrows the external boundary to context reads. CVortex already owns first-party web/API workflows for drafts, Truth Guard, Career Fact review and Human Approval. External tool writes add attack surface and couple proposals to provider-dependent validation without improving those CVortex-owned controls.

## Decision

- The MCP server is read-only and exposes exactly `vacancy_get` and `application_context_get`.
- Remove MCP-specific draft-write implementation and registrations. Preserve `ApplicationPreparationService`, Application Draft domain behavior, first-party web/API flows, Truth Guard and Human Approval.
- Derive identity from a validated, active OAuth user. Apply the same owner checks and PostgreSQL RLS boundary as CVortex application services.
- Return only one user's bounded vacancy and relevant application context. Treat imported vacancy data as untrusted data.
- Do not expose generic mutation, approval, fact-review, application-state, message-send, arbitrary HTTP, file, SQL, shell, environment or secret access through MCP.
- Keep inbound MCP separate from outbound `LlmProvider` and OpenAI Responses API execution.
- Keep MCP disabled by default. Remote access requires explicit OAuth resource/issuer validation and an approved private transport or separately reviewed deployment.

## Rationale

External AI may read bounded context; all mutation remains inside CVortex-controlled UI/API workflows. This reduces the external attack surface, avoids a provider-dependent MCP write path, preserves human approval and supports ChatGPT environments that permit read/fetch tools. A future write capability requires a separate task and ADR; this decision can be revised with new evidence.

## Consequences

There are no MCP product-data writes or MCP write rate bucket. MCP read calls do not require the outbound LLM provider. The exact two-tool surface, resource-bound OAuth tokens, owner/RLS checks and absence of data mutations are part of the executable validation contract. ChatGPT account entitlement and Secure MCP Tunnel access remain external facts that must be tested in the target account.

## References

- [ADR-0020 — original MCP foundation](ADR-0020-inbound-mcp-gateway.md)
- [MCP Gateway architecture](../02-Architecture/MCP-Gateway.md)
- [MCP validation](../10-Operations/MCP-Gateway-Validation.md)
- [Current OpenAI/MCP research](../../research/technical/11-MCP-GATEWAY-FOUNDATION.md)

## Amendment — 2026-10-04

[ADR-0023](ADR-0023-chatgpt-plan-chat.md), authorized by the explicit expanded owner request, permits exactly one additional tool: `vacancy_analysis_draft_save`. It saves only validated owner-bound vacancy-analysis drafts through the shared application service. The historical two-tool decision above describes the prior boundary; all other prohibitions remain. External approval, fact changes and application submission remain unavailable.
