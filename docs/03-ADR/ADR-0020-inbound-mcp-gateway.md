---
title: ADR-0020 — Inbound MCP Gateway as a bounded access channel
status: accepted
decision_nature: DERIVED_ARCHITECTURAL_DECISION
owner: project
created: 2026-09-25
updated: 2026-09-25
tags: [architecture, mcp, authentication, ai]
related: [ADR-0007, ADR-0009, ADR-0010, ADR-0019]
---

# ADR-0020 — Inbound MCP Gateway as a bounded access channel

## Context

CVortex has a provider-independent outbound `LlmProvider` and an existing Responses API implementation. External AI clients need a narrow, user-specific way to read context and propose drafts. ADR-0019 fixes stateful Sanctum authentication for the first-party web client; it does not define third-party MCP authentication. Truth Guard, owner isolation and CVortex Human Approval remain mandatory.

## Decision

- Add a separate inbound MCP adapter in Laravel, disabled by default (`MCP_ENABLED=false`), registered through an explicit tool allowlist. Keep the outbound Responses API path intact.
- Use `laravel/mcp` for Streamable HTTP protocol and Passport for OAuth bearer credentials. Derive the owner from the validated token, require `mcp:use`, active account state, per-owner queries and PostgreSQL owner context. Continue Sanctum sessions for the first-party web UI.
- Initially expose `vacancy_get`, `application_context_get` and `application_draft_submit`. Return only bounded normalized vacancy and relevant confirmed Career evidence; do not expose raw source excerpts, other users, secrets, generic query/proxy/shell tools or approval/send/fact-confirmation tools.
- Route draft submission through `ApplicationPreparationService` and its current Truth Guard. Persist a passing draft as internal `DRAFT`; expose `PENDING_REVIEW` as the MCP-facing review state. Only CVortex's existing authenticated human workflow can accept/approve it. A blocked or unavailable review cannot save an approved draft.
- Choose local Inspector/loopback validation now (deployment option C). Public HTTPS or Secure MCP Tunnel requires a separate connection gate: reachability, OAuth resource/audience compliance, current account entitlement, security review and operational/billing verification. Tunnel is infrastructure, never a domain component.

## Consequences and limits

The gateway is multi-user compatible without selecting a permanent LLM provider. It adds Passport tables, local signing keys, a new authorization boundary and a small amount of operational work. Existing `LlmProvider` remains required by current application semantic Truth Guard, so MCP draft validation is not presently independent of outbound API availability. `EmployerConsistencyCheck`/Employer Memory is deferred because the M4 domain service is absent; it cannot be represented as a passing validation. ChatGPT OAuth E2E is unverified, particularly `resource` audience binding, and no claim is made about this account's Plus entitlement or tunnel charges.

## Alternatives considered

- Convert MCP into another `LlmProvider`: rejected because inbound tool access and outbound model execution have different contracts.
- Expose REST endpoints automatically as MCP tools: rejected because it bypasses deliberate exposure review.
- Use shared static bearer token or session cookie for external clients: rejected for multi-user, OAuth and CSRF boundaries.
- Implement MCP transport manually: rejected in favor of the maintained Laravel integration.

## References

- [MCP research](../../research/technical/11-MCP-GATEWAY-FOUNDATION.md)
- [Gateway contract](../02-Architecture/MCP-Gateway.md)
- [ADR-0019](ADR-0019-sanctum-stateful-first-party-auth.md)
