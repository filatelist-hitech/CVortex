# CVortex — проверенное текущее состояние

## QA-002 & QA-003 — Harness Safety, Aggregate Relations & Timestamp-Tie Negative Control — 2026-10-25

Mode: IMPLEMENT; branch: `fix/qa-002-qa-003-safety-negative-control`; target: `stage`. One agent / sequential execution.

### Completed Outcomes
1. **QA-002: Lifecycle Safety, Aggregate Relations & Evidence Validation**:
   - `R1 MAJOR`: `scripts/vacancy_migration_harness.py::resources()` дополнен флагом `-a` для `docker container ls`, гарантируя учёт остановленных контейнеров проекта при инвентаризации, валидации меток владения (`cvortex.qa002.run`) и контроле чистоты ресурсов при cleanup. Добавлен юнит-тест `test_resources_lists_all_containers_including_stopped`.
   - `R2 MAJOR`: `scripts/fixtures/vacancy-duplicate-migration/reconciled.sql` дополнен строгой проверкой FK и aggregate mapping `vacancy → snapshots → analyses → LLM runs` (проверка соответствия каждого снимка его вакансии и `source_url`, каждого анализа — снимку и вакансии, LLM-прогона — снимку). В `vacancy_migration_harness.py` добавлен отрицательный контроль `QA002-14`, проверяющий, что ошибочная перепривязка снимка к чужой вакансии того же владельца вызывает немедленный сбой проверок и откатывается транзакцией.
   - `R3 MINOR`: `scripts/qa_evidence.py` дополнен валидатором `validate_qa002`, выполняющим строгую проверку JSON, соответствие SHA-256 файлов `log-exports.json` (включая разархивирование raw gzip), валидацию структуры `exact-runs.json`, неизменность `development-preservation.json` и целостность ссылок в `README.md`. Добавлены тесты целостности в `scripts/test_qa_evidence.py`.
   - Запуск полного lifecycle harness `scripts/test-vacancy-postgres-duplicate-migration.sh --regression-controls` в гарантированно изолированном disposable Compose-окружении завершён успешно (PASS, exit 0, чистый cleanup).

2. **QA-003: Deterministic Timestamp Ordering Negative Control & Latest Operation**:
   - В `apps/backend/tests/Feature/DiagnosticsPostgresTest.php` (`test_runtime_role_uses_id_to_break_latest_occurrence_timestamp_ties`):
     - Физический порядок обновления кортежей инвертирован относительно лексикографического порядка ULID: запись с большим ID обновляется первой, а с меньшим — второй, получая больший TID в таблице PostgreSQL.
     - Добавлен строгий negative control: прямой SQL-запрос `ORDER BY created_at DESC LIMIT 1` без условия `id DESC` возвращает более старую запись (`$smallerId`), доказывая, что отсутствие `id DESC` приводит к получению неверного результата. Аналогичный отрицательный контроль добавлен для детального списка occurrences.
     - Добавлена проверка tie-breaker для поля `latest_operation` (`operation_newer` vs `operation_older`).
     - Проведено мутационное тестирование (удаление `id DESC` из `DiagnosticsController` вызывает немедленный FAIL тестов), подтверждена защита от регрессий.
     - Выполнение теста подтверждено на изолированном PostgreSQL 18.6 (5/5 PASS, 58 assertions, 0 SKIP).

Status: QA-002 и QA-003 **PASS / RESOLVED**.



## QA-004 — Career Claim resolution race conditions eliminated (PR #53) — 2026-10-25

Mode: IMPLEMENT; branch: `fix/qa-004-claim-resolution-race-conditions`; target: `stage` (PR #53 squashed and merged as commit `679c3fcdca1effe26c253a4f3052b275c6c3abe9`). One agent / sequential execution.

### Completed Outcomes
1. **Request Sequencing & Invalidation (`apps/frontend/src/app/career-workspace.tsx`)**:
   - Monotonic request tracking (`requestId` / `latestAppliedRequestIdRef`) ignoring stale GET responses.
   - Mutation epoch tokens (`mutationEpochRef`) dropping any GET responses initiated before an explicit resolution PATCH.
   - Local resolution invariant cache (`resolvedClaimsRef`) protecting resolved claim statuses against lagging server snapshots (preventing regression from resolved state back to `USER_RESOLUTION_REQUIRED` and preventing stale `PASS` from overwriting newer `BLOCK`).
   - `AbortController` cancellation for in-flight GET requests upon new fetch, resolution mutation, user switch, or component unmount.
   - Mutex coordination between active mutations (`activeMutationsRef`) and background polling, pausing polling during PATCH operations.
   - User switch isolation (`activeUserIdRef`) and component unmount safety (`isMountedRef`), ensuring responses and resolution callbacks are never applied to unmounted components or across different user sessions.
   - `apps/frontend/src/app/access-shell.tsx`: passed `userId` and `key={user.id}` to `CareerWorkspace`; permitted `AbortError` to pass through without generating spurious `NETWORK_ERROR` toasts.
   - `apps/frontend/src/app/career-claim.tsx`: added `onMutating` notification callback and guarded `onResolved` against claim ID mismatch or unmount.

2. **Verification & Regression Coverage**:
   - **Frontend Vitest (`apps/frontend/src/app/__tests__/career-claim.test.tsx`)**: 10/10 test files (142/142 tests) PASS. Added deterministic `deferred<T>()` promise tests covering RACE-01 through RACE-08:
     - RACE-01: Stale GET completing after successful PATCH does not revert resolution.
     - RACE-02: Stale GET with PASS does not overwrite newer BLOCK.
     - RACE-03: Out-of-order GET responses are ignored; only latest sequence applies.
     - RACE-04: Failed PATCH handles error cleanly without false success state.
     - RACE-05: Claim/context switch during in-flight request ignores old context.
     - RACE-06: Background polling does not collide with or revert in-flight mutations.
     - RACE-07: Unmount prior to request completion does not commit state.
     - RACE-08: User switch drops pending requests from previous user session.
     - Truth Guard negative test verifying unresolved ambiguous claims announce downstream resolution requirement.
   - **Backend Feature Test (`apps/backend/tests/Feature/ClaimResolutionUiTest.php`)**: added `test_unresolved_ambiguous_claim_blocks_downstream_matching_until_explicit_resolution` verifying downstream matching (`TrustedCareerQuery::forMatching`) remains empty until explicit PATCH resolution. 83/83 backend feature tests PASS (689 assertions, isolated SQLite container).
   - **Static Quality & Build**: ESLint (0 errors), TypeScript (`tsc --noEmit`, 0 errors), Next.js production build (6/6 static pages), Laravel Pint (0 issues), PHPStan (`--memory-limit=512M`, 0 errors).
   - **Remote CI**: PR #53 passed `Governance/PR contract`, `Governance/Roadmap metadata`, and `Quality/m0-quality`.

Status on `stage`: QA-004 is **PASS / RESOLVED**.


## Playwright E2E UI Coverage & Quality Automation — 2026-10-24

Mode: IMPLEMENT; branch: `chore/playwright-e2e-coverage`; task: `.agents/tasks/playwright-e2e-coverage.md`. One agent / sequential execution.

### Completed Outcomes
1. **Playwright Automation Infrastructure (`apps/frontend/`)**:
   - Configured `playwright.config.ts` targeting local Docker Compose stack (`http://127.0.0.1:8080`) with Desktop Chromium (1440×900) and Mobile Chrome (Pixel 7 emulation, 393×851).
   - Added npm test scripts: `test:e2e`, `test:e2e:smoke`, `test:e2e:critical`, `test:e2e:ui`, `test:e2e:report`.
   - Built reusable test harness and fixtures (`apps/frontend/e2e/fixtures/test-fixtures.ts`) with synthetic run-scoped auth (`qa-*@test.cvortex`), automated WCAG 2.1 AA audits via `@axe-core/playwright`, and role-based helpers.
   - Configured CI job `e2e-tests` in `.github/workflows/quality.yml` with host dependency installation, Chromium headless test execution, and Playwright report artifact uploading.

2. **Test Suites & Verification Results (64 tests across 8 suites, 100% PASS)**:
   - `e2e/auth/login.spec.ts`: 10/10 PASS (Desktop + Mobile) — form rendering, valid auth, invalid password safe failure, nonexistent user handling, HTML5 boundary validation, session persistence, logout flow.
   - `e2e/auth/register.spec.ts`: 6/6 PASS — invite-only gate enforcement, successful registration with valid token, invalid token rejection, password mismatch validation.
   - `e2e/auth/access-control.spec.ts`: 8/8 PASS — role-based route access, admin-only Error Center diagnostics, regular user 403 denial, unauthenticated visitor login redirect.
   - `e2e/career/career-facts.spec.ts`: 8/8 PASS — career workspace panels, manual fact creation, fact review/confirmation, extraction provider error handling with safe localized messaging.
   - `e2e/vacancies/vacancies.spec.ts`: 8/8 PASS — vacancy workspace rendering, vacancy creation via paste, client-side validation on empty input, Truth Guard invariant enforcing human approval separation before AI draft approval.
   - `e2e/security/cross-user-isolation.spec.ts`: 6/6 PASS — multi-user career facts isolation, vacancy isolation, prompt injection resistance without privilege escalation.
   - `e2e/diagnostics/diagnostics.spec.ts`: 6/6 PASS — admin incidents log, detailed sanitized technical context, status update to RESOLVED.
   - `e2e/responsive/responsive-navigation.spec.ts`: 6/6 PASS — mobile navigation drawer, 390px viewport without horizontal overflow, 320px compact layout preservation.
   - `e2e/accessibility/a11y-audit.spec.ts`: 6/6 PASS — automated axe-core WCAG 2.1 AA scans on auth and career pages, modal focus trap, Escape key dismissal.
   - Vitest component/unit tests: 10/10 files (133/133 tests) PASS without regressions.
   - TypeScript (`tsc --noEmit`) and ESLint (`eslint`): 0 errors.

3. **Documentation Delivered**:
   - `docs/09-QA/playwright-coverage-matrix.md`: 30+ scenarios across 8 areas mapped to priority (P0–P2), type, route, and coverage status.
   - `docs/09-QA/playwright-architecture.md`: layout, fixtures, browser targets, configuration, and invariants.
   - `docs/09-QA/playwright-test-strategy.md`: Truth-first, multi-user isolation, deterministic AI boundaries.
   - `docs/09-QA/playwright-local-setup.md`: prerequisites, local test running instructions, environment overrides.
   - `docs/09-QA/playwright-ci.md`: GitHub Actions workflow, artifact retention, timeout policies.
   - `docs/09-QA/playwright-known-gaps.md`: honest audit of unimplemented UI features (file uploads, URL scraping, Career Tracks UI, Settings BYOK, Interview prep, Document export).

---

## Post-Merge Targeted Regression — stage SHA 8dce209b7c1c86747753a97083dcf3eb0360db3f, 2026-10-24

Mode: VERIFY ONLY / DOCUMENTATION ONLY; one agent/sequential. Проверенный HEAD ветки `stage`:
`8dce209b7c1c86747753a97083dcf3eb0360db3f` (синхронизирован с `origin/stage`).
Все четыре PR #47 (QA-001), #50 (QA-004), #49 (QA-003) и #48 (QA-002) объединены в `stage`.
GitHub Actions CI Quality (`m0-quality`) и Governance checks (`PR contract`, `Roadmap metadata`)
для данного SHA завершились успешно (PASS).
Фактическая независимая проверка на интегрированном дереве установила, что объединение в stage
не устранило открытые дефекты автоматически:

- **QA-001 (B01 OFL integrity): PASS.**
  Канонический checker `node docs/07-Design/reference/build-reference.cjs --check` PASS (exit 0);
  10/10 тестов Node (`build-reference.test.cjs`, включая QA001-01…08) PASS; 8/8 Python evidence
  regressions PASS. Нормализованный `OFL.txt` (4401 байт, 0 CRLF, 1 хвостовой пробел удалён)
  соответствует утверждённой политике `lf-trim-trailing-horizontal-whitespace`. Stored SHA-256
  (`18a4de52...`) отделён от upstream SHA-256 (`564ce565...`). Исходный hash mismatch устранён.
  Сохраняются открытыми non-blocking review findings: `REVIEW-QA001-01` (покрытие guard-веток
  постоянными параметризованными тестами) и `REVIEW-QA001-02` (документационное разграничение
  snapshot integrity и upstream authenticity в README). Локальная snapshot integrity не доказывает
  подлинность upstream.

- **QA-002 (Migration harness boundary & isolation): BLOCKED.**
  Селективный выбор миграций до границы `000008` реализован, 7/7 unit-тестов harness PASS.
  Unit-тесты не доказывают безопасность полного lifecycle. Критические замечания независимого
  ревью остаются открытыми:
  - `R1 MAJOR`: инвентаризация `inspect_owned` использует `docker container ls -q` без `-a`,
    пропуская остановленные контейнеры, что блокирует cleanup или пропускает неверный token;
  - `R2 MAJOR`: assertions в `reconciled.sql` проверяют только скалярные counts/статусы,
    но не проверяют полные FK-связи `vacancy → snapshots → analyses → LLM runs` и не содержат
    negative control для предотвращения ошибочной перепривязки к другому агрегату того же владельца;
  - `R3 MINOR`: verifier `scripts/qa_evidence.py` не проверяет QA-002 evidence package.
  PostgreSQL lifecycle execution заблокирован (`BLOCKED`) до устранения проблем безопасности
  harness и подтверждения disposable isolation (на хосте активен `cvortex-postgres-1` с `cvortex_test`).

- **QA-003 (Deterministic timestamp ordering): BLOCKED.**
  Разделение объекта `LlmProviderException` на два экземпляра устранило сбой WeakMap deduplication
  в фикстуре `DiagnosticsPostgresTest::test_runtime_role_uses_id_to_break_latest_occurrence_timestamp_ties`.
  Однако регрессионный тест пока не гарантирует обнаружение удаления обязательного `id DESC`:
  SQL-контроль показал, что при одинаковых timestamps физический порядок строк в PostgreSQL
  совпадает с порядком ID, создавая риск ложного PASS; строгий negative control отсутствует.
  Timestamp-tie для `latest_operation` не проверяется (LOW). В CI используется SQLite,
  поэтому PostgreSQL-специфичный тест пропускается (`SKIP`). Успешный CI не подтверждает
  прохождение этого теста на PostgreSQL. Выполнение на изолированном PostgreSQL не подтверждено
  (`SKIP` не является `PASS`).

- **QA-004 (Career Claim resolution UI & Truth Guard): FAIL.**
  Компонент эксплицитного выбора подтверждающего факта добавлен (`CareerClaim.tsx`, 25/25 component
  tests PASS). Backend Truth Guard downstream enforcement работает: unresolved Claim блокирует
  генерацию на уровне API (`422 Unprocessable` / `stale` / `BLOCK`, 12/12 `ClaimResolutionUiTest`,
  19/19 `ApplicationDraftTest` PASS [MOCKED]).
  Однако в `career-workspace.tsx` обнаружен открытый блокирующий дефект состояний гонки (RACE-01–RACE-08):
  функция `refresh()` выполняет несинхронизированный GET и затирает стейт (`setOverview`).
  Устаревший GET после PATCH resolution возвращает UI к `USER_RESOLUTION_REQUIRED` (RACE-01);
  устаревший GET с PASS перезаписывает более новый BLOCK (RACE-02); фоновый polling перекрывает
  PATCH (RACE-06); размонтирование производит недопустимый commit (RACE-07).
  Коммит `b09a081` добавил лишь обработку `RESOURCE_NOT_FOUND` и memoize, но саму проблему гонок не устранил.
  Статус UI-компонента и интеграции — FAIL.

- **Cross-task integration: FAIL.**
  Сквозной пользовательский сценарий `AMBIGUOUS CLAIM → USER_RESOLUTION_REQUIRED → DOWNSTREAM BLOCK → EXPLICIT RESOLUTION → TRUTH GUARD REVALIDATION → GENERATION → HUMAN APPROVAL → REFRESH → PERSISTENCE`
  на backend boundary работает с synthetic/mock данными (`PASS [MOCKED]`), но на уровне UI
  подвержен регрессии при устаревшем ответе GET/polling (`FAIL`). Полный end-to-end сценарий не прошёл.

- **Release Gates и ограничения:**
  - Production Accessibility Release Gate (ADR-0025, K1/Z1/A1): `OPEN / BLOCKED`.
  - Preview 0.1 → M2 Gate: `BLOCKED` (реальный пользовательский сценарий не подтверждён).
  - Реальные интеграции с OpenAI API и ChatGPT OAuth не подтверждены (`NOT_VERIFIED / BLOCKED`).
  - Все выполненные тесты AI провайдеров маркированы как MOCKED и не доказывают работу реального LLM.

## QA-003 — diagnostics timestamp-tie fixture repair, 2026-10-12

Explicit bounded owner IMPLEMENT override of
`NEXT=b01-current-manifest-integrity-reconciliation`; native write preflight PASS.
Base/target `stage`, branch `fix/qa-003-diagnostics-fixture`, one agent/sequential.
NEXT and BLOCKERS are unchanged; no other QA repair or full audit.

Baseline `5cfe18d401c056eefb179f722d875c3b1acf7522` reproduced expected 2/actual 1.
Validated code SHA `10d9b575b63cd44ad183af8287c322d4ff804191`: two distinct
RATE_LIMITED exceptions create two occurrences in one fingerprint; equal timestamps
select greater ID with latest provider/request/attempt/retry metadata. Dedicated
PostgreSQL same-object regression preserves one occurrence/count. Production
recorder/controller/migrations and WeakMap behavior are unchanged.

Owned PostgreSQL 18.6/tmpfs/internal network; runtime role NOSUPERUSER/NOBYPASSRLS.
Exact test 1/17 PASS; runtime Diagnostics suite 5/50 PASS; targeted Diagnostics
on PostgreSQL as isolated admin 57/575 PASS; zero skips, exit 0. USER list/detail/update
403, audit rollback and redaction PASS. Pint, PHPStan, diff checks PASS. After each
suite users/incidents/occurrences/audit return to zero; owned cleanup verified.
Setup and LOG_LEVEL invocation failures remain in new evidence alongside the
successful rerun; historical failure logs/accounting were not rewritten.
[SHA/commands/environment/results](../../docs/09-QA/evidence/qa003-2026-10-12/README.md).
Locally verified; PR delivery/CI is recorded in the final report. No merge. STOP.

## QA-004 — Career Claim resolution UI, 2026-10-12

Bounded IMPLEMENT, explicit owner override of NEXT. Base `origin/stage` = `5cfe18d`;
separate branch `fix/qa-004-career-claim-resolution-ui` and managed worktree.
Career now exposes explicit supporting-fact selection, existing PATCH, controlled
errors/retry and backend-authoritative result. No production backend/schema changes.

Local validation: frontend 133 tests, lint/typecheck/production build PASS;
Career/Claim API 52 tests / 417 assertions and Application 19 / 253 PASS
(SQLite in-memory), targeted Pint/PHPStan PASS. Browser: isolated PostgreSQL
`cvortex_qa004_synthetic`, runtime role `qa004_app` without superuser/BYPASSRLS,
Redis/Horizon + deterministic local provider; 15 scenarios PASS, including actual
keyboard resolution, persistence, reanalysis → generation → explicit acceptance/
approval, CSRF419 and cross-owner404. Responsive 320/390/768/1440 smoke PASS.
[Task](../tasks/qa-004-career-claim-resolution-ui.md),
[evidence/limits](../../docs/09-QA/evidence/qa004-2026-10-12/README.md).

QA-004 FIXED / locally verified, awaiting PR review/integration. NEXT/BLOCKERS unchanged;
QA-001–003, live-provider readiness, Preview 0.1 and manual accessibility gates
remain outside scope. No development DB writes, automatic approval/submission or merge.
STOP at QA-004; the next intended task remains `b01-manual-accessibility-release-gate`.

## QA-002 — deterministic migration harness, 2026-10-12

Explicit owner IMPLEMENT override of `NEXT=b01-current-manifest-integrity-reconciliation`;
base/target `stage`, branch `fix/qa-002-migration-harness`, one agent/sequential.
Native Git write preflight with task + `--user-override` PASS. NEXT remains unchanged.

The test selects paths through `000008`, checks actual pre-000009 schema, seeds
legacy duplicate history, applies `000009`–`000012`, checks data/status/owner/schema
invariants, and rolls back/reapplies only those named repairs before later migrations.
Every invocation owns a fresh Compose project, PostgreSQL container, database,
ephemeral credentials, volumes and internal network. External targets are blocked;
cleanup checks ownership and cannot silently PASS on failure.

Local PostgreSQL current-set run PASS (28/28 Ran); separate future-migration and
wrong-schema controls PASS. Seven harness unit tests, shell/Python syntax and
scoped diff checks PASS. Counts `1/8/10/5/3 → 1/5/10/5/3`; history retained,
owned cleanup verified. Read-only schema/count/row digests for existing `cvortex`,
`cvortex_test`, `cvortex_dev2` unchanged. Product migrations, QA-001/003/004 and
historical QA logs/accounting are preserved. [Evidence](../../docs/09-QA/evidence/qa002-2026-10-12/README.md).
Task is locally verified; governed PR delivery/CI is recorded in the final report.
Existing B01/Preview/provider/M2 blockers are unchanged. No merge. STOP.

## QA-001 B01 integrity — local implementation verified, 2026-10-12

Owner explicitly authorized IMPLEMENT, one agent/sequential, scoped commit/push/PR;
base stage `5cfe18d401c056eefb179f722d875c3b1acf7522`, branch
`fix/qa-001-b01-manifest-integrity`, isolated managed worktree. NEXT matched;
no task spec existed at launch, owner prompt used as bounded authority and recorded
in [new task spec](../tasks/b01-current-manifest-integrity-reconciliation.md).

Current canonical `--check` PASS and 10/10 Node tests PASS (QA001-01…08).
Pinned upstream fetched/rehashed: 4495 bytes/93 CRLF; tracked/worktree OFL:
4401 bytes/LF, unchanged. Git normalizes CRLF but does not remove the separately
observed trailing space. Manifest mixed upstream and stored-byte hashes.
Policy A retains LF/trimmed storage and separate upstream metadata/fixture;
check/write verify provenance before manifest generation. Only four generated
asset hashes change, with no token/source/rendering or unrelated asset changes.

[Current defect status and evidence](../../docs/09-QA/QA-001-RECONCILIATION.md).
Historical QA-001 FAIL, register and evidence manifest stay byte-for-byte intact;
new status supersedes their dated outcome. QA-002–QA-004 are not addressed.
First remote Quality run failed because the Python test sandbox omitted the new
linked task; its fixture helper now copies that task. Final-tree Python evidence
regressions 8/8 PASS; original CI failure retained. Independent PR review pending;
technical result is not independent review PASS.
B01 owner acceptance remains; full manual matrix/K1/Z1/A1 **NOT TESTED / OPEN**,
production release blocked. NEXT names the existing manual gate after QA-001
review/integration; this task stops without executing it.

## PR #46 evidence remediation candidate — 2026-10-12

Explicit owner override, primary mode IMPLEMENT, существующая PR branch `docs/full-functional-qa-2026-10-11`. Исправлены механизм manifest LF export, historical false-PASS accounting, mapping восьми реализованных workflows, export provenance и Documentation Map. Career verification helpers теперь проверяют реального победителя confirm/reject, owner/Claim/evidence и требуют успешной финальной проверки; exceptions/incomplete output не становятся PASS. Product code/ADRs/schema, QA-001–QA-004 и RCV-01 не изменены.

Executed validation: isolated PostgreSQL race PASS (confirm winner); targeted backend 51 tests / 358 assertions PASS, включая 11 новых invariant datasets; Python evidence regression 8 tests PASS, wrapper regression 2 tests PASS; targeted Pint, PHPStan и Bash syntax PASS. Raw logs/source hashes/commands — [remediation-runs](../../docs/09-QA/evidence/full-2026-10-11/remediation-runs.json). Manifest/JSON/XML/accounting verification выполняется canonical `python3 scripts/qa_evidence.py`; подробности — [PR46 remediation](../../docs/09-QA/PR46-REMEDIATION.md). Независимый review и Git delivery относятся к последующему frozen candidate gate; отчёт хранится вне дерева, не подменён self-review.

Исторические 432 records / 539 weighted units сохраняют 525 PASS / 5 FAIL / 7 BLOCKED / 2 NOT_RUN. Новый isolated run не стирает historical verifier failure. `NEXT` и `BLOCKERS` не менялись; production accessibility, real provider/Preview и M2 gates остаются открыты.

## PR #46 QA audit integration — 2026-10-11

PR #45 MERGED в `stage`; интегрированный Recovery HEAD — `ecf65f7f20224544beabbd6df7aadb69d7cc352a`. Сохранённый functional QA относится к исходному baseline `f06a6dba058589823bf3d3c8b693cb625170bc4b`; Recovery update был документационным и не менял product code. Отчёты и evidence сохранены без повторного полного QA Audit.

QA ledger содержит **432 records / 539 weighted units**: **525 PASS, 5 FAIL, 7 BLOCKED, 2 NOT_RUN**. Bounded inventory: 67 функций/контролей, 59 проверены; 54 PASS, 4 FAIL, 7 BLOCKED, 2 NOT_RUN. Вердикт аудита — **CHANGES REQUIRED**. QA-001–QA-004 остаются открыты. Пятый historical FAIL — Career concurrency final verifier; его verification helpers исправлены отдельным bounded remediation ниже, historical log не переписан. Состав результатов и ограничения — в [audit report](../../docs/09-QA/FULL-FUNCTIONAL-AUDIT-2026-10-11.md), детализация — в [coverage matrix](../../docs/09-QA/FUNCTIONAL-COVERAGE-MATRIX.md), проверяемые хэши — в evidence manifest.

Это отдельный результат от RCV-01 Recovery: Recovery closeout сохранён как **DONE_VERIFIED в локальном scope**, а функциональный QA — **CHANGES REQUIRED**. Текущий `NEXT` остаётся `b01-current-manifest-integrity-reconciliation`; `BLOCKERS.md` и Recovery gate не изменялись этой QA-интеграцией. Product code, migrations и QA-001–QA-004 не менялись; remediation затрагивает test/verification helpers.

## Recovery snapshot — 2026-10-11

Следующие checkout/Git/runtime формулировки — датированный Recovery snapshot, не live PR #46 head.
Последняя проверка Recovery: **2026-10-11, Europe/Moscow**.
Основной audited checkout: `stage`, HEAD
`f06a6dba058589823bf3d3c8b693cb625170bc4b`, совпадает с live remote stage.
Последний блок: **IMPLEMENT / RCV-01, DONE_VERIFIED в локальном scope**.
Предыдущий recovery audit: DOCS; его dated evidence сохранено без переписывания.
[RCV-01 Execution Report](LOCAL_RUNTIME_ALIGNMENT_REPORT.md) — текущие runtime checks.

[Recovery Report](PROJECT_RECOVERY_REPORT.md) — подробные evidence, матрицы и
ограничения. [Git snapshot](recovery-2026-10-11/git-snapshot.json),
[validation](recovery-2026-10-11/validation.json).

## Текущая фаза

**M1 First Value → незакрытый Preview 0.1.** M1.4:
**IMPLEMENTED_UNVERIFIED по полной пользовательской приёмке**.
Сохранённые recommendations/short+standard cover drafts, Truth Guard,
edit/revalidation и approval integrated; real-user E2E/feedback не зафиксированы.
M2 planning/implementation и MVP 0.2 не начаты и остаются за Preview gate.
Никаких процентов готовности: acceptance checkpoints ещё не закрыты.

## Завершённые результаты и границы

- Foundation 00–07: исторически завершённые reviewed contracts/research/design;
  PR/merge и deliverables перечислены в Recovery Report. Не равны runtime
  реализации всего conceptual ERD/AI design.
- M0: integrated runnable stack, свежие Compose/health/Horizon PASS.
- M1.1/M1.2/M1.3: integrated slice scopes, historical acceptance/PG proof и
  свежие targeted SQLite tests. Реальный authenticated user flow здесь NOT_RUN.
- M1.4: PR #31 merged, preparation/draft lifecycle тестируется;
  полного Preview PASS нет.
- Observability и ChatGPT plan/MCP extension integrated через #36/#37/#42;
  local schema mismatch закрыт RCV-01; provider/account readiness не проверена.

## Проверки предыдущего recovery audit

- Frontend: **108 tests / 9 files PASS**, ESLint PASS (0 warnings), TypeScript PASS.
- Backend canonical targeted invocation: **210 tests / 1396 assertions**,
  **1 PostgreSQL-only skip**. SQLite in-memory; не PostgreSQL RLS/race proof.
- Первичный backend run с принудительным APP_ENV=testing: FAIL на CSRF419/201;
  canonical environment recheck и rerun PASS. Ошибка invocation сохранена.
- Compose example config PASS; public live/ready/root 200, unauthenticated me401;
  Horizon running. **Readiness не подтверждает полноту schema.**
- 27 migrations Ran, 1 Pending; `chatgpt_connections.oauth_generation` отсутствует.
  `ConnectionService` использует поле. Миграции здесь не выполнялись.
- Reference transformer 2/2 PASS; React derivation 260 checks PASS.
  **Canonical manifest FAIL**: один OFL.txt hash mismatch, текущие tokens/source
  hashes не менялись. Причина drift ещё не доказана.
- Exact stage SHA remote Quality SUCCESS
  ([run](https://github.com/filatelist-hitech/CVortex/actions/runs/38153409400)).
  Это remote CI, не новое local build/PG/manual/user evidence.

## RCV-01 — выполнено 2026-10-11

- Явно разрешён `cvortex_test` как текущая локальная development DB: owner override
  запрета test DB в исходном task. Docker Desktop Unix socket, project `cvortex`,
  APP_ENV=local, фактические roles `cvortex_app` / `cvortex` проверены.
- Private backup вне Git: 226093 bytes, modes 0700/0600; полное archive decoding,
  TOC и SHA-256 recheck PASS. Restore в БД не выполнялся.
- Только existing migration `2026_10_07_000001_add_oauth_generation_to_chatgpt_connections`
  применена с точным --path через migration role: **28/28 Ran**, batch 5;
  `oauth_generation bigint NOT NULL DEFAULT 0`, existing connections 2/2 generation=0.
- 35 data tables / 302 existing rows: counts и row fingerprints совпали;
  schema-only diff — одно additive поле. RLS/policies/ACL/старые constraints сохранены.
  Initial metadata verifier FAIL учёл новый NOT NULL constraint как drift;
  исправленная проверка и независимый schema diff PASS.
- ChatGptPlanTest: **16 tests / 92 assertions PASS**, SQLite :memory:, mocked HTTP,
  stray requests запрещены. Public live/ready/root 200, unauthenticated me401,
  Horizon running. Это не live ChatGPT usability/Preview acceptance.
- Production DB, product code, infrastructure/config и другие БД не изменялись;
  live AI/OAuth, commit/push/PR не выполнялись.

## Активные workstreams

| Workstream | Состояние | Открытый gate |
| --- | --- | --- |
| Core M1 / Preview | Code integrated, full acceptance unverified | Real-user E2E/feedback; provider readiness |
| B01/B02 design | B01 owner ACCEPTED; B02 + React port integrated #44 | QA-001 local PASS, independent review pending; mandatory manual production accessibility |
| ChatGPT/MCP | #42 merged; mocked tests PASS | Local schema aligned RCV-01; no fresh external account/provider proof |
| Recovery control | Report/state/dashboard/spec prepared locally | Отдельная authorized documentation delivery, если нужна integration |

B01 принят по **ADR-0025**; owner visual acceptance сохраняется.
Manual full matrix, K1 keyboard, Z1 native200% zoom, A1 audible screen reader
**NOT TESTED / OPEN**. Production release **BLOCKED** до закрытия существующей
`b01-manual-accessibility-release-gate`. Synthetic demo и 239 historical visual
comparisons не закрывают этот gate. Отдельный approved Universe stream не найден;
внешние материалы/Figma NOT_VERIFIED. B03 не разрешён.

## Git

PR #44 **MERGED** 2026-10-11; #42 MERGED, 59/59 threads resolved;
#43 CLOSED/superseded, не merged отдельно. Единственный OPEN #34 конфликтует,
governance FAIL; его старые env/egress изменения преимущественно есть в stage.
`main` не promoted к текущему продукту. Ни cleanup, ни новая Git delivery здесь
не выполнялись. Исходные 802 untracked files и dirty соседний
`CVortex-chatgpt-plan-chat` worktree сохранены.

Документационные изменения этого блока находятся **только** в
`/Users/filatelist/Documents/it/CVortex-project-recovery`, branch
`docs/project-recovery-2026-10-11`, от audited HEAD. Исходный checkout остаётся
на stage без tracked изменений. Нет commit/push/PR; это локальный recovery result.

## Одна главная следующая задача

**`b01-manual-accessibility-release-gate`** — следующая intended task после
независимого review/integration QA-001. Current integrity проверен локально;
manual state/viewport, keyboard, native200% zoom и human screen reader остаются
OPEN/NOT TESTED. Нужен named human evidence; owner visual acceptance не заменяет
его. Preview/provider/M2 gates остаются отдельными. STOP без перехода к gate.

## История

Прежний STATUS сохранён byte-for-byte в
[STATUS-HISTORY-2026-10-11](STATUS-HISTORY-2026-10-11.md).
Это dated evidence, **не канонический текущий статус или NEXT**.
Следующая сессия читает этот STATUS, NEXT, BLOCKERS и task, как уже предписано AGENTS.

**STOP:** QA-001 проверен локально и передаётся на независимый review; следующая task не выполнялась.
