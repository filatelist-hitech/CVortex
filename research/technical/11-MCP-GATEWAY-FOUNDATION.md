---
title: MCP Gateway — current product and protocol research
status: verified-current-docs
owner: project
created: 2026-09-25
updated: 2026-10-03
tags: [mcp, oauth, chatgpt, tunnel, security]
related: [../../docs/03-ADR/ADR-0021-inbound-mcp-read-only.md, ../../docs/02-Architecture/MCP-Gateway.md]
---

# MCP Gateway — current product and protocol research

Research rechecked: 2026-10-03. Recheck before external deployment or by 2026-11-03 because ChatGPT features, account controls and tunnel requirements can change. The dated findings below distinguish official product documentation from what was visible in the user's account; UI visibility does not prove a successful connection or tool call.

## ChatGPT capability and entitlement

| Question | Current official documentation | CVortex conclusion |
|---|---|---|
| Developer Mode eligibility | The official Developer Mode guide lists Pro, Plus, Business, Enterprise and Education on ChatGPT web, with **Settings → Security and login → Developer mode** as the personal toggle. The plan-specific Help Center FAQ documents Pro read/fetch and Business/Enterprise/Edu full MCP, but omits Plus. | **Official-source conflict remains for Plus read-only entitlement.** The authenticated Plus web account displayed the custom MCP form; entering the existing Platform tunnel ID resolved to its `Tunnel` record. This proves the setup UI and tunnel record are available to this account, not that app creation, tool discovery or calls succeed. The Developer Mode setting itself was not verified. |
| Read-only custom MCP | The Help Center says Pro users can connect MCP with read/fetch permissions. The Developer Mode guide lists Plus eligibility. | Plus entitlement is unresolved despite the visible form. Do not infer support from the Plus label alone. |
| Full MCP / writes | The Help Center describes full MCP, including write actions, for Business, Enterprise and Edu; workspace admins/owners govern access and Enterprise/Edu can gate it with RBAC. | These broader capability details do not change CVortex's accepted exactly-two-read-tools boundary. |
| ChatGPT app creation and protocols | Current route is ChatGPT web → Plugins → `+` → create a developer-mode app. OpenAI documents SSE and streaming HTTP, OAuth/no-auth/mixed auth, and a `Tunnel` connection mode for private MCP. | The current account showed the form and resolved the existing tunnel. A CVortex form draft was populated but not submitted; no risk acknowledgment was accepted, no app was created and no tools were called. |
| Local server and surface availability | The Help Center says ChatGPT does not connect directly to a local MCP server and describes custom MCP apps as web-only. It directs private/developer-machine servers to Secure MCP Tunnel. | Direct `http://localhost:8080/mcp/v1` integration is unsupported by the documented ChatGPT path. ChatGPT web on the Mac is the documented app-creation/test surface. Native ChatGPT Desktop tool invocation was not verified. |
| ChatGPT Desktop plugin package | OpenAI's current portable plugin format uses root `plugin.json` plus `skills/`. A repo marketplace at `$REPO_ROOT/.agents/plugins/marketplace.json` can expose plugins in the ChatGPT desktop app's Plugins Directory after restart; the docs scope this flow to Work mode or Codex in ChatGPT desktop. | A local marketplace and skill package can distribute CVortex guidance. It does not create the ChatGPT MCP connection. The OpenAI app binding needs the real `plugin_asdk_app...` ID from a ChatGPT-created app; no ID was available, so none is fabricated. |

Official sources (retrieved 2026-10-03): [Developer Mode guide](https://developers.openai.com/api/docs/guides/developer-mode), [Developer Mode and MCP apps in ChatGPT](https://help.openai.com/en/articles/12584461-developer-mode-and-mcp-apps-in-chatgpt), [Secure MCP Tunnel](https://developers.openai.com/api/docs/guides/secure-mcp-tunnels), [Package your plugin](https://developers.openai.com/plugins/build/plugins), [Connect and test a plugin](https://developers.openai.com/plugins/deploy/connect-chatgpt).

## OAuth, resource and transport

OpenAI's current MCP authentication guide requires protected-resource metadata and authorization-server metadata, expects `resource` on both authorization and token requests, and requires the authorization server to echo the resource into the access token (commonly as `aud`) so the resource server can check it. It describes CIMD, DCR and predefined OAuth clients. The callback is `https://chatgpt.com/connector/oauth/{callback_id}` when the authorization server does not meet the issuer-identification requirements; servers that advertise issuer support and return `iss` use `https://chatgpt.com/connector_platform_oauth_redirect`. The same issuer response rule applies to successful and error redirects.

The current [MCP authorization specification](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization) requires clients to include the canonical MCP resource URI in authorization and token requests, and specifies exact issuer comparison when RFC 9207 `iss` is supported. It defines path-aware protected-resource metadata lookup and root fallback. The [MCP transport specification](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports) defines Streamable HTTP. CVortex serves the same protected-resource document at the root, `/mcp` (used by the current official tunnel-client discovery contract), and the canonical `/mcp/v1` path; all return the configured resource URI (direct local `/mcp/v1` unless a trusted deployment override is configured). CVortex issues an `mcp:use` token with issuer and resource claims, requires the exact resource at the OAuth endpoints, and checks issuer/resource after Passport token validation at `/mcp/v1`.

Official sources (retrieved 2026-09-27): [OpenAI MCP authentication](https://developers.openai.com/plugins/build/auth), [MCP authorization 2026-07-28](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization), [MCP transports 2026-07-28](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports), [RFC 8252 native app redirects](https://www.rfc-editor.org/rfc/rfc8252.html), [RFC 9700 OAuth security BCP](https://www.rfc-editor.org/rfc/rfc9700.html).

CVortex's redirect policy accepts exact ChatGPT stable/callback-ID HTTPS callback forms and native loopback callbacks at `/oauth/callback` with a valid explicit dynamic port. It rejects userinfo, host confusion, descendants/traversal, encoded paths, query/fragment and malformed URIs. It parses URI components; it does not use string-prefix trust.

## Secure MCP Tunnel

The current official `tunnel-client` v0.0.15 doctor contract checks `/.well-known/oauth-protected-resource/mcp`; its exact release source was inspected on 2026-09-27: [OAuth discovery probe](https://github.com/openai/tunnel-client/blob/v0.0.15/cmd/client/doctor_command.go) and [v0.0.15 release](https://github.com/openai/tunnel-client/releases/tag/v0.0.15). MCP's current [Protected Resource Metadata discovery rules](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization/authorization-server-discovery), also checked on 2026-09-27, require path-specific and root-fallback discovery. CVortex serves all three paths while each document names the canonical `/mcp/v1` resource.

OpenAI describes Secure MCP Tunnel as an **outbound-only** connection from a private host to an OpenAI-hosted MCP endpoint. `tunnel-client` needs outbound HTTPS and local reachability to the private MCP server; it does not require inbound Internet access. The local server is not thereby made public. Current `openai/tunnel-client` documentation says the tunnel service rewrites protected-resource metadata/resource URLs for the tunnel. OAuth discovery and some registered OAuth endpoints can use the tunnel path, but the authorization endpoint itself is not automatically tunneled: the browser-facing authorization URL must be reachable, and the OAuth flow can still fail if required endpoints cannot be reached from the browser or tunnel client.

OpenAI's guide requires a `tunnel_id`, a runtime API key for `tunnel-client`, and a locally reachable MCP endpoint. The current tunnel permission model separates **Tunnels Read + Use** (run/select) from **Tunnels Read + Manage** (create/edit); tunnel association must include the target ChatGPT workspace as well as the Platform organization. The app creator also needs the separate ChatGPT Developer Mode permission where workspace policy applies. `CONTROL_PLANE_API_KEY` is only for the local tunnel process; it is not CVortex's `OPENAI_API_KEY`, must never enter the repository `.env`, logs or chat, and must remain in a local secret manager/process environment. `OPENAI_ADMIN_KEY` is only for tunnel CRUD and must not be given to the daemon. The latest tunnel-client release and current commands must be taken from the [official project](https://github.com/openai/tunnel-client); do not pin the runbook to the previously inspected v0.0.15 binary.

Current primary sources (retrieved 2026-10-03): [Secure MCP Tunnel](https://developers.openai.com/api/docs/guides/secure-mcp-tunnels), [OpenAI tunnel-client architecture](https://github.com/openai/tunnel-client/blob/master/docs/architecture.md), [tunnel-client configuration](https://github.com/openai/tunnel-client/blob/master/docs/configuration.md). On this date GitHub's latest release was v0.0.15. The macOS arm64 archive SHA-256 matched the published asset digest, and `tunnel-client help quickstart` ran. A local profile was generated with an environment-variable reference only; `doctor --explain` stopped because `CONTROL_PLANE_API_KEY` is unset. No tunnel daemon or ChatGPT app connection was started.

Secure MCP Tunnel is the documented private path; it carries MCP traffic through OpenAI infrastructure, so prompts/tool arguments/results and OAuth artifacts on applicable paths do not remain local-only. The documentation does not establish billing, a required credit balance or API token charges for ChatGPT app calls; those remain **UNKNOWN**. ChatGPT subscription usage and the Platform runtime key for `tunnel-client` are separate credentials and concepts.

## CVortex architecture and validation gates

The accepted [ADR-0021](../../docs/03-ADR/ADR-0021-inbound-mcp-read-only.md) defines exactly two inbound MCP tools: `vacancy_get` and `application_context_get`. MCP reads are independent of outbound `LlmProvider` and `OPENAI_API_KEY`. Draft creation, Truth Guard and Human Approval remain first-party CVortex workflows. Do not merge inbound MCP into the outbound Responses API path.

| Gate | Evidence/status on 2026-09-27 |
|---|---|
| Official product/protocol contract | Rechecked above from official OpenAI and MCP docs |
| Account entitlement and workspace role | **UNRESOLVED:** the Plus account can open the custom-MCP form and resolve the existing Platform tunnel. No app was created, no Developer Mode setting was confirmed and no MCP call was made. Official sources still conflict on Plus read-only entitlement. |
| Secure MCP Tunnel ID, runtime credential and permissions | A Platform tunnel record exists and lists a ChatGPT workspace; the ChatGPT form resolves that tunnel. OpenAI `tunnel-client` v0.0.15 was downloaded from the official release and its SHA-256 matched GitHub's published asset digest. A local `cvortex-chatgpt` profile was created with an environment-variable reference only. `doctor --explain` fails because `CONTROL_PLANE_API_KEY` is unset; no daemon is running. Runtime use permissions and successful control-plane authentication remain unverified. |
| Local automated MCP/OAuth checks | See [validation evidence](../../docs/10-Operations/MCP-Gateway-Validation.md) |
| Full Inspector login/consent/token/discovery against the current running local stack | **Not repeated in this pass:** an existing stored OAuth session completed live Streamable HTTP discovery and both read calls. A fresh DCR/login/consent/token exchange needs an authenticated local CVortex browser session; the current local page showed sign-in. |
| Real ChatGPT discovery and tool calls | **BLOCKED_EXTERNAL:** the app form resolves the existing tunnel, but the runtime key is absent and `tunnel-client` is not running. The app draft is not submitted. No ChatGPT tool discovery, read, cover-letter generation or negative-mutation prompt was executed. Native ChatGPT Desktop use is also unverified; official Help Center currently describes custom MCP apps as web-only. |

This is not a public deployment authorization. Do not open router/firewall ports or add a public reverse proxy as a tunnel substitute. If Secure MCP Tunnel is unavailable, stop before external publication and obtain an explicit security/deployment decision.

## Implementation library

`laravel/mcp` provides Laravel Streamable HTTP routes, tool schemas/annotations and protocol testing. Passport provides OAuth routes and token validation; CVortex adds resource/issuer enforcement, strict redirect validation and bounded owner-scoped tools. Keep protocol/auth integration code separate from outbound provider abstractions and domain services.

### Local origin correction and Harpoon evidence — 2026-10-03

Verified installed official client v0.0.15 (`a390c168ff1b2d14e73a95991c186c6aba3ff5a0`) help and [matching upstream configuration](https://github.com/openai/tunnel-client/blob/a390c168ff1b2d14e73a95991c186c6aba3ff5a0/docs/configuration.md#harpoon-mcp-outbound-http-allowlist): OAuth-discovered PRMD targets follow Harpoon plaintext policy, default false. Trusted loopback HTTP requires `--harpoon.allow-plaintext-http`; no extra localhost trusted origin is needed when local target and issuer/endpoints derive from `APP_URL=http://127.0.0.1:8080`. Confidence: high for this installed version; recheck on upgrade.

User-provided evidence reports `dispatcher forwarded command` on `harpoon` and `oauth-prmd-source-0` HTTP 200, while ChatGPT still reports inability to determine OAuth settings. These observations predate the local origin correction and do not prove an upstream defect. After successful local validation, a fresh ChatGPT discovery attempt is manual external E2E; do not weaken OAuth or introduce undocumented workarounds.


### Tunnel resource contract verified — 2026-10-03

Official version-pinned [architecture contract](https://github.com/openai/tunnel-client/blob/a390c168ff1b2d14e73a95991c186c6aba3ff5a0/docs/architecture.md#oauth-protected-mcp) states that PRMD resource/WWW-Authenticate metadata URLs are rewritten to tunnel-service endpoints for the selected tunnel; bearer headers are forwarded, browser authorization_endpoint is not rewritten, and registered token/DCR endpoints are shimmed. Confidence high for installed release; recheck on upgrade.

User evidence after origin correction: DCR HTTP 201, direct browser authorize, and `resource` equal to the selected tunnel-service MCP identifier rather than the local transport URL. CVortex exact-resource middleware therefore returned invalid_target. Local defect: deployment had not set existing `MCP_RESOURCE_URL` for this external resource identity. Configure that one exact non-secret value locally; do not hardcode internal OpenAI hostnames or real tunnel IDs in source or derive trust from the request. Issuer remains local; no OAuth middleware changes or broad resource aliases are needed. Structural full-flow regression covers DCR, consent, S256 code exchange, refresh and both MCP reads with this split-origin configuration. Real ChatGPT completion still requires the user's session/consent and observed tool calls; this is not evidence of an upstream defect.
