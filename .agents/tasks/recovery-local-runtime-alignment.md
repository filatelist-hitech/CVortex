---
title: RCV-01 — Local runtime schema alignment
status: completed-local-verified
owner: project
created: 2026-10-11
updated: 2026-10-11
tags: [task, recovery, local-runtime, schema-verification]
execution:
  workflow: implementation
  orchestration: native
  agents: 1
  parallelism: false
---

# RCV-01 — Согласование локальной схемы с интегрированным кодом

## Goal

Привести существующую **локальную development DB** к уже интегрированному
ChatGPT connection contract и проверить результат без нового продукта,
потери данных или live AI/OAuth запросов. Наблюдаемый результат:
`chatgpt_connections.oauth_generation` существует, соответствующая миграция
`Ran`, целевые тесты и техническая readiness проходят.

## Context

[Recovery Report](../state/PROJECT_RECOVERY_REPORT.md) на HEAD `f06a6db` подтвердил:
health ready 200 и Horizon running, но
`2026_10_07_000001_add_oauth_generation_to_chatgpt_connections` Pending,
`Schema::hasColumn` false. `ConnectionService` читает/увеличивает/сохраняет
`oauth_generation`; health endpoint полноту schema не проверяет.
Это состояние runtime, а не отсутствующая новая функция.

Эта спецификация **не выполнена** в recovery session. Сам файл не разрешает
DB mutation: требуется явный запуск владельцем с разрешением применить эту
существующую миграцию **только к проверенной локальной development DB**.
Без такого разрешения можно выполнить read-only preflight и подготовить
конкретный план; DB write остаётся NOT_RUN. Production migrations запрещены.

## Source of Truth

- [Root AGENTS](../../AGENTS.md), [PROJECT](../../PROJECT.md), текущие
  [STATUS](../state/STATUS.md), [NEXT](../state/NEXT.md), [BLOCKERS](../state/BLOCKERS.md).
- [Implementation workflow](../workflows/implementation.md),
  [Git policy](../policies/git-workflow.md), resource/security policies.
- [ADR-0023](../../docs/03-ADR/ADR-0023-chatgpt-plan-chat.md),
  ADR-0002/0005/0007/0019; принятые решения не меняются.
- [Existing migration](../../apps/backend/database/migrations/2026_10_07_000001_add_oauth_generation_to_chatgpt_connections.php),
  [ConnectionService](../../apps/backend/app/AI/ChatGpt/ConnectionService.php),
  [ChatGptPlanTest](../../apps/backend/tests/Feature/ChatGptPlanTest.php).
- [Local Development](../../docs/10-Operations/Local-Development.md),
  [ChatGPT Plan setup](../../docs/10-Operations/ChatGPT-Plan-Chat.md).

## Scope

1. Проверить фактический checkout/HEAD/remote integration и dirty changes.
   Не полагаться на старый SHA; при новом HEAD пересмотреть только затронутые факты.
2. Определить текущую Compose project/container identity, database mapping,
   migrations status и schema metadata без раскрытия credentials/private rows.
3. Подтвердить, что target — локальная data-bearing development DB и миграция
   действительно Pending. Чужие/test/production БД не трогать.
4. При разрешённой mutation сохранить защищённый локальный backup вне Git,
   проверить его создание/целостность, записать лишь безопасную metadata.
   Не печатать backup или содержимое пользовательских таблиц.
5. Применить **только уже существующую pending миграцию** через существующий
   migration-role workflow. Предварительно проверить точный migration path и
   ожидаемый additive column/default change. Не использовать `migrate:fresh`,
   reset/rollback/drop и не прогонять случайные другие pending migrations.
6. Проверить Ran/column/default и сохранность существующих данных по безопасным
   агрегатам/metadata без PII, не читая token values.
7. Выполнить целевые mocked/SQLite checks и health checks; live ChatGPT inference,
   OAuth refresh, disconnect реальных connections или vacancy retry не нужны.
8. Записать результаты в разрешённых docs/state, отдельно от manual/Preview gates.

Repository writes — только на bounded branch от проверенного stage и после
`bash scripts/check-agent-contract.sh implementation .agents/tasks/recovery-local-runtime-alignment.md --write`.
Если recovery docs ещё не integrated, использовать recovery checkout как источник
спецификации и сохранить его изменения; не reset/stash/copy-over dirty work.
Обычный commit/PR/push — только по отдельному явному поручению.

## Non-goals

Нет новых migrations/schema design, правок product code, dependency installs,
infrastructure recreation, credentials changes, quotas/models changes,
provider calls, source data extraction, full application E2E или нового дизайна.
Не чинить B01 manifest и frontend dev build в этой задаче.
Не закрывать manual/accessibility gate, Preview, M2 или PR #34.

## Requirements

- До mutation recorded: exact target identity, existing migration SHA/path,
  pending status, backup metadata и явная execution authorization.
- Если migration уже Ran и column существует — ничего повторно не применять;
  проверить contract и зафиксировать фактическое состояние.
- Если target не подтверждён, backup не проверен, появились другие pending
  изменения или миграция отличается по scope — BLOCKED/STOP с точной причиной.
- Текущий health PASS не выдавать за user/provider acceptance.
- Не откатывать existing data/schema для удобства теста.

## Security

Credentials остаются в существующем local/container mechanism; не выводить env,
connection strings, token rows, emails/career facts или SQL backup в evidence.
Сохранять runtime/migration privilege separation; не менять RLS/owner isolation.
Нет передачи user content или credentials внешним AI/services.
Backup хранится private и не попадает в Git/PR.

## Tests and Validation

- Read-only `migrate:status` до/после и schema column/default metadata.
- Existing `ChatGptPlanTest` с SQLite `:memory:` и mocked HTTP;
  не `RefreshDatabase` на data-bearing PostgreSQL.
- Использовать env/command convention из Makefile; принудительный APP_ENV=testing
  меняет Laravel CSRF behavior, что уже показал recovery audit.
- Public health live/ready, unauthenticated `/me`, Horizon status.
- Если нужен PG connection-race proof: только отдельно подтверждённый disposable
  harness scope; его создание/удаление DB не скрывать за словом read-only.
- `git diff --check`, targeted documentation links и scope check.
- Каждая проверка: literal command, PASS/FAIL/NOT_RUN/BLOCKED, layer, error.

## Documentation

Безопасный execution report в `.agents/state/`, обновить STATUS и реальный
runtime blocker. NEXT менять только по доказанному outcome; следующие ориентиры:
current B01 integrity reconciliation, затем существующий manual accessibility gate.
Dashboard синхронизировать как представление; approved ADR не менять.
Исторические результаты recovery не переписывать.

## Completion Criteria

- [x] Runtime/DB target и authorization однозначно подтверждены.
- [x] Backup создан и проверен до mutation либо mutation не потребовалась.
- [x] Existing migration Ran, oauth_generation column/default соответствует файлу.
- [x] Существующие data-bearing records сохранены; sensitive values не раскрыты.
- [x] Existing targeted tests и health checks фактически PASS; omissions явно указаны.
- [x] Нет product/dependency/infrastructure/AI/provider/user-data scope expansion.
- [x] Документы и blocker отражают outcome; accessibility/Preview остаются отдельно.

## Final Report

Кратко по-русски: target (safe identifier), SHA, applied/not-needed/NOT_RUN,
backup verification без sensitive path/content, фактические commands/results,
изменённые документы, remaining gates и одна next task. Не утверждать
live ChatGPT usability или Preview PASS по mocked tests.

## STOP

После bounded результата STOP. Не выполнять следующую integrity/manual task,
не запускать AI, не начинать Preview/M2, не merge/commit/push/close PR.
При отсутствии DB-write authorization завершить read-only планом и конкретным
owner decision request; отсутствие ответа не означает разрешение.

## Execution outcome — 2026-10-11

**DONE_VERIFIED, local scope.** [Execution report](../state/LOCAL_RUNTIME_ALIGNMENT_REPORT.md).
Owner explicitly authorized `cvortex_test` as the current local development DB
following exact runtime identification; this overrides the original test-DB exclusion.
Private backup verified before the one existing migration; 28/28 Ran, column/default
present, 35 data tables / 302 rows preserved. Mocked SQLite 16 tests / 92 assertions
and health/Horizon PASS. Initial constraint verifier FAIL and corrected PASS retained.
No live AI/OAuth, production mutation, code/config edits or commit/push/PR. STOP.
