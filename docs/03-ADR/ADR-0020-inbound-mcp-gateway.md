---
title: ADR-0020 — Inbound MCP Gateway as a bounded access channel
status: superseded
decision_nature: DERIVED_ARCHITECTURAL_DECISION
owner: project
created: 2026-09-25
updated: 2026-09-27
tags: [architecture, mcp, authentication, ai]
related: [ADR-0007, ADR-0009, ADR-0010, ADR-0019, ADR-0021]
---

# ADR-0020 — Inbound MCP Gateway as a bounded access channel

## Context

CVortex has a provider-independent outbound `LlmProvider` and an existing Responses API implementation. External AI clients need a narrow, user-specific way to read context and propose drafts. ADR-0019 fixes stateful Sanctum authentication for the first-party web client; it does not define third-party MCP authentication. Truth Guard, owner isolation and CVortex Human Approval remain mandatory.

## Decision

> **Superseded on 2026-09-27 by [ADR-0021](ADR-0021-inbound-mcp-read-only.md).** The following records the original foundation decision; it no longer defines the active MCP surface.

- Add a separate inbound MCP adapter in Laravel, disabled by default (`MCP_ENABLED=false`), registered through an explicit tool allowlist. Keep the outbound Responses API path intact.
- Use `laravel/mcp` for Streamable HTTP protocol and Passport for OAuth bearer credentials. Derive the owner from the validated token, require `mcp:use`, active account state, per-owner queries and PostgreSQL owner context. Continue Sanctum sessions for the first-party web UI.
- The original decision also contemplated an MCP draft-write path. ADR-0021 removes all MCP mutation capabilities while preserving first-party Application Draft workflows.
- Choose local Inspector/loopback validation now (deployment option C). Public HTTPS or Secure MCP Tunnel requires a separate connection gate: reachability, OAuth resource/audience compliance, current account entitlement, security review and operational/billing verification. Tunnel is infrastructure, never a domain component.

## Consequences and limits

The gateway is multi-user compatible without selecting a permanent LLM provider. It adds Passport tables, local signing keys and a new authorization boundary. The original draft-validation consequence no longer applies to inbound MCP reads. `EmployerConsistencyCheck`/Employer Memory is deferred because the M4 domain service is absent. ChatGPT OAuth E2E and account-specific entitlement require current validation; this ADR makes no claim about them.

## Alternatives considered

- Convert MCP into another `LlmProvider`: rejected because inbound tool access and outbound model execution have different contracts.
- Expose REST endpoints automatically as MCP tools: rejected because it bypasses deliberate exposure review.
- Use shared static bearer token or session cookie for external clients: rejected for multi-user, OAuth and CSRF boundaries.
- Implement MCP transport manually: rejected in favor of the maintained Laravel integration.

## References

- [MCP research](../../research/technical/11-MCP-GATEWAY-FOUNDATION.md)
- [Gateway contract](../02-Architecture/MCP-Gateway.md)
- [ADR-0019](ADR-0019-sanctum-stateful-first-party-auth.md)
