---
title: CVortex Full Functional Audit 2026-10-11
status: changes-required
created: 2026-10-11
updated: 2026-10-11
tags: [qa, audit, first-value]
---

# Full functional audit — 2026-10-11

**CHANGES REQUIRED.** Основной synthetic workflow работает на реальном локальном стеке с mock provider. Самостоятельное разрешение Claim ambiguity через Career UI отсутствует. B01 integrity check и два test harness controls имеют открытые failures. Preview 0.1 и MVP 0.2 не готовы.

Tested SHA: `f06a6dba058589823bf3d3c8b693cb625170bc4b`. QA branch: `docs/full-functional-qa-2026-10-11`; checkout and temporary artifact paths are intentionally omitted from the Git export. Итоговые отчёты ранее отсутствовали; создан ровно один файл по каждому запрошенному пути, старые evidence сохранены. No product code changes, external provider calls or production writes.

## Recovery and counting

| Показатель | Количество |
|---|---:|
| Функции / контроли в bounded inventory | 59 |
| Функции с проверкой / inspection | 51 |
| Function PASS / FAIL / BLOCKED / NOT_RUN | 47 / 3 / 7 / 2 |
| Сценарии / контрольные прогоны | 539 |
| Scenario PASS | 526 |
| Scenario FAIL | 4 |
| Scenario BLOCKED | 7 |
| Scenario NOT_RUN | 2 |
| MOCKED_PROVIDER_E2E, входит в PASS | 7 |
| Дефекты | 4: 1 functional UI, 1 reference integrity, 2 test infrastructure |

Термины и exact ledger: [Coverage matrix](FUNCTIONAL-COVERAGE-MATRIX.md). Числа не означают процент готовности всего продукта. Четыре FAIL сценария: B01 checker, duplicate-migration harness, Diagnostics timestamp-tie test, expected Career UI resolution action. Function Diagnostics BLOCKED: его timestamp-tie поведение не доказано из-за faulty fixture; product sorting failure не установлен.

Восстановлены: canonical backend suite **366 cases / 2772 assertions / 11 skips**, frontend **108/108**, lint/Pint/Larastan/TS/build, B01 node tests, MCP route toggle и PostgreSQL harnesses. PostgreSQL targeted suite **10 cases / 122 assertions**, 9 PASS и 1 FAIL. После merge результатов по test name 364 из 366 canonical backend cases PASS, 1 FAIL и 1 BLOCKED. Оставшийся skipped `VacancyCoreTest::test_postgres_rejects_a_cross_owner_snapshot_requirement_chain` не выполнялся как exact PHPUnit case; эквивалентный FK/RLS transaction probe PASS. Existing QA Truth Guard data sets: **4/4 / 12 assertions**.

`checks.json` содержит 19 исторических запусков. Первоначальный nginx check exit 1 снят прежним исправленным запуском с QA host/port — PASS в `nginx-log-safety-corrected.log`; raw record не переписан. Первоначальный noncanonical SQLite run имел 2 errors/3 failures; тот же SHA позже прошёл canonical suite. Это environment-sensitive attempt, не пять подтверждённых product defects; точная первоначальная effective config не сохранена, root cause не объявляется доказанным.

Browser record уже содержал основной safe-vacancy workflow: 27 PASS entries, **26 unique**. Не повторяли successful suites. Первоначальный happy-path timeout с injected vacancy переинтерпретирован только после проверки persisted `SEMANTIC_REJECTED`: это expected negative rejection, не PASS обработки нормальной вакансии. Для happy path сохранён отдельный safe fixture. Ранние generator failures сохранены отдельно; Claim ambiguity потребовала явного API resolution, после которого reanalysis/generation PASS.

Новые проверки: reopen results в новом browser context; доступные имена controls/lang/title/alt/duplicate IDs на Materials и Career; 20 read-only DB queries по текущим persisted records; FK/RLS negative probe с rollback; повтор ранее FAIL OFL check; snapshots/main checkout preservation. [Recovery checkpoint](evidence/full-2026-10-11/recovery-checkpoint.json).

## Environment safety

Compose project `cvortexqa1011`; only loopback `127.0.0.1:18081` exposed. `cvortex_qa_synthetic`, runtime role `cvortex_qa_app` (no superuser/BYPASSRLS), own PostgreSQL volume and Redis container with audit Horizon prefix. No primary PostgreSQL/Redis mounts or service connection. Test users use reserved `qa.example.test`/`example.test` domains.

Backend/Horizon use `OPENAI_BASE_URL=http://fakeprovider:8000/v1`, `qa-deterministic`, synthetic canary credential; scheduler `AI_PROVIDER=none`, credential empty. Mock implementation uses HTTP `createServer` with no outbound client; workers only internal networks. No live AI call made in this continuation. Browser records `external=[]`, `errors=[]`.

**Network limitation:** `edge internal=false` includes backend/frontend/nginx. A Docker-wide no-egress guarantee is unavailable; current URL/credential controls and mock-source inspection establish bounded local calls, not historical packet-level proof. This is a QA environment hardening gap. No changes to network configuration made during recovery.

Primary DB snapshots (`cvortex`, `cvortex_dev2`, `cvortex_test`) match counts and aggregate row digests, excluding sessions/cache/cache_locks as in the original snapshot design. Primary tracked diff remains empty; all **802** pre-existing untracked files match saved SHA-256. This evidence covers the sampled tables/files; it is not a forensic proof of every host byte. Raw snapshots, private invitations/credentials stay in `/tmp`; repository stores summary digests only. [Environment](evidence/full-2026-10-11/environment-safety.json).

## Functional E2E evidence

Все Career assertions и vacancy/company names ниже — synthetic fixtures, не факты о пользователе. `Built Laravel APIs.` — единственное supporting утверждение mock.

| Шаг | Expected | Actual | Result / class | API evidence | DB evidence |
|---|---|---|---|---|---|
| 1. Vacancy create | Owned safe vacancy stored | QA Verified Backend Engineer, COMPLETED | PASS REAL_LOCAL_E2E | POST /vacancies 202; subsequent GET 200 | vacancies record |
| 2. Raw source | Exact raw source / immutable version | Snapshot v1 retains Laravel text and inert XSS payload | PASS REAL_LOCAL_E2E | snapshot.raw_text | vacancy_snapshots raw_text/content_hash |
| 3. Requirement extraction | Source-bound supported requirement | Laravel, MANDATORY, TECHNICAL | PASS MOCKED_PROVIDER_E2E | requirements source_excerpt | vacancy_requirements + run PASS |
| 4. Normalization | Stable normalized value | `laravel`, source-backed excerpt | PASS MOCKED_PROVIDER_E2E | requirements.normalized_value | persisted normalized_value |
| 5. Matching | Confirmed evidence, dimension explanation | TECHNICAL MATCH; remaining six NOT_APPLICABLE | PASS REAL_LOCAL_E2E after mocked extraction | analysis.dimensions | vacancy_match_dimensions/evidence |
| 6. Gap analysis | Material gaps correctly represented | Empty material_gaps for one supported requirement; multi-gap negatives covered by backend tests | PASS local deterministic scope | analysis.material_gaps=[] | vacancy_analyses |
| 7. Recommendations | Before/After/Reason/Evidence/Risk | Resume recommendation + two covers; recommendation later REJECTED | PASS MOCKED_PROVIDER_E2E | preparation.items | items/usages/revisions |
| 8. Truth Guard | Same-owner CONFIRMED Claims only | Original content PASS; unsupported Kubernetes edit BLOCK | PASS with mocked semantic reviewer | item validation; approve blocked 422 | confirmed facts/evidence; STANDARD BLOCK |
| 9. Cover draft | SHORT and STANDARD unapproved variants | Both generated as drafts; no employer sending | PASS MOCKED_PROVIDER_E2E | generation route 202 then GET 200 | two COVER_DRAFT rows |
| 10. Approval | Exact validated text and explicit approval | SHORT ACCEPTED then APPROVED; STANDARD edit cannot approve | PASS REAL_LOCAL_E2E after mock validation | item PATCH/approve 200; unsupported approve 422 | approval events + matching validated_content_hash |
| 11. Persistence | Drafts and history survive request | Preparation DRAFT; mixed APPROVED/BLOCKED/REJECTED states | PASS REAL_LOCAL_E2E | GET preparation 200 | preparations/items/revisions/usages/events |
| 12. Refresh | Same statuses/content after reload | Browser refresh retains approved and rejected states | PASS REAL_LOCAL_E2E | APPLICATION-REFRESH-persistence | unchanged persisted records |
| 13. Reopen | New browser context sees saved flow | Materials reopened; approved cover and blocked text visible | PASS REAL_LOCAL_E2E | RECOVERY-UI-reopen-results + GET payload | DB-CHAIN PASS |
| 14. Cross-user auth | No private read/write or admin bypass | User B reads/writes 404, list empty; admin own diagnostics 200/foreign vacancy 404 | PASS REAL_LOCAL_E2E | AUTH-002-005-IDOR; ADMIN-boundary | user-B/unset RLS reads 0; FK violation caught |
| 15. Logout | Session loses API access | Logout 204 then /me 401 | PASS REAL_LOCAL_E2E | AUTH-LOGOUT-browser | No direct session-table snapshot; Redis session behavior proven by API |

API body snapshots and DB subsets: [recovery-browser.json](evidence/full-2026-10-11/recovery-browser.json), [database-evidence.json](evidence/full-2026-10-11/database-evidence.json); historical HTTP method/status list: [browser.json](evidence/full-2026-10-11/browser.json). Browser/API helpers use `/api/v1` prefix for abbreviated routes above. Source/history checks do not claim a database query was recorded at the exact instant of every earlier HTTP step; current read-only DB records corroborate persisted outcomes.

Screenshots: [reopened Materials](evidence/full-2026-10-11/recovery-materials.png), [320](evidence/full-2026-10-11/responsive-320.png), [390](evidence/full-2026-10-11/responsive-390.png), [768](evidence/full-2026-10-11/responsive-768.png), [1440](evidence/full-2026-10-11/responsive-1440.png). Reopened desktop and 320 samples inspected during recovery; no full human visual acceptance claimed.

## Security and Truth Guard

Confirmed local controls: CSRF rejection, non-enumerating authorization, owner-scoped API writes, runtime forced RLS on Vacancy/Application tables, composite owner FK, immutable snapshots, no implicit admin private-data access, secret/log redaction test coverage, malformed input 422, oversize payload 413, unknown method 405, inert HTML/XSS, injection SEMANTIC_REJECTED. Direct FK probe rolled back; no retained negative-write mutations. Career Facts/Claims do not have DB RLS in this architecture: owner isolation there relies on server scopes and ownership/composite constraints; do not infer forced RLS across every table.

Truth Guard confirmed chain: APPROVED SHORT content hash equals approved text; linked Claim PASS and same-owner CONFIRMED facts. Four supplementary evidence-state cases block PENDING/REJECTED/DEPRECATED evidence. Duplicate types require explicit resolution; resolving through API is safe but the UI gap is a functional defect. Semantic truth-review model was mocked; robustness of real OpenAI/ChatGPT against adversarial prompts remains BLOCKED.

MCP contracts (22 backend tests) cover bearer/scopes/resource/redirect/owner boundaries, bounded reads and unapproved draft write. External ChatGPT/tunnel/OAuth consumers were not exercised. No file converter/fetcher/package runtime is claimed at M1; M2 traversal/malicious-upload/export scope is NOT_RUN, not security PASS.

## Responsive, accessibility, errors, performance

Existing responsive smoke: no horizontal overflow at 1440/768/390/320, Escape closes profile dialog and returns focus. New accessibility automation: CDP accessible names on interactive controls, document lang/title, image alt, duplicate IDs for Materials and Career. This is a semantic smoke; no full WCAG/contrast audit, full keyboard traversal, human A1 or native Z1.

Error coverage: provider/schema/semantic failures, retry/recovery races, malformed JSON, empty/invalid/oversize API input; successful browser record contains zero page exceptions. Diagnostics timestamp-tie expected behavior remains undemonstrated by its faulty fixture.

Performance: 20 requests each to Career, vacancy list/detail, preparation (**80** total). p95 **19/22/24/26 ms**, error rate 0 on local small fixture. No latency SLO, load saturation or production-volume test; only measurement smoke PASS.

## Release readiness and blockers

| Target | Verdict | Reason |
|---|---|---|
| M1 First Value | PARTIAL / local core workflow demonstrated | Synthetic browser workflow plus mock-dependent extraction/generation; ambiguity UI defect and technical proof failures open |
| Preview 0.1 | BLOCKED | Mandatory real-user value/feedback acceptance missing; no real provider E2E; required technical checks not all green; B01 manual release gate OPEN |
| MVP 0.2 | NOT_READY / NOT_RUN | M2 package/export/employer-consistency features not implemented/authorized; Preview exit not achieved |

Open coverage: external OpenAI, external ChatGPT OAuth/MCP consumer, real-user acceptance, A1 screen reader, Z1 native 200%, full manual state matrix, one exact PostgreSQL PHPUnit case. Equivalent FK negative proof reduces the ownership gap, not the missing exact runner execution. No cloud review, CI readiness, PR contract or release promotion was claimed.

## Defects and recommended order

[Defect register](FUNCTIONAL-DEFECT-REGISTER.md) contains reproduction, root cause, severity, affected tests, fixes and regression criteria.

1. Repair bounded QA proof failures (legacy migration setup and Diagnostics fixture), reconcile B01 OFL byte provenance without weakening integrity checks.
2. Add Career UI explicit Claim resolution with owner-safe selection and revalidation in a separately authorized product task.
3. Complete mandatory human accessibility/state review and named Preview acceptance; authorized real-provider runs require their own consent and suitable account conditions.
4. Consider M2 planning only after Preview gates; no package readiness by inference.

**One recommended next bounded task:** `Repair full-functional QA proof gates`: change only the legacy migration harness setup, Diagnostics timestamp-tie fixture and B01 license provenance handling; run targeted regressions on an isolated disposable environment under separate authorization. No product feature changes. This recommendation does not update authority: repository `NEXT` remains `b01-manual-accessibility-release-gate`.

## Preservation and STOP

Reports/evidence saved in this QA branch only. During the audit closeout, existing untracked QA test/JUnit files were retained; they predate the continuation and are test artifacts, not product edits. No stage/reset/stash/commit/push/merge, database deletion or volume removal occurred during QA execution. Successful original runs were not repeated. Все восемь принадлежащих аудиту контейнеров `cvortexqa1011` остановлены после сохранения evidence; containers/volumes не удалены. Primary service IDs, running state and start times unchanged. Resource disposition and final preservation validation: [checkpoint](evidence/full-2026-10-11/recovery-checkpoint.json), [resource-stop.json](evidence/full-2026-10-11/resource-stop.json), [document-validation.json](evidence/full-2026-10-11/document-validation.json). QA volumes/temp private artifacts retained for recovery. No remediation started.
