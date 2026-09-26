---
title: MCP Gateway Foundation validation
status: verified-local
owner: project
created: 2026-09-25
updated: 2026-09-27
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

## PR test bootstrap check — 2026-09-26

The first PR #33 `m0-quality` run failed in `McpGatewayTest` because Compose supplied `MCP_ENABLED=false` while the test suite expected the gateway routes and Passport consent binding. `phpunit.xml` now forces `MCP_ENABLED=true` in PHPUnit's environment and server variables. This applies only to tests; normal Compose still defaults to disabled. With an external `MCP_ENABLED=false`, the focused MCP suite passed (7 tests / 65 assertions), and the full backend suite passed with `APP_ENV=local` and a temporary test `APP_KEY` (207 tests / 1278 assertions, six PostgreSQL-only skips). Pint and Larastan passed, and normal Artisan route listing showed zero MCP/OAuth routes when disabled and 18 when enabled. The local `make test` attempt could not exercise the changed suite because this worktree's Docker vendor volume lacks `laravel/mcp`; the clean GitHub runner installs locked dependencies before testing.

## OAuth loopback redirect bugfix — 2026-09-27

The DCR route now applies the structured CVortex validator directly. It accepts the exact approved HTTPS ChatGPT callback shape and native HTTP callbacks on `127.0.0.0/8`, `[::1]`, or exact `localhost`, with an explicit valid port and exactly `/oauth/callback`. It rejects userinfo, query/fragment delimiters, encoded paths, malformed URIs, host confusion, dot-segment traversal, and descendants. The regression matrix exercises the actual `/oauth/register` route.

| Check | Actual result |
|---|---|
| `./vendor/bin/phpunit tests/Feature/McpGatewayTest.php` | PASS: 7 tests / 152 assertions |
| `APP_ENV=local APP_KEY=<temporary test key> ./vendor/bin/phpunit tests/Feature/AccessCoreTest.php` | PASS: 35 tests / 167 assertions |
| `./vendor/bin/pint --test app/Mcp/Http/Controllers/RegisterOAuthClientController.php app/Mcp/OAuth/RedirectUriValidator.php config/mcp.php routes/ai.php tests/Feature/McpGatewayTest.php` | PASS |
| `./vendor/bin/phpstan analyse --memory-limit=1G --no-progress --error-format=table` | PASS: zero errors |
| `MCP_ENABLED=false/true php artisan route:list --json` | PASS: 47 disabled / 65 enabled; all 18 added routes are MCP/OAuth and unrelated routes are identical |
| `git diff --check` | PASS |

Real MCP Inspector Web v2.8.0 reached `POST /oauth/register` and received `201 Created` for its built-in `http://localhost:6274/oauth/callback`; this callback is accepted by the intentional exact-localhost policy. The automated route matrix separately passed `http://127.0.0.1:6274/oauth/callback` and another dynamic port. The initial live DCR attempt returned 500 only because this Compose database had the five Passport migrations pending. `make migrate` stopped at its runtime-role safety check because configured admin/runtime database passwords are equal; the migration service then applied only those five pending Passport table migrations, without resetting the database or deleting volumes.

The first failing OAuth boundary after successful registration was `GET /oauth/authorize` → `401`. The isolated Inspector browser had no authenticated CVortex session; no authorization code or access/refresh token was issued, and Inspector displayed `invalid_client` on its callback. Consent, token exchange, MCP initialize and `tools/list` were therefore not validated in this live OAuth run. This does not change `CHATGPT CONNECTION: NOT_VALIDATED`.

## Preview persistence and authenticated Inspector retest — 2026-09-27

The actual UI path is API-backed: `Preserve and analyze` sends `POST /api/v1/vacancies`, while `Saved vacancies` reloads from `GET /api/v1/vacancies`. The root Compose project `cvortex` routes `http://localhost:8080` through Nginx to this worktree's frontend/backend; backend uses PostgreSQL database `cvortex1`, schema `public`, runtime user `cvortex_app`. No Preview local/session storage is used as product-data authority.

The apparent empty-database diagnosis came from querying through `cvortex_app` without setting the PostgreSQL owner context required by forced RLS. Such an unscoped query correctly sees zero owner rows. The authenticated UI path and a query inside `DatabaseOwnerContext` see the persisted records; read-only administrator inspection found the same Vacancy and snapshot. RLS was not weakened.

| Manual check | Result |
|---|---|
| UI save and `Saved vacancies` refresh | PASS: persisted Vacancy ULID `01m3fvhmxhp83kcz80ecrd0jwg`, snapshot ULID `01m3fvhmxm4s0qc0z5q767tvdt`, version 1 |
| Provider unavailable | PASS: Vacancy remains `FAILED / PROVIDER_ERROR`; the raw snapshot remains readable in the UI after browser reload |
| Untrusted source preservation | PASS: synthetic prompt-injection strings remained raw vacancy content and did not affect the tool behavior |
| Authenticated local Inspector OAuth and tool discovery | PASS: discovery, DCR, authorization, token exchange, initialize and `tools/list`; exactly `vacancy_get`, `application_context_get`, `application_draft_submit` |
| `vacancy_get` for the UI-created ID | PASS: same persisted ID and `FAILED` status; source body is not returned |
| `application_context_get` for the UI-created ID | PASS: same ID/status, empty requirements/confirmed claims, `untrusted_vacancy_data=true` |
| `application_draft_submit` for the provider-failed vacancy | Controlled `VALIDATION_UNAVAILABLE`; PostgreSQL confirms 0 preparations, drafts and approvals for this vacancy |

Automated validation for this integration slice: `VacancyCoreTest`, `McpGatewayTest` and `AccessCoreTest` passed together (978 assertions; 152 non-failing warnings/log entries); the frontend suite passed (16/16). PostgreSQL vacancy RLS revalidation passed twice (44 assertions each; three existing non-failing missing-`.env` warnings per run), preserving first-run rows across migration rollback/re-up; the independent import/reanalysis concurrency harness passed. The root `cvortex` Compose environment has equal admin/runtime passwords, so these boundary checks ran in a separate Compose project with distinct ephemeral credentials and PostgreSQL tmpfs; the product database and volumes were not reset or removed. Pint passed on seven changed backend PHP files and Larastan reported zero errors. Route registration checks returned 0 MCP/OAuth routes with `MCP_ENABLED=false` and 18 with it enabled. The full backend suite was also run and found one separate reproducible failure in unchanged `CareerCoreRemediationTest::test_api_review_flow_records_human_actions_and_keeps_one_candidate_pending` (`Confirmed source wording.` absent from that test's collection); the bounded Vacancy/MCP/Access suites pass.

The local Inspector result supersedes the earlier isolated-profile `401` attempt above. It remains a local MCP result: `CHATGPT CONNECTION = NOT_VALIDATED`; no ChatGPT account, public HTTPS endpoint, tunnel or external OAuth resource/audience flow was exercised.

## Career review regression isolation — 2026-09-27

The review-flow test noted above read extraction candidates immediately after the API returned `202`. That API queues extraction; it does not promise synchronous completion. `QUEUE_CONNECTION=null` reproduced the reported missing `Confirmed source wording.` key in the unchanged test, while the sync driver produced one `COMPLETED` source, four `PENDING` candidates and a `PASS` LLM run. The original failing run's queue configuration was not captured. The test now fakes the queue and explicitly executes extraction before review. Its human-action and provenance assertions are unchanged.

After the test-only fix, the exact method passed under both null and sync queues. Career Core, Vacancy Core, MCP Gateway and Access Core passed together (184 tests / 1242 assertions, 1 skip). The full backend suite passed (210 tests / 1419 assertions, 6 PostgreSQL-only skips). Pint, Larastan and `git diff --check` passed. No MCP, Vacancy, OAuth or Career production code changed in this fix.
