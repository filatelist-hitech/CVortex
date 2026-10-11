# CVortex — проверенное текущее состояние

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
Independent PR review pending; technical result is not independent review PASS.
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
