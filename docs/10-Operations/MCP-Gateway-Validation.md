---
title: MCP Gateway read-only validation
status: verified-local-external-blocked
owner: project
created: 2026-09-25
updated: 2026-09-27
tags: [operations, mcp, validation, read-only]
related: [../02-Architecture/MCP-Gateway.md, ../03-ADR/ADR-0021-inbound-mcp-read-only.md]
---

# MCP Gateway read-only validation

## Contract

Inbound MCP is disabled by default and, when enabled, exposes exactly two read-only tools:

1. `vacancy_get`
2. `application_context_get`

Reads derive the user from a validated OAuth bearer, enforce ownership and PostgreSQL RLS, bound returned context and do not call the outbound `LlmProvider`. Application Draft, Truth Guard and Human Approval remain first-party CVortex workflows.

## Local validation — 2026-09-27

| Check | Result |
|---|---|
| `make test` | PASS: backend 215 tests / 1419 assertions / 7 PostgreSQL-only skips; frontend 16/16. Includes the 0/20 disabled/enabled route-toggle check. |
| `make lint` | PASS: Pint 162 files; PHPStan 102 files / 0 errors; ESLint; TypeScript. |
| `bash scripts/test-application-postgres-boundary.sh` | PASS: PostgreSQL owner/RLS checks, including MCP before/after snapshots; 3 tests / 55 assertions. Disposable test database only; the root Compose database and volumes were not reset or removed. |
| `bash scripts/test-mcp-gateway-toggle.sh` | PASS: `MCP_ENABLED=false` registers 0 MCP/OAuth routes; `true` registers and verifies the expected endpoints across 20 MCP/OAuth routes. |
| Current local discovery metadata | PASS: root, `/mcp`, and canonical `/mcp/v1` protected-resource metadata plus authorization-server metadata return HTTP 200 while enabled. |
| Unauthenticated `POST /mcp/v1` | PASS: HTTP 401 with a safe JSON error; no stack trace returned to the client. |
| MCP Inspector CLI, modern protocol era, stored OAuth session | PASS: live Streamable HTTP `tools/list` returned exactly the two tools above. Both advertise read-only, non-destructive and idempotent hints. |
| Inspector `vacancy_get` for the Preview UI record | PASS: same persisted owner-scoped vacancy ID; analysis status `FAILED`; bounded metadata returned and source text omitted. |
| Inspector `application_context_get` for that vacancy | PASS: safe incomplete-analysis result, no derived requirements/claims, no source excerpt, no error. |
| PostgreSQL product-state snapshot before/after both reads | PASS: serialized state hash was identical (`6714368272c9171dfe18ab171cfb1049b3a9bb75722b215b2d4e5426da98f271`). Automated coverage also snapshots product tables and verifies owner isolation. |
| `docker compose --env-file .env.example config --quiet` | PASS. |
| `git diff --check` | PASS at final pre-commit review. |

The live Inspector calls used the actual Preview-created vacancy `01m3fvhmxhp83kcz80ecrd0jwg`. Private vacancy title/company and OAuth credentials are intentionally omitted from this report. Its existing stored OAuth authorization was used for discovery and tool calls. A fresh DCR → login → consent → token run was not completed in this validation pass because the local CVortex browser session showed the sign-in page and no user credentials were available; do not treat stored-auth Inspector calls as proof of a fresh authorization flow.

Sanitized live Inspector response summary:

```json
{
  "tools": ["vacancy_get", "application_context_get"],
  "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true},
  "vacancy_get": {
    "isError": false,
    "id": "01m3fvhmxhp83kcz80ecrd0jwg",
    "analysis_status": "FAILED",
    "untrusted_data": true
  },
  "application_context_get": {
    "isError": false,
    "analysis_status": "FAILED",
    "requirements_count": 0,
    "confirmed_claims_count": 0,
    "untrusted_vacancy_data": true,
    "context_truncated": false
  }
}
```

The full PostgreSQL security test compares product rows before and after both MCP reads for the owner and a second user. It covers application preparations/drafts, approvals, applications, Career Facts, Claims, vacancy status/source snapshots and employer memory. Cross-owner vacancy/context lookups return not-found. Read-only state assertions are also part of the SQLite-backed MCP feature suite.

Redirect URI regression tests exercise the actual registration route. They accept the approved ChatGPT callback shapes and dynamic native loopback ports, and reject host confusion, userinfo, path traversal (including encoded traversal), query/fragment delimiters and malformed URIs. Resource/audience tests bind authorization and access tokens to the configured MCP resource and issuer, require `mcp:use`, and deny expired/revoked tokens and inactive users.

The OAuth regression also verifies that `WWW-Authenticate` derives the protected-resource metadata URL from a configured remote resource authority/path rather than the inbound Host header.

`MCP_ENABLED=false` is the safe default. Local reads need no `OPENAI_API_KEY`; local OAuth requires the existing Passport signing keys. See [Local Development](Local-Development.md) for the exact configuration contract.

## External E2E status

- **Local MCP Inspector discovery and read calls: PASS.**
- **Fresh Inspector login/consent/token flow: NOT COMPLETED in this pass.** The current browser had no authenticated CVortex session. The prior stored OAuth authorization enabled real authenticated tool calls but does not replace this flow.
- **ChatGPT Developer Mode and tool calls: BLOCKED on interactive account/workspace access.** The signed-in ChatGPT sidebar displayed Plus, but the Developer Mode setting and app-creation flow were not reached, so entitlement is not classified as denied or allowed. Official plan documentation is ambiguous for Plus; see the dated [OpenAI/MCP research](../../research/technical/11-MCP-GATEWAY-FOUNDATION.md).
- **Secure MCP Tunnel: NOT VALIDATED.** The latest official macOS arm64 client release was downloaded to a temporary directory, SHA-256-verified against the release manifest, and its `help quickstart` command ran. It is not installed on PATH; no profile/daemon was started because Platform was at sign-in and no tunnel ID, Tunnels Read + Use permission or runtime key was available. No inbound port or public proxy was opened.

ChatGPT OAuth discovery, resource propagation through the remote connection, tool selection, unsupported mutation prompts, and cross-user behavior in ChatGPT remain unverified. Inspector results must not be presented as ChatGPT E2E. Do not call ChatGPT compatibility PASS until that real connection and both read calls succeed.

## Secret and logging boundary

Tunnel process credentials do not belong in the repository `.env`; supply them through the operator's secret manager/process environment according to OpenAI's current tunnel instructions. Never print a token or secret into test output. MCP auth failures return safe structured client errors. Expected Passport bearer failures log only safe request metadata; production must use `APP_DEBUG=false`, and server log detail must not be described as metadata-only beyond the verified handler behavior.
