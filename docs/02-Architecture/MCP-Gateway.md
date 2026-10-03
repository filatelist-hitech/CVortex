---
title: CVortex MCP Gateway
status: active
owner: project
created: 2026-09-25
updated: 2026-10-03
tags: [architecture, mcp, ai, security]
related: [../03-ADR/ADR-0021-inbound-mcp-read-only.md, ../08-Security/Threat-Model.md]
---

# CVortex MCP Gateway

## Product boundary

The inbound MCP gateway is **read-only**. Its surface contains exactly two tools:

1. `vacancy_get`
2. `application_context_get`

An external MCP client can read bounded context for the authenticated CVortex user. It cannot create or change product data. Draft preparation, Truth Guard, Human Approval, Career Fact review and application workflows remain available through CVortex-owned web/API paths.

```mermaid
flowchart LR
    Client[ChatGPT web MCP app] --> Tunnel[OpenAI Secure MCP Tunnel]
    TunnelClient[tunnel-client on local host] -->|outbound HTTPS| Tunnel
    TunnelClient -->|local HTTP| Gateway[CVortex MCP Gateway]
    Client --> OAuth[OAuth 2.1 + PKCE]
    OAuth --> Gateway
    Gateway --> Auth[Authenticated active user]
    Auth --> Tools[vacancy_get / application_context_get]
    Tools --> Adapter[Owner-scoped application services]
    Adapter --> RLS[(PostgreSQL / RLS)]
    CV[CVortex-owned web/API workflows] --> Draft[Application Draft + Human Approval]
    CV --> LLM[LlmProvider / Responses API]
```

Inbound MCP and outbound model execution remain separate. The existing `LlmProvider`, `OpenAiResponsesProvider`, ModelPolicy, runtime Skills and `OPENAI_API_KEY` path are unchanged. Neither MCP initialization nor either read tool calls an LLM provider.

## Authentication and deployment

`MCP_ENABLED=false` is the default. When enabled, `/mcp/v1` uses Streamable HTTP and Passport OAuth bearer tokens with `mcp:use`. The server derives the owner from the validated active user; caller-supplied identity never authorizes access. PostgreSQL owner context and owner-scoped queries both apply. Browser cookies do not authenticate MCP requests.

The authorization server requires the exact canonical OAuth `resource` on authorization and token requests, places that resource in the issued access token and checks it again on MCP requests. The access token issuer must match the configured authorization-server issuer. OAuth metadata advertises issuer response support; successful and error authorization redirects include the issuer when the callback passes the same strict URI policy. DCR accepts the documented ChatGPT stable and callback-ID redirect forms plus exact native loopback callbacks with dynamic ports. It rejects host confusion, userinfo, path traversal, encoded paths, query/fragment and malformed URIs.

The protected-resource challenge URL is derived from the canonical `MCP_RESOURCE_URL`, not the inbound Host or forwarded-proto headers. OpenAI documents Secure MCP Tunnel as the private path for local servers: the local `tunnel-client` initiates outbound HTTPS and forwards requests to the local MCP URL without opening an inbound port. Current OpenAI tunnel documentation says the service rewrites protected-resource discovery/resource URLs for the selected tunnel. Keep the local `APP_URL`/MCP resource consistent with the running server; set `MCP_RESOURCE_URL` or `MCP_AUTHORIZATION_SERVER_URL` only when the deployment has a deliberately different canonical resource or issuer. The OAuth authorization endpoint still has to be reachable by the browser; a tunnel does not automatically make every OAuth endpoint public or local.

The local stack binds Nginx to loopback. Public exposure is not enabled by this feature. Direct ChatGPT access to `localhost` is not supported by the current Help Center; Secure MCP Tunnel is the documented private path. Tunnel traffic traverses OpenAI's tunnel service, while the MCP server stays local. OAuth authorization reachability must be verified separately. See [current product research](../../research/technical/11-MCP-GATEWAY-FOUNDATION.md), [local setup guide](../10-Operations/ChatGPT-CVortex-Local-Setup.md) and [validation evidence](../10-Operations/MCP-Gateway-Validation.md).

## Tool contract

Every tool requires the same authenticated principal and `mcp:use`; both accept only one ULID vacancy ID. Schemas reject additional properties. Vacancy text is untrusted data, never server instructions.

| Tool | Effect | Input | Bounded output | Access and errors |
|---|---|---|---|---|
| `vacancy_get` | Read only | `vacancy_id` | ID, title, company, persisted analysis status, untrusted-data marker | One owned vacancy; `NOT_FOUND` for missing or foreign IDs |
| `application_context_get` | Read only | `vacancy_id` | Vacancy metadata/status; at most 50 requirements, 25 relevant confirmed claims, and 20 confirmed facts per claim; `context_truncated` indicates clipping | Same owner checks; no raw vacancy body, source excerpt, secrets or unrelated career records |

If analysis is pending, failed or otherwise not completed, the context tool returns bounded vacancy metadata, empty derived requirements/claims, and `untrusted_vacancy_data=true`. A completed analysis is selected for the current career signature; only relevant passing claims backed by confirmed Career Facts are returned. Missing/stale derived analysis fails safely rather than broadening access.

No MCP capability can create or modify drafts, approve content, confirm/reject/update Career Facts, change application state, send an application or recruiter message, update vacancies, fetch arbitrary URLs, access files, invoke SQL/shell, read environment/secrets or act as a generic service proxy. MCP requests do not enqueue product jobs or write product rows.

## Errors, logs and limits

Missing/invalid bearer tokens receive a safe 401 response and protected-resource challenge. Missing scope or inactive accounts are denied. Cross-owner resources are non-enumerating `NOT_FOUND`. Tool errors use stable codes; unexpected failures return a request ID and generic error without exception text, SQL or stack trace. Safe auth/error log entries exclude bearer/refresh tokens, secrets, prompt content and private career data. `APP_DEBUG=false` is required in production; local framework logs can include additional exception detail outside the MCP response boundary.

Discovery and authenticated reads are rate-limited. The MCP request path has no write rate bucket because there are no MCP write operations. See [local operations](../10-Operations/Local-Development.md) and [MCP validation](../10-Operations/MCP-Gateway-Validation.md) for tested results and the exact local environment contract.

### Canonical local OAuth origin — 2026-10-03

For local transport and OAuth endpoints, `APP_URL=http://127.0.0.1:8080` remains canonical. With empty overrides the direct local resource is `/mcp/v1`. For ChatGPT Secure MCP Tunnel, set the existing `MCP_RESOURCE_URL` locally to the exact resource identifier observed in the selected tunnel's OAuth request; keep `MCP_AUTHORIZATION_SERVER_URL` empty. Tunnel rewrites connector-facing PRMD resource URLs, while browser authorization stays direct. This separates transport location from token resource identity; it does not accept arbitrary origins or dynamically trust request input. The exact configured resource is required on authorize/token/refresh requests, embedded in signed access tokens and checked after Passport validation. Passport client audience and issuer validation remain unchanged. The challenge metadata URI is derived from the configured resource and rewritten/provided by the tunnel discovery path; no URL fetching is added. Real tunnel IDs/URLs belong only in ignored deployment configuration. Switching resource invalidates use of tokens bound to the old identifier. Existing localhost and 127.0.0.1 are not interchangeable for issuer/endpoints.

[Installed official client OAuth contract](https://github.com/openai/tunnel-client/blob/a390c168ff1b2d14e73a95991c186c6aba3ff5a0/docs/architecture.md#oauth-protected-mcp).
