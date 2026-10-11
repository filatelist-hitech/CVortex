---
title: CVortex Functional Coverage Matrix
status: audited-with-open-findings
created: 2026-10-11
updated: 2026-10-11
tags: [qa, functional, evidence]
---

# Functional coverage matrix

Baseline `f06a6dba058589823bf3d3c8b693cb625170bc4b`; worktree `full-functional-qa`, branch `docs/full-functional-qa-2026-10-11`. Продолжение существующего аудита; product code не изменялся. Явный user override разрешает QA и отчёты сверх `NEXT`; primary mode REVIEW, documentary preflight DOCS, один агент, sequential, no live AI.

Inventory: **59 функций/контролей, 51 проверена**; PASS 47, FAIL 3, BLOCKED 7, NOT_RUN 2. Проверена означает есть выполненный тест, runtime evidence или конкретная inspection; это не синоним PASS. Восемь внешних/ручных/M2 строк включены для видимости пробелов; инвентарь не объявляет все roadmap features реализованными.

Сценарии и контрольные прогоны: **539**, PASS **526**, FAIL **4**, BLOCKED **7**, NOT_RUN **2**. **MOCKED_PROVIDER_E2E = 7**, входит в PASS; это modifier, не отдельный взаимоисключающий исход. Backend/frontend fake providers остаются `AUTOMATED_LOCAL_TEST`, а не provider E2E.

Единица счёта: named PHPUnit case/data set, Vitest case (108 сохранены агрегатом из 9 suites), уникальный browser scenario, DB/control run или явный acceptance gap. Assertions не суммируются. Wrapper script controls считаются отдельными orchestration checks; их внутренние повторные PHPUnit assertions не добавлены. PostgreSQL повторения объединены по имени test; 27 browser entries свёрнуты в 26 уникальных (повтор injection исключён). Источник чисел: [scenario-ledger.json](evidence/full-2026-10-11/scenario-ledger.json), [function-inventory.json](evidence/full-2026-10-11/function-inventory.json).

| ID | Область | Функция / контроль | Результат | Фактический scope | Evidence |
|---|---|---|---|---|---|
| F01 | Auth | Invited registration | PASS | Targeted invite and normalized identity; no public registration | AccessCoreTest; REGISTRATION-invited-browser |
| F02 | Auth | Login and denied credentials | PASS | Real session browser; enumeration/disabled cases automated | AccessCoreTest; AUTH-LOGIN-browser |
| F03 | Auth | CSRF / session fencing | PASS | 419 cross-site; stale generations and long-password behavior automated | AccessCoreTest; AUTH-CSRF-origin-and-token-fallback |
| F04 | Auth | Role / diagnostics boundary | PASS | User 403; admin diagnostics 200 with no private-data bypass | DiagnosticsTest; ADMIN-boundary |
| F05 | Auth | Cross-user resource authorization | PASS | Foreign vacancy/preparation/fact/item 404; foreign list empty | McpGatewayTest; AUTH-002-005-IDOR |
| F06 | Auth | Logout | PASS | 204 logout followed by 401 /me | AUTH-LOGOUT-browser |
| F07 | Career | Manual CONFIRMED fact | PASS | Real local manual fact with user_manual provenance | CareerCoreTest; CAREER-MANUAL-browser |
| F08 | Career | Text extraction to PENDING | PASS | Local mocked provider; no automatic confirmation | CareerCoreRemediationTest; CAREER-EXTRACT-PENDING-browser |
| F09 | Career | Confirm / edit-confirm | PASS | Browser confirm; edit-confirm backend suite | CareerCoreTest; CAREER-EXTRACT-PENDING-browser |
| F10 | Career | Reject and terminal replay | PASS | Rejected fact cannot become confirmed via repeated review | CareerCoreTest; CAREER-REJECT-API |
| F11 | Career | Deprecate / supersede | PASS | Automated lifecycle and PostgreSQL race tests | CareerCoreRemediationTest; supersession concurrency log |
| F12 | Career | Source persistence / dedup | PASS | Raw provenance, repeated-source behavior; refresh | CareerCoreTest; CAREER-REFRESH-persistence |
| F13 | Career | Claims / trusted confirmed-only evidence | PASS | Confirmed PASS; PENDING/REJECTED/DEPRECATED BLOCK | qa-added.xml; database-evidence.json |
| F14 | Career | Explicit ambiguity resolution API | PASS | Owned evidence selected; Truth Guard PASS | CAREER-AMBIGUITY-resolution-API |
| F15 | Career | Explicit ambiguity resolution UI | FAIL | No action for USER_RESOLUTION_REQUIRED; API workaround needed | career-actions.json; career-workspace.tsx |
| F16 | Vacancy | Paste vacancy | PASS | Safe synthetic vacancy via real UI | VACANCY-IMPORT-browser |
| F17 | Vacancy | Raw snapshot / hash / history | PASS | Immutable raw text, versioning and history | database-evidence.json; VacancyCoreTest |
| F18 | Vacancy | URL metadata / duplicates | PASS | Metadata URL not fetched; duplicate convergence | VacancyCoreTest; VACANCY-DUPLICATE-replay |
| F19 | Vacancy | Requirement extraction / normalization | PASS | Laravel TECHNICAL MANDATORY normalized; local mock | VACANCY-IMPORT-browser; VacancyCoreTest |
| F20 | Vacancy | Prompt-injection fail closed | PASS | SEMANTIC_REJECTED, zero persisted requirements/analysis for attack | INPUT-002-prompt-injection-controlled-block |
| F21 | Vacancy | Reanalysis / freshness | PASS | Career signature and snapshot fences | VACANCY-REANALYZE-after-resolution; VacancyCoreTest |
| F22 | Vacancy | Queue / retry / orphan recovery | PASS | Controlled failures and races; no real provider delivery proof | vacancy-postgres-concurrency.log; VacancyCoreTest |
| F23 | Vacancy | Provider/schema error handling | PASS | Fake HTTP error, retry and invalid output regressions | OpenAiResponsesProviderTest; VacancyCoreTest |
| F24 | Matching | Seven matching dimensions | PASS | TECHNICAL MATCH; six NOT_APPLICABLE for this source | database-evidence.json; VacancyCoreTest |
| F25 | Matching | Material gaps / mandatory preferred | PASS | Backend multi-requirement negative tests; main E2E material_gaps=[] | VacancyCoreTest |
| F26 | Matching | Clause-bound candidate evidence | PASS | Negation, incidental terms, language and requirement-scoped support | VacancyCoreTest |
| F27 | Matching | Stale analysis protection | PASS | Changed fact/snapshot cannot reuse stale matching | VacancyCoreTest; VacancyPlanChatTest |
| F28 | Application | Preparation persisted | PASS | Saved snapshot/analysis/career signature; preparation remains DRAFT with mixed items | APPLICATION-GENERATE-browser; database-evidence.json |
| F29 | Application | Resume recommendation fields | PASS | Before/After/Reason/Evidence/Risk; mocked generation | ApplicationDraftTest; recovery-browser.json |
| F30 | Application | Accept distinct from approve | PASS | Browser accepted/approved SHORT cover; resume acceptance backend/frontend tests | ApplicationDraftTest; frontend-test.log; APPLICATION-ACCEPT-APPROVE-browser |
| F31 | Application | Edit with revalidation | PASS | Unsupported edit stores BLOCK and cannot approve | APPLICATION-TRUTHGUARD-unsupported; ApplicationDraftTest |
| F32 | Application | Reject recommendation | PASS | Rejected recommendation persists; cannot approve | APPLICATION-REJECT-API |
| F33 | Application | SHORT cover generation | PASS | Mocked content supported by Claims | APPLICATION-GENERATE-browser; database-evidence.json |
| F34 | Application | STANDARD cover generation | PASS | Original truthful draft generated; later deliberately blocked edit | APPLICATION-GENERATE-browser; database-evidence.json |
| F35 | Application | Truth Guard before approval | PASS | Same-owner confirmed backing required; mock semantic reviewer scoped | qa-added.xml; ApplicationDraftTest; database-evidence.json |
| F36 | Application | Approval / revision audit | PASS | Exact hash, actor, revision, events; no sending | APPLICATION-ACCEPT-APPROVE-browser; qa-postgres.xml |
| F37 | Application | Saved flow refresh / reopen | PASS | APPROVED SHORT, BLOCKED STANDARD, REJECTED recommendation reopen | APPLICATION-REFRESH-persistence; RECOVERY-UI-reopen-results |
| F38 | MCP | Gateway on/off / discovery | PASS | Disabled routes absent, enabled bounded contract available | mcp-toggle.log; McpGatewayTest |
| F39 | MCP | OAuth / PKCE / scopes / resource | PASS | Local automated tokens, redirects, scope and resource controls; no external consumer | McpGatewayTest |
| F40 | MCP | Read-only owned context | PASS | Read calls preserve product rows and owner scope | McpGatewayTest; qa-postgres.xml |
| F41 | MCP | Bounded unapproved draft save | PASS | No MCP approve/send; snapshot/signature fences and draft-write scope | McpGatewayTest; VacancyPlanChatTest |
| F42 | Frontend | Build / frontend contracts | PASS | 108 tests; lint, TS and production build PASS | frontend-test.log; checks.json |
| F43 | Frontend | Responsive / keyboard smoke | PASS | 1440/768/390/320 overflow, Escape and focus return; screenshots only sampled | RESPONSIVE-and-keyboard; responsive-320.png |
| F44 | Frontend | Accessible-name DOM automation | PASS | Materials + Career controls named; lang/title/alt/duplicate ID smoke only | accessibility-smoke.json |
| F45 | Frontend | Performance / browser errors | PASS | 80 read requests; zero error; no defined SLO/load threshold | performance.json; BROWSER-no-exceptions-no-egress |
| F46 | PostgreSQL | PostgreSQL application persistence | PASS | Read-only DB confirms snapshot, requirements, matching, cover, usages and approvals | database-evidence.json |
| F47 | PostgreSQL | Runtime RLS / owner FK | PASS | Foreign owner sees zero; cross-owner FK rejects insert; no commits | pg-constraint-probe.json; qa-postgres.xml |
| F48 | PostgreSQL | Concurrency / upgrade integrity | PASS | Saved scripts PASS except separate legacy duplicate-migration harness | access/career/vacancy/application/chatgpt PostgreSQL logs |
| F49 | Diagnostics | Latest occurrence timestamp-tie regression | BLOCKED | Fixture dedup prevents intended assertion; 3 other PostgreSQL Diagnostics tests PASS | qa-postgres.xml; diagnostics-isolated-repro.log |
| F50 | Reference | B01 asset provenance integrity | FAIL | OFL.txt byte SHA differs after normalization | b01-provenance.json; b01-recovery-repro.log |
| F51 | QA harness | Legacy duplicate vacancy migration proof | FAIL | Fixed --step=4 no longer reaches pre-000009 schema | vacancy-postgres-duplicate-migration.log |
| F52 | External AI | Real OpenAI extraction / generation | BLOCKED | Mock results do not validate real OpenAI | no live AI authorized |
| F53 | External AI | Real ChatGPT OAuth / MCP consumer | BLOCKED | Local contracts tested; external account/transport not verified | no live integration authorized |
| F54 | Accessibility gate | Human audible screen reader | BLOCKED | A1 requires named human/AT evidence | b01-manual-accessibility-release-gate |
| F55 | Accessibility gate | Native 200% zoom / reflow | BLOCKED | Z1 requires native browser zoom reviewer evidence | b01-manual-accessibility-release-gate |
| F56 | Accessibility gate | Full manual state/viewport matrix | BLOCKED | 32 cases and G/N/M/T/K interactions remain open | b01-manual-accessibility-release-gate |
| F57 | Acceptance | Preview real-user value and feedback | BLOCKED | Synthetic automation is not real-user acceptance | m1-4-application-draft completion criteria |
| F58 | M2 | DOCX / PDF application package | NOT_RUN | Not implemented/authorized by this audit | Roadmap M2 |
| F59 | M2 | Full employer memory / contradiction flow | NOT_RUN | MVP acceptance unavailable at M1 baseline | Roadmap M2 |

Evidence aliases: browser IDs → [browser.json](evidence/full-2026-10-11/browser.json); recovery IDs → [recovery-browser.json](evidence/full-2026-10-11/recovery-browser.json); suite names → [qa-sqlite.xml](evidence/full-2026-10-11/qa-sqlite.xml) and [qa-postgres.xml](evidence/full-2026-10-11/qa-postgres.xml). Paths for every recorded run are in the scenario ledger.

`REAL_LOCAL_E2E`: browser/API/PostgreSQL/Redis/Horizon and deterministic lifecycle/ownership paths. `MOCKED_PROVIDER_E2E`: provider-dependent full stack with local HTTP fixture. `EXTERNAL_PROVIDER_E2E`: BLOCKED. Metadata `provider=openai` with `model=qa-deterministic` is an adapter label, not evidence of OpenAI delivery.

No full WCAG conformance, all-state keyboard coverage, exhaustive security proof or production load readiness is claimed. No remote CI/PR readiness was rechecked; this is local QA for one SHA.
