---
status: research-complete
date: 2026-09-12
phase: 03-technical-research
owner: CVortex
architecture_decision: none
---

# Technical Decision Candidates

> **Post-Phase-05 reconciliation**
>
> This document is historical Phase 03 research input. Phase 05 has completed. Items below remain useful as dated evidence and option history, but they are **not current architecture decisions**.
>
> Current architecture authority is `PROJECT.md`, `docs/03-ADR/INDEX.md`, and the accepted ADRs. Where this file conflicts with those newer authoritative sources, the newer source wins. Concrete versions and vendor capabilities below are freshness-sensitive and must be revalidated before implementation when required by the active phase.

No accepted ADRs are created by this file. Items marked **owner-fixed direction** describe the Phase 03 understanding at the time of research and must not override later accepted project state.

## Owner-fixed directions preserved during Phase 03

- Backend: PHP 8.4+ and Laravel.
- Frontend: Next.js, React, TypeScript, Tailwind.
- Database: PostgreSQL.
- Queue/cache: Redis + Laravel Horizon.
- Web: Nginx.
- Documents: DOCX templates + LibreOffice headless -> PDF.
- Deployment: local-first Docker Compose, API-first, later VPS/cloud.
- Phase 03 recorded OpenAI as first LLM provider, with provider-independent business architecture and BYOK/system-managed credentials. Later Phase 05 reconciliation established that provider selection is mutable configuration; accepted ADRs and `PROJECT.md` are authoritative.
- Figma is visual source of truth; docs are Markdown/Obsidian.

## Historical candidates for Phase 05 decision freeze

| ID | Decision | Options | Preliminary recommendation | Status / evidence needed |
|---|---|---|---|---|
| TC-01 | PHP runtime | 8.4 vs 8.5 | **8.5 current patch** | Candidate. Longer support; validate extensions/libs. |
| TC-02 | Laravel major | 12 vs 13 | **13.x** | Candidate. 12 already security-only; dependency validation needed. |
| TC-03 | PostgreSQL major | 17 vs 18 | **18.x current minor** | Candidate. Current stable, long support. |
| TC-04 | Redis branch/license | 8.10.x AGPL/other Redis license vs older BSD line | **8.10.1+**, document selected license | Candidate. Security fixes favor current; legal/license record required. |
| TC-05 | Laravel Redis client | PhpRedis vs Predis | **PhpRedis** | Candidate. Laravel-preferred, container makes extension manageable. |
| TC-06 | Browser auth | Sanctum stateful session vs SPA bearer token | **Sanctum stateful session + CSRF** | Strong candidate. Official first-party SPA guidance. |
| TC-07 | Auth backend endpoints | Fortify vs custom Laravel auth actions | **Fortify + custom invitation workflow**, subject to design | Candidate. Validate invite-only UX and route ownership. |
| TC-08 | Resource authorization | Policies + Gates vs ad-hoc service/controller checks | **Policies for resources, Gates for global/admin** | Strong candidate. Requires ownership test matrix. |
| TC-09 | BYOK at-rest encryption | Laravel Crypt vs external KMS/envelope | **Laravel Crypt initially** | Candidate. Revisit for cloud/threat maturity. |
| TC-10 | HTTP/API rate limiting | DB/cache vs Redis limiter | **Redis-backed** | Candidate. Define dimensions/quotas in security design. |
| TC-11 | Queue topology | one queue vs logical queues | **critical/default/llm/documents/research/imports** | Owner plan supported. Exact supervisors deferred. |
| TC-12 | Queue semantics | generic retries vs explicit idempotency/backoff/timeouts | **explicit per-job policy** | Candidate. Design in Phase 06/08. |
| TC-13 | OpenAI PHP transport | Laravel AI SDK vs openai-php/client vs direct HTTP/OpenAPI codegen | **Spike Laravel AI SDK + direct escape hatch behind `LlmProvider`** | Candidate. Must test BYOK, usage data, Batch, new provider fields. |
| TC-14 | Product LLM abstraction | provider SDK types vs CVortex-owned interfaces | **CVortex-owned contracts** | Owner principle. Exact interfaces Phase 06. |
| TC-15 | Model routing | hardcoded model names vs logical tiers/config mapping | **logical capability tiers + versioned mapping** | Strong candidate. Concrete mapping requires evals. |
| TC-16 | Structured extraction | free prose parsing vs Structured Outputs + validation | **Structured Outputs + local validation** | Candidate. Still requires truth/business checks. |
| TC-17 | Prompt caching layout | dynamic-first prompts vs stable-prefix prompts | **stable instructions/schema/tools prefix** | Candidate. Track cache read/write tokens. |
| TC-18 | Batch workloads | synchronous everything vs Batch for offline work | **Batch for offline/bulk/evals only** | Candidate. Interactive flows remain synchronous/queued standard. |
| TC-19 | Next.js line | 15 Maintenance vs 16 Active LTS | **16.x patched stable, >=16.3.3 at research date** | Candidate. Recheck security release at bootstrap. |
| TC-20 | React | 19.3 vs older 19.x | **19.3 current patch** | Candidate, confirm Next compatibility. |
| TC-21 | TypeScript | 5.9 vs 6.0 | **6.0 current patch** | Candidate. Explicit tsconfig, address deprecations. |
| TC-22 | Tailwind | 4.3 vs older v4 | **4.3 current patch** | Candidate. Confirm browser-support policy. |
| TC-23 | Component primitives | Base UI vs Radix vs React Aria | **Base UI first spike; benchmark hard controls** | Candidate. Accessibility/component/Figma spike required. |
| TC-24 | Backend test syntax | Pest 5 vs direct PHPUnit 13 | **Pest 5 over PHPUnit 13** | Candidate. Either way PHPUnit remains engine. |
| TC-25 | Frontend tests | Vitest/RTL vs alternatives | **Vitest 5 + RTL** | Candidate. Validate Next client/server component testing boundaries. |
| TC-26 | E2E | Playwright vs alternatives | **Playwright** | Candidate. Current 1.63 at research date. |
| TC-27 | DOCX renderer | PHPWord vs raw OOXML vs separate renderer | **PHPWord TemplateProcessor** | Candidate. Must pass real resume fidelity spike. |
| TC-28 | LibreOffice line | 26.2 maintenance vs 26.8 major | **test both; likely pin maintenance line first** | Candidate. Render regression decides. |
| TC-29 | Token format | proprietary JSON vs DTCG 2025.10 | **DTCG 2025.10 stable** | Strong candidate. |
| TC-30 | Token transformer | Style Dictionary vs custom transforms | **Style Dictionary spike** | Candidate. Its docs say 2025.10 support is not yet complete. |
| TC-31 | Token canonical source | Git DTCG vs Figma Variables | **Git DTCG for build canonical; Figma visual canonical** | Candidate requiring explicit ADR and drift workflow. |
| TC-32 | Figma MCP | remote vs desktop/local/manual only | **Remote MCP** | Candidate, seat/permission constraints apply. |
| TC-33 | Figma write-to-canvas | automatic authority vs assisted/human-reviewed | **assisted + human review** | Strong candidate due beta limitations and owner human-control philosophy. |
| TC-34 | Code Connect | legacy React parser vs template files | **template files** | Strong candidate; legacy parsers no longer actively maintained. |

## Explicitly not decided in Phase 03

- final Composer/npm lock versions;
- final ModelPolicy mappings to OpenAI model IDs;
- exact queue worker counts;
- auth UI and invitation endpoint contracts;
- final primitive library;
- final token sync mechanism/plugin;
- exact LibreOffice container build;
- concrete database schema/ERD;
- accepted ADRs.

## Historical Phase 05 input

Phase 05 consumed these candidates together with Phase 04 product/integration/hiring/security research. The resulting accepted decisions are recorded in `docs/03-ADR/INDEX.md` and `docs/02-Architecture/Architecture-Baseline.md`.

Do not treat an unaccepted recommendation in this file as a deferred architecture decision merely because it was proposed during Phase 03.
