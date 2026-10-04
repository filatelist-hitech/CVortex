# ChatGPT plan chat validation — 2026-10-04

Branch: `feature/chatgpt-local-mcp-integration`. Existing PR #42 base verified as stage; merge-base ec13ce68fd1200a0f307f00d8630588a9e3aa75a. No branch creation, commit, push or publication. Pre-existing operator/state edits preserved. Explicit user scope overrides NEXT only for this bounded task; Preview/M2 not advanced.

## Official protocol research

Checked official Sign in with ChatGPT overview, local/open-source plan usage, registration/sign-in, models/inference, accounts/sessions, token reference, errors/recovery, preview limitations and UI/UX on 2026-10-04. Sources linked in ADR-0023. Direct public Responses was chosen because no local agent execution is needed. MCP remains an external adapter, not an inference or billing gateway.

## Automated validation actually executed

- Full backend PHPUnit with DB_CONNECTION=sqlite, DB_DATABASE=:memory:, DB_URL empty, CACHE_STORE=array, SESSION_DRIVER=array: 323 tests, 2482 assertions, 11 PostgreSQL-specific skips. No failures.
- Targeted ChatGptPlanTest + VacancyPlanChatTest + McpGatewayTest: 37 tests / 407 assertions passed. Latest declined-issued-client persistence extension: ChatGptPlanTest 13 tests / 77 assertions passed. Final MCP suite after explicit bounded-source assertions: 16 tests / 280 assertions passed.
- Frontend npm test: 82 tests across 6 files passed. Existing page-fixture duplicate-key warnings remain; they are not new chat failures.
- Backend vendor/bin/pint --test: 211 files passed; PHPStan --memory-limit=1G: no errors.
- Frontend npm run lint, npm run typecheck and NODE_ENV=production npm run build passed. The separately tracked development-mode production-build environment issue is unchanged.
- docker compose --env-file .env.example config --quiet passed.
- bash scripts/test-mcp-gateway-toggle.sh passed: disabled 0 routes; enabled 20 MCP/OAuth routes.
- The pre-remediation `scripts/test-chatgpt-postgres-boundary.sh` run used an isolated disposable database and restricted runtime role for forced RLS, cross-owner reads/writes/composite foreign keys, serialized refresh, concurrent draft idempotency, source/fact preservation and migration rollback/reapply. It did **not** include approval. The independent reviewer's approval/concurrent-repeat check was manual in a temporary PostgreSQL database and was not reproducible through that repository harness.
- bash scripts/test-vacancy-postgres-concurrency.sh passed: import/reanalysis and Vacancy/Career orphan-recovery races converged.
- bash scripts/test-nginx-access-log-safety.sh passed; Nginx config test/reload passed; privileged make migrate applied the three new local migrations.
- git diff --check passed.

Initial failures were corrected: SQLite test env override, RSA JWK loading, explicit Laravel table naming, tool schema namespace, parent page mocks, global nested distinct validation, exact-label prompt guidance and harness fixture construction/exit handling. No weakened source validator or test harness was used to claim success. The harness now exits nonzero on uncaught errors.

## Live evidence

User performed browser OAuth consent and reported the terminal completed greeting “Hello, CVortex.” No token/password was requested. Agent observed Connected / Using ChatGPT plan, the live account model catalog and first-use notice.

In the ordinary local test user account, two explicitly user-supplied career assertions were saved through the manual human-confirmed workflow. Their contents and the vacancy identity are omitted here to keep private career and employer data out of Git. The vacancy was preserved as source snapshot version 1.

Agent observed streamed Analyze vacancy, three completed plan runs, six persisted chat messages, two AI_GENERATED DRAFTs and model/history/draft restoration after reload. The first generated paraphrased requirements were rejected; after tightening the registry prompt, a subsequent Analyze vacancy quick action saved directly with literal source-backed requirements and MANDATORY/PREFERRED section classification. No source or fact mutation occurred: snapshot created_at equals updated_at, exactly two facts remain CONFIRMED. Live drafts remain unapproved for user review; approval is covered by SQLite/API and real PostgreSQL checks.

External ChatGPT: user reported a refreshed three-tool catalog and actual sequential read calls followed by a successful empty bounded draft save, status DRAFT / AI_GENERATED. Agent independently repeated both reads successfully against the user's specified owned vacancy; attempting the browser test user's vacancy through the external account returned NOT_FOUND (live ownership isolation). Initial tunnel_client_not_seen was resolved externally. A previous substantive save was rejected by validation; payload/cause not provided. FAILED legacy API analysis itself is not a draft-write blocker. User subsequently reported a successful nonempty source-backed requirement draft, status DRAFT / AI_GENERATED. This demonstrates external structured persistence despite FAILED legacy API analysis; external write evidence is user-reported, while local shared-service and PostgreSQL write verification are agent-executed.

## Limits

Only this eligible local account/deployment was live verified. Other plans/workspaces and remote/paid deployment are not implied. Existing API-key inference still reports its separately tracked quota/rate-limit failure; regression tests passed, no billing repair claimed. Career Track and Employer Memory aggregates do not exist yet; the context uses available prior approved employer statements. Exact Figma comparison was not performed; existing frontend styles/tokens are reused. ChatGPT provider monetary cost is null. No CI/push/PR readiness claim.

Result: PASS for the authorized local ChatGPT plan chat + controlled MCP draft-save block. Preview/M2 phase acceptance remains unchanged.

## Pre-merge review remediation — 2026-10-04

Fixed `PLAN-MODEL-01`: `VacancyChatService::begin` now resolves the active ModelPolicy and checks the requested slug against models fetched for the authenticated user's owned connection before creating/updating chat state. `OpenAiChatGptPlanProvider::stream` fetches that connection's catalog again immediately before Responses inference. Catalogs are fetched live with no shared or persisted cache. Controlled errors are `MODEL_NOT_AVAILABLE` and `MODEL_NOT_ALLOWED`; there is no alternate-model or API-key fallback. Regressions cover a handcrafted valid-looking slug, a real adapter call with two different account tokens, policy rejection, a stale saved selection and no Responses fallback request.

Fixed `PG-EVIDENCE-01`: the repository PostgreSQL harness now concurrently approves one saved DRAFT. It holds the first transaction inside a temporary trigger created by the migration/table owner in the disposable test database, confirms the second worker is waiting on a PostgreSQL lock, and then lets both finish. It verifies one canonical analysis, one requirement set, the expected current CONFIRMED Career Fact in deterministic matching evidence, unchanged source/fact data, identical approval results, sequential repeat idempotency and clean transaction state. The whole disposable database is dropped by its creator; no foreign-owned table/object is dropped.

Validation executed for this remediation: targeted `ChatGptPlanTest` + `VacancyPlanChatTest` passed (26 tests / 156 assertions); `McpGatewayTest` passed (16 / 280); `scripts/test-chatgpt-postgres-boundary.sh` passed including observed overlapping row-lock wait, approval and rollback/reapply; Pint passed for nine changed PHP files; PHPStan passed across 135 files; OpenAPI JSON parsing, shell syntax and `git diff --check` passed. Full backend/frontend suites were not rerun for this remediation.

`CHAT-ARCH-01` and `CHAT-CTX-01` remain explicit follow-ups. Local fixes are ready for independent re-review; no fresh review, commit, push or merge was performed.
