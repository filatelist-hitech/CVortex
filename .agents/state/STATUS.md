# CVortex — проверенное текущее состояние

Последняя проверка: **2026-10-11, Europe/Moscow**.
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
| B01/B02 design | B01 owner ACCEPTED; B02 + React port integrated #44 | Current manifest integrity; mandatory manual production accessibility |
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

**`b01-current-manifest-integrity-reconciliation` — согласование текущего B01 manifest / OFL.txt provenance.**
RCV-01 закрыт; blocker current B01 integrity остаётся. Отдельная спецификация этого
следующего bounded scope ещё не подготовлена; требуется отдельный owner launch.
NEXT — указатель намерения, не разрешение продолжать после текущего STOP.
Не переписывать historical reports/goldens. Затем existing manual accessibility
release gate; Preview/provider verification и M2 остаются отдельными gates.

## История

Прежний STATUS сохранён byte-for-byte в
[STATUS-HISTORY-2026-10-11](STATUS-HISTORY-2026-10-11.md).
Это dated evidence, **не канонический текущий статус или NEXT**.
Следующая сессия читает этот STATUS, NEXT, BLOCKERS и task, как уже предписано AGENTS.

**STOP:** RCV-01 завершён проверенным локальным результатом; следующая task не выполнялась.
