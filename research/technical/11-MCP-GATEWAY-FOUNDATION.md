---
title: MCP Gateway Foundation research
status: EVIDENCE_COLLECTED
owner: project
created: 2026-09-25
updated: 2026-09-26
tags: [research, mcp, openai, authentication]
related: [../../docs/03-ADR/ADR-0020-inbound-mcp-gateway.md]
---

# MCP Gateway Foundation — current evidence

Research date: 2026-09-25 (Europe/Moscow). Question: how can CVortex expose bounded user-specific read and reversible draft tools to external MCP clients without replacing the existing Responses API or weakening owner isolation and human approval? Recheck by 2026-10-25 or before any ChatGPT/tunnel deployment, whichever comes first. Product availability and pricing can change sooner.

## OpenAI capability and billing

| Question | Finding as of research date | Confidence |
|---|---|---|
| Developer Mode plans | OpenAI documents Pro, Plus, Business, Enterprise and Education eligibility **on the web**. Workspace permissions and the individual account setting still matter. Current CVortex owner's account entitlement was not inspected. | High for documented plans; UNKNOWN for this account |
| Custom MCP read/write | Developer Mode documents full MCP tool access, read and write; remote server connection supports streaming HTTP/SSE and OAuth/no auth/mixed auth. Search/fetch are tool conventions, not a separately proven plan entitlement. Write actions can require ChatGPT confirmation. Apps SDK availability alone is not evidence of connection availability. | High for documented feature; UNKNOWN for this account |
| Mobile/desktop | The cited Developer Mode instructions state web eligibility. They do not establish this workflow on mobile or desktop, so those surfaces are UNKNOWN. | High |
| ChatGPT subscription vs API | The existing CVortex `OPENAI_API_KEY` outbound Responses path consumes Platform API resources; a ChatGPT subscription does not by itself fund that API path. No official source inspected gives a general zero-API-cost guarantee for ChatGPT → private MCP. | High for separation; UNKNOWN for MCP token allocation |
| Secure MCP Tunnel | Requires Platform `tunnel_id`, runtime API key, Tunnel Read/Use (and Manage for creation), association with the ChatGPT workspace/Platform organization, outbound HTTPS, and local server reachability. The tunnel is transport; it does not automatically publish CVortex OAuth authorization endpoints. | High |
| Tunnel charges, funded account, credit balance | Official tunnel guide specifies key and permissions but does not establish the price, whether a funded Platform account or positive credit balance is required, or whether a ChatGPT-origin tool call is charged for model tokens via Platform. **UNKNOWN**. Responses API calls through the tunnel use the Platform API path. | Unknown |

Sources: [ChatGPT Developer Mode](https://developers.openai.com/api/docs/guides/developer-mode), [OpenAI ChatGPT developer overview](https://developers.openai.com/chatgpt), [Secure MCP Tunnel](https://developers.openai.com/api/docs/guides/secure-mcp-tunnels), [ChatGPT/API billing separation](https://help.openai.com/en/articles/9039756-managing-billing-for-chatgpt-and-the-api-platform), [OpenAI API pricing](https://developers.openai.com/api/docs/pricing).

## MCP and authentication

The current stable [MCP 2026-07-28 specification](https://modelcontextprotocol.io/specification/2026-07-28) has a stateless `server/discover` path; older clients use `initialize`. HTTP clients send JSON-RPC over Streamable HTTP, discover explicit tools and schemas, and receive bounded tool errors. Tool annotations inform clients but cannot enforce CVortex approval. Laravel MCP 1.0.1 supports the current revision and legacy initialization. CVortex retains both protocol paths; future contract changes should version names or endpoint when incompatible.

For user-specific ChatGPT data, [OpenAI's OAuth guidance](https://developers.openai.com/plugins/build/auth) calls for OAuth 2.1 style authorization code + PKCE S256, protected-resource and authorization-server metadata, scope and per-tool security schemes, and token issuer/audience/expiry checks. ChatGPT supports CIMD, DCR and predefined clients; the current Laravel MCP + Passport route uses DCR, not CIMD. The Laravel package exposes metadata and S256 but Passport's standard token path has **not** been verified to echo OAuth `resource` into a CVortex MCP audience claim and enforce that audience. This is an explicit ChatGPT connection gate, not a passing E2E claim. The local Inspector bearer-token proof does not exercise ChatGPT OAuth.

Source spot-check on 2026-09-26 confirms the documented Developer Mode plan/read/write capability, OAuth `resource` requirement and tunnel prerequisites above. This is documentation evidence, not validation of the current account. The configured DCR allowlist accepts ChatGPT's callback-ID-specific URI; the stable redirect URI and complete authorization-code flow remain unvalidated.

Sources: [MCP transport](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports), [MCP authorization](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization), [OpenAI OAuth requirements](https://developers.openai.com/plugins/build/auth), [Laravel MCP](https://laravel.com/docs/13.x/mcp), [Laravel Passport](https://laravel.com/docs/13.x/passport).

## Library options

| Option | Evidence and trade-off | Decision |
|---|---|---|
| `laravel/mcp` 1.0.1 + Passport 13.8 | Official Laravel package, MIT, Streamable HTTP, modern/legacy protocol, tool schema/annotations, Laravel middleware/testing, OAuth registration support. Passport adds migrations/keys and needs explicit audience review before ChatGPT. | Selected for bounded adapter |
| Official MCP PHP SDK | [Official SDK](https://github.com/modelcontextprotocol/php-sdk) supports HTTP/stdio but remains experimental/pre-major and needs Laravel auth/routing integration. | Deferred |
| Handwritten JSON-RPC | Small initial surface, but transport/version/OAuth correctness is costly to maintain. | Rejected |

## Deployment options

| Path | Security and local-first fit | Infrastructure, cost, reversibility |
|---|---|---|
| A — public HTTPS | Enables external clients after OAuth compliance and ingress hardening; expands exposure of user-private tools. | TLS/domain/WAF/monitoring and operational cost; reversible by disabling MCP and ingress. Not selected now. |
| B — Secure MCP Tunnel | Keeps Laravel private and uses outbound HTTPS; attractive for local-first. OAuth authorization endpoints and `resource` audience still need a reachable, compliant design. | Platform tunnel/key/permissions, workspace association and unknown billing; tunnel runtime outside Laravel. Deferred. |
| C — local protocol client | Loopback server + Inspector with local OAuth bearer token; no public ingress or ChatGPT entitlement needed. | Minimal infrastructure/cost, easy rollback by `MCP_ENABLED=false`. Selected for foundation validation. |

## Decision gate

| Gate | State | Reason |
|---|---|---|
| MCP protocol implementation | SUPPORTED | Laravel MCP implements current/legacy protocol; Inspector proof required separately. |
| ChatGPT read integration | PARTIALLY_SUPPORTED | Documented platform capability; account connection, public/tunnel reachability and audience flow unverified. |
| ChatGPT write integration | PARTIALLY_SUPPORTED | Documented Developer Mode capability; CVortex draft tool exists, account/E2E and OAuth audience unverified. |
| Current account/plan | UNKNOWN | No account entitlement or workspace setting inspected. Plus eligibility is documented, not proof of this account. |
| Secure MCP Tunnel | PARTIALLY_SUPPORTED | Product capability documented; this workspace's Platform permissions, funded-account/credit requirements and OAuth reachability unknown. |
| Billing independence from Responses API | NOT_SUPPORTED_CURRENTLY | CVortex draft Truth Guard still invokes the configured `LlmProvider`; unconfigured/unfunded outbound validation fails closed. ChatGPT inference billing via MCP remains UNKNOWN. |

Architecture impact: keep inbound MCP as a separate, explicitly enabled adapter over existing services. Keep `OpenAiResponsesProvider` and `OPENAI_API_KEY`. Do not deploy a ChatGPT connection until OAuth resource/audience compliance and account/tunnel facts are tested with the target account. Employer Memory/Consistency does not exist before M4, so the foundation neither exposes nor invents it.
