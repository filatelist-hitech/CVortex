---
title: MCP Gateway — current product and protocol research
status: verified-current-docs
owner: project
created: 2026-09-25
updated: 2026-09-27
tags: [mcp, oauth, chatgpt, tunnel, security]
related: [../../docs/03-ADR/ADR-0021-inbound-mcp-read-only.md, ../../docs/02-Architecture/MCP-Gateway.md]
---

# MCP Gateway — current product and protocol research

Research verified: 2026-09-27. Recheck before external deployment or by 2026-10-27 because ChatGPT features, account controls and tunnel requirements can change. Evidence below is product documentation, not proof of the current user's plan or workspace permissions.

## ChatGPT capability and entitlement

| Question | Current official documentation | CVortex conclusion |
|---|---|---|
| Developer Mode eligibility | The Developer Mode guide lists Pro, Plus, Business, Enterprise and Education on ChatGPT web. It places the personal toggle at **Settings → Security and login → Developer mode**. | Documentation lists Plus, but does not prove entitlement on a particular account or workspace. |
| Read-only custom MCP | The Help Center says Pro users can connect MCP with read/fetch permissions. | Pro is documented for read/fetch. Account UI still needs inspection. |
| Full MCP / writes | The Help Center says full MCP, including write actions, is rolling out to Business, Enterprise and Edu; workspace admins/owners enable apps, and Enterprise/Edu can gate access with RBAC. | Those plan/workspace details concern broader capabilities; CVortex intentionally exposes only reads. |
| Plus plan detail | The Developer Mode guide lists Plus as eligible, while the newer plan-specific Help Center FAQ omits Plus and only answers Pro and Business/Enterprise/Edu. | **Official-source ambiguity:** Plus read-only custom MCP entitlement is not conclusively described by the plan-specific article. Verify the actual UI; do not infer it from subscription name. |
| App creation and protocols | On web, enable Developer Mode, open ChatGPT Plugins, press `+`, create an app for the remote MCP server, configure the endpoint/authentication, then inspect/refresh tool metadata. The guide lists SSE and streaming HTTP with OAuth, no-auth or mixed authentication. | Use ChatGPT web and the custom MCP app path. CVortex uses Streamable HTTP. |
| Mobile | Help Center says custom MCP apps are web-only. | The real ChatGPT validation path must use web. |

Official sources (retrieved 2026-09-27): [Developer Mode guide](https://developers.openai.com/api/docs/guides/developer-mode), [Developer Mode and MCP apps in ChatGPT](https://help.openai.com/en/articles/12584461-developer-mode-and-mcp-apps-in-chatgpt), [Connect and test a plugin](https://developers.openai.com/plugins/deploy/connect-chatgpt).

## OAuth, resource and transport

OpenAI's current MCP authentication guide requires protected-resource metadata and authorization-server metadata, expects `resource` on both authorization and token requests, and requires the authorization server to echo the resource into the access token (commonly as `aud`) so the resource server can check it. It describes CIMD, DCR and predefined OAuth clients. The callback is `https://chatgpt.com/connector/oauth/{callback_id}` when the authorization server does not meet the issuer-identification requirements; servers that advertise issuer support and return `iss` use `https://chatgpt.com/connector_platform_oauth_redirect`. The same issuer response rule applies to successful and error redirects.

The current [MCP authorization specification](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization) requires clients to include the canonical MCP resource URI in authorization and token requests, and specifies exact issuer comparison when RFC 9207 `iss` is supported. It defines path-aware protected-resource metadata lookup and root fallback. The [MCP transport specification](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports) defines Streamable HTTP. CVortex serves the same protected-resource document at the root, `/mcp` (used by the current official tunnel-client discovery contract), and the canonical `/mcp/v1` path; all return the canonical `/mcp/v1` resource URI. CVortex issues an `mcp:use` token with issuer and resource claims, requires the exact resource at the OAuth endpoints, and checks issuer/resource after Passport token validation at `/mcp/v1`.

Official sources (retrieved 2026-09-27): [OpenAI MCP authentication](https://developers.openai.com/plugins/build/auth), [MCP authorization 2026-07-28](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization), [MCP transports 2026-07-28](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports), [RFC 8252 native app redirects](https://www.rfc-editor.org/rfc/rfc8252.html), [RFC 9700 OAuth security BCP](https://www.rfc-editor.org/rfc/rfc9700.html).

CVortex's redirect policy accepts exact ChatGPT stable/callback-ID HTTPS callback forms and native loopback callbacks at `/oauth/callback` with a valid explicit dynamic port. It rejects userinfo, host confusion, descendants/traversal, encoded paths, query/fragment and malformed URIs. It parses URI components; it does not use string-prefix trust.

## Secure MCP Tunnel

The current official `tunnel-client` v0.0.15 doctor contract checks `/.well-known/oauth-protected-resource/mcp`; its exact release source was inspected on 2026-09-27: [OAuth discovery probe](https://github.com/openai/tunnel-client/blob/v0.0.15/cmd/client/doctor_command.go) and [v0.0.15 release](https://github.com/openai/tunnel-client/releases/tag/v0.0.15). MCP's current [Protected Resource Metadata discovery rules](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization/authorization-server-discovery), also checked on 2026-09-27, require path-specific and root-fallback discovery. CVortex serves all three paths while each document names the canonical `/mcp/v1` resource.

OpenAI describes Secure MCP Tunnel as an **outbound-only** connection from a private host to an OpenAI-hosted MCP endpoint. `tunnel-client` needs outbound HTTPS and local reachability to the private MCP server; it does not require inbound Internet access. The local server is not thereby made public. The tunnel carries MCP transport, while OAuth issuer discovery and authorization must still resolve to reachable, correctly configured endpoints; tunnel setup alone does not make a local OAuth server reachable from ChatGPT.

OpenAI's current guide says MCP traffic and OAuth discovery can travel through the tunnel path; the upstream authorization-server metadata is preserved. The tunnel does not provision or automatically tunnel the authorization server itself, so its issuer, authorization and token endpoints must remain reachable for the browser-based OAuth flow. The official v0.0.15 macOS arm64 release was downloaded from `openai/tunnel-client`; its ZIP SHA-256 matched the release `SHA256SUMS.txt`, and `tunnel-client help quickstart` ran successfully. Its OAuth doctor contract checks `/.well-known/oauth-protected-resource/mcp` and follows `authorization_servers[0]` to `/.well-known/oauth-authorization-server`; CVortex additionally implements the spec's root fallback and its exact `/mcp/v1` path. No profile was initialized and no daemon was started. The CLI identifies `CONTROL_PLANE_TUNNEL_ID` as the selected tunnel and `CONTROL_PLANE_API_KEY` as the runtime key used by `doctor`/`run`. The runtime key must live in the user's secret manager/process environment and must never be committed in `.env`, logged or pasted into a repository. `OPENAI_ADMIN_KEY` is separate and only needed for tunnel CRUD; do not give it to the daemon. Runtime use requires Tunnels Read + Use; tunnel CRUD requires Read + Manage. The ChatGPT app still needs separate Developer Mode/workspace permission.

Current source: [Secure MCP Tunnel](https://developers.openai.com/api/docs/guides/secure-mcp-tunnels), retrieved 2026-09-27. The documentation inspected here does not establish billing, a required credit balance or API token charges for ChatGPT tool calls; those remain **UNKNOWN** until Platform account facts are checked.

## CVortex architecture and validation gates

The accepted [ADR-0021](../../docs/03-ADR/ADR-0021-inbound-mcp-read-only.md) defines exactly two inbound MCP tools: `vacancy_get` and `application_context_get`. MCP reads are independent of outbound `LlmProvider` and `OPENAI_API_KEY`. Draft creation, Truth Guard and Human Approval remain first-party CVortex workflows. Do not merge inbound MCP into the outbound Responses API path.

| Gate | Evidence/status on 2026-09-27 |
|---|---|
| Official product/protocol contract | Rechecked above from official OpenAI and MCP docs |
| Account entitlement and workspace role | **Not established:** ChatGPT sidebar displayed Plus, but Developer Mode settings/app creation were not reached. The official guide lists Plus while the newer plan-specific Help Center article omits it from read/fetch eligibility; do not infer access from the plan label. |
| Secure MCP Tunnel ID, runtime credential and permissions | **Blocked on Platform access:** Platform UI was at sign-in; no tunnel ID or runtime secret was available. The official CLI was checksum-verified and help-tested from a temporary download, but no profile or daemon was configured. |
| Local automated MCP/OAuth checks | See [validation evidence](../../docs/10-Operations/MCP-Gateway-Validation.md) |
| Full Inspector login/consent/token/discovery against the current running local stack | **Not repeated in this pass:** an existing stored OAuth session completed live Streamable HTTP discovery and both read calls. A fresh DCR/login/consent/token exchange needs an authenticated local CVortex browser session; the current local page showed sign-in. |
| Real ChatGPT discovery and tool calls | **Blocked externally:** Developer Mode/app setup was not reached, and no authenticated Platform session or Secure MCP Tunnel profile/ID/process credential was available. No ChatGPT read or negative-mutation prompt was executed. |

This is not a public deployment authorization. Do not open router/firewall ports or add a public reverse proxy as a tunnel substitute. If Secure MCP Tunnel is unavailable, stop before external publication and obtain an explicit security/deployment decision.

## Implementation library

`laravel/mcp` provides Laravel Streamable HTTP routes, tool schemas/annotations and protocol testing. Passport provides OAuth routes and token validation; CVortex adds resource/issuer enforcement, strict redirect validation and bounded owner-scoped tools. Keep protocol/auth integration code separate from outbound provider abstractions and domain services.
