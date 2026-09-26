---
title: MCP Gateway Foundation validation
status: verified-local
owner: project
created: 2026-09-25
updated: 2026-09-26
tags: [operations, mcp, validation]
related: [../02-Architecture/MCP-Gateway.md]
---

# MCP Gateway Foundation validation — 2026-09-25

Environment: isolated `feature/mcp-gateway-foundation` worktree; host PHP 8.5.3 for test/protocol smoke; disposable PostgreSQL 18.6 container with separate administrative and `cvortex_mcp_app` runtime credentials. `MCP_ENABLED=true` was set only in test processes. Temporary local Passport tokens and signing keys were not committed. The Inspector smoke router bound the existing `McpGatewayFakeProvider` **in the temporary test process only** so a passing Truth Guard response could be tested without a live API charge. Production `LlmProvider` binding was unchanged.

| Check | Actual result |
|---|---|
| `APP_ENV=local MCP_ENABLED=true APP_KEY=<test key> php artisan test --compact` | PASS: 206 tests, 1274 assertions, six PostgreSQL-only skips in SQLite run |
| `vendor/bin/pint --test --dirty` | PASS |
| `vendor/bin/phpstan analyse --memory-limit=1G --no-progress --error-format=table` | PASS: zero errors |
| `docker compose --env-file .env.example config --quiet` | PASS |
| Fresh PostgreSQL admin migration | PASS: five Passport tables created after existing Application tables |
| `ApplicationPostgresSecurityTest` as `cvortex_mcp_app` | PASS: two tests, 45 assertions; runtime role `rolsuper=0`, `rolbypassrls=0` |
| PostgreSQL rollback/re-up of six latest migrations | PASS: five OAuth and six Application tables removed, preexisting User retained (`1:0`); re-up restored `1:5:6` |
| MCP Inspector CLI over local Streamable HTTP, valid Passport bearer | PASS: three tools discovered; `vacancy_get` and `application_context_get` returned bounded owned context; `application_draft_submit` returned `PASS`, `PENDING_REVIEW`, `requires_cvortex_approval=true` |
| Same Inspector against PostgreSQL runtime role | PASS: context and write; persisted state `DRAFT:PASS:DRAFT:0` (item validation, preparation status, approval count) |
| Inspector with foreign Vacancy ID | PASS: read and write returned `isError=true`, `NOT_FOUND` |
| Inspector with invalid bearer | Auth required (exit 3); client attempted interactive OAuth, unavailable in noninteractive CLI |
| Legacy `initialize` request | PASS: negotiated `2025-11-25`, server `CVortex` |
| Modern `server/discover` request | PASS: advertised `2026-07-28` |
| `AI_PROVIDER=none` draft call | Controlled `VALIDATION_UNAVAILABLE`, no draft saved in that attempt |

Automated MCP tests separately cover missing/invalid/revoked/expired bearer, missing scope, disabled user token revocation, cross-owner read/write, unknown argument, rate limit, bounded context, current confirmed provenance, Truth Guard block, changed evidence, DCR callback allowlist and lack of approval. The full existing backend suite passes with MCP enabled, so its existing Responses API workflow remains exercised by regressions.

`MCP SERVER: PASS` for local protocol and PostgreSQL runtime. `WRITE CAPABILITY: PASS` with a test provider; real provider availability remains a runtime dependency. `CHATGPT CONNECTION: NOT_VALIDATED`: no ChatGPT session, reachable HTTPS/tunnel endpoint, complete authorization-code flow or verified OAuth resource/audience flow was tested; current account entitlement was not inspected. Inspector is not a ChatGPT E2E test.

The first `npx @modelcontextprotocol/inspector` invocation emitted a non-failing npm deprecation warning for `@modelcontextprotocol/server-legacy`. It did not affect the transport results above.

## Finalization check — 2026-09-26

The OAuth consent view was explicitly registered with Passport, and DCR was limited to the callback-ID-specific ChatGPT URI supported by the current allowlist. The authorization-code flow itself was not run. After these changes, `McpGatewayTest` passed (7 tests / 65 assertions), `AccessCoreTest` passed (35 tests / 167 assertions), Pint on the changed backend files and Larastan passed (zero errors), Compose config passed, disabled/enabled route-list checks passed, and `git diff --check` passed. The earlier full backend and PostgreSQL results above were not rerun because the final code changes affected only OAuth consent/registration, covered by the focused tests.
