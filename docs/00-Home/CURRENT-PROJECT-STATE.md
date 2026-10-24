---
title: CVortex — Current Project Status
status: current-dashboard
owner: project
created: 2026-10-03
updated: 2026-10-12
audit_date: 2026-10-11
repository_commit: f06a6dba058589823bf3d3c8b693cb625170bc4b
tags: [project-state, recovery, roadmap, mvp]
---

# CVortex — Current Project Status

## QA-001 update — 2026-10-12

На ветке от stage `5cfe18d` current canonical integrity **PASS**, 10/10 Node
regressions **PASS**; OFL/license text, tokens и визуальный reference не изменены.
[Текущий статус/evidence QA-001](../09-QA/QA-001-RECONCILIATION.md).
Независимый PR review/integration ожидается. NEXT — существующий manual accessibility
release gate после QA-001 review; gate **OPEN / NOT TESTED**, production release
blocked. Preview/provider/M2 gates не изменены.

## Recovery snapshot — 2026-10-11 (история)

Следующий датированный snapshot сохраняет прежний FAIL и Git/runtime evidence;
текущие QA-001/NEXT формулировки находятся выше и в каноническом STATUS.

Проверено **11 октября 2026, Europe/Moscow**, на stage `f06a6db`.
Это удобное представление [канонического STATUS](../../.agents/state/STATUS.md),
[NEXT](../../.agents/state/NEXT.md) и [BLOCKERS](../../.agents/state/BLOCKERS.md).
Подробное evidence и границы проверки — в
[Project Recovery Report](../../.agents/state/PROJECT_RECOVERY_REPORT.md).
Предыдущий [аудит 3 октября](PROJECT-STATE-AUDIT-2026-10-03.md) сохранён как история.

## Current milestone — где мы сейчас

**M1 First Value → Preview 0.1.** Access, Career и Vacancy integrated;
Application Draft integrated, но полная приёмка **IMPLEMENTED_UNVERIFIED**.
Реальный пользовательский путь и feedback ещё не подтверждены.
Milestone определяется [Roadmap](../01-Product/Roadmap.md), а не последней веткой
или папкой задач. Исторические Phases 00–07 — foundation; Phase 08–12 execution
заменён M0–M6.

## Overall project health — здоровье проекта

**Технический стек работает, контроль готовности требует стабилизации.**
Health live/ready200 и Horizon running, frontend 108 tests PASS,
backend targeted 210 tests/1396 assertions с одним PG-only skip PASS.
RCV-01 устранил local schema mismatch: 28/28 migrations Ran, данные сохранены,
ChatGptPlanTest 16/92 PASS; B01 canonical manifest integrity check остаётся FAIL. Зелёная readiness не проверяет
полноту схемы и пользовательский путь. Production release blocked manual gate.

## Core MVP status

MVP **0.2 не достигнут**. Уже есть invite/login, review Career Facts,
paste vacancy/snapshot/extraction/matching, resume recommendations,
short/standard cover drafts и отдельное human approval. Эти возможности
подтверждены кодом/тестами, но не новым real-provider/user E2E.

Для MVP 0.2 ещё нужны M2 package/lifecycle, Company/Application/versions,
Employer Memory/consistency, DOCX→PDF/private download и manual tracking.
[PRD/FR/NFR](../01-Product/Phase-06-Product-Design.md) и
[conceptual ERD](../04-Data/Phase-06-Data-Design.md) — целевой дизайн,
не список работающих таблиц. Субъективных процентов готовности здесь нет.

## Active workstreams

| Направление | Подтверждённый результат | Что остаётся |
| --- | --- | --- |
| Core M1 | Срезы integrated, targeted tests PASS | Real Preview acceptance/feedback |
| B01/B02 design | «Орбита решения» owner ACCEPTED, React port integrated #44 | Manifest integrity; ручная accessibility shipped UI |
| ChatGPT/MCP | #42 merged, provider abstraction и controlled draft save | RCV-01 local schema aligned; live/account readiness отдельно |
| Recovery | Evidence report/state/dashboard/next specification | Документы пока локальные в recovery worktree |

Отдельный утверждённый **CVortex Universe** с task/scene/camera/acceptance plan
в осмотренных индексах не найден. Design experiments и private personal-preview
не меняют Core milestone. Live Figma **NOT_VERIFIED**; Git design authority
определяют ADR-0024/0025 и canonical tokens.

## Last verified achievements

- PR #44 **MERGED** в stage; fresh108 frontend tests, ESLint/TypeScript PASS.
- Targeted backend suite **210 tests/1396 assertions**, SQLite, один PG-only skip.
- Compose config, public health и Horizon checks PASS; credentials не выводились.
- Exact stage Quality CI
  [SUCCESS](https://github.com/filatelist-hitech/CVortex/actions/runs/38153409400).
  CI не заменяет PostgreSQL/manual/user evidence.
- React derivation 260 checks и reference transformer 2/2 PASS.
  Отдельный manifest check **FAIL**, не замаскирован этими PASS.

## Current blockers и risks

1. Current canonical manifest hash OFL.txt не совпадает с tracked bytes.
2. Mandatory manual production release gate OPEN: state/viewport, keyboard,
   native 200% zoom и audible human screen reader.
3. Preview 0.1: нет real-user acceptance/feedback; M2 пока не executable.

Local schema blocker закрыт: owner явно разрешил `cvortex_test` как текущую
development DB после private backup. Existing migration applied; одно additive
поле, 35 data tables / 302 rows сохранены.
[RCV-01 report](../../.agents/state/LOCAL_RUNTIME_ALIGNMENT_REPORT.md).

Последнее live API-key evidence от 3 октября — RATE_LIMITED/HTTP429;
текущие billing/quota и ChatGPT plan readiness здесь **не проверялись**.
Не повторять AI запрос без разрешения передачи конкретных данных.
Другие риски: SQLite не доказывает RLS/races, historical scoped AGENTS M0 wording,
старый конфликтующий PR34 и dirty соседний worktree.
Подробности — [blockers](../../.agents/state/BLOCKERS.md) и Recovery Report.

## Next action — одна задача

**`b01-current-manifest-integrity-reconciliation` — текущий B01 manifest / OFL.txt provenance.**
RCV-01 выполнен. Для следующего шага ещё нужны отдельная bounded spec и owner launch;
этот блок завершён STOP. Historical baselines/goldens не переписывать.
Затем existing manual accessibility release gate. Production/manual/Preview
acceptance и live ChatGPT usability не подтверждены mocked/runtime checks.

## Next 3 milestones — ориентир дорожки

1. **Завершить M1/Preview 0.1:** runtime readiness, реальные user criteria и feedback.
2. **M2/MVP 0.2:** отдельный planning contract, затем traceable downloadable package.
3. **M3:** safe imports/source integrations после доказанного package flow.

Production accessibility gate обязателен независимо от продуктового milestone.
B03/M2/release не запускаются на основании visual acceptance.

## Git / PR summary

- Source checkout: stage `f06a6db`, live remote совпадает; tracked clean.
- PR44 merged; PR42 merged и 59/59 threads resolved; PR43 closed/superseded.
- Единственный open [PR34](https://github.com/filatelist-hitech/CVortex/pull/34):
  conflicting, governance FAIL; отдельная disposition, без auto merge/close.
- 802 local untracked design files и правки соседнего docs/chatgpt-plan-chat
  worktree сохранены. Ahead counts старых squash branches не равны новым features.
- main не promoted к current product; release не выполнен.
- Этот recovery — локальные документы в
  `/Users/filatelist/Documents/it/CVortex-project-recovery`, branch
  `docs/project-recovery-2026-10-11`; commit/push/PR не создавались.
  Следующая сессия использует recovery checkout либо separately integrated docs.

## Действия владельца

- Отдельно запустить bounded B01 current integrity reconciliation; spec ещё нужна.
- Для release gate выполнить/организовать human keyboard/zoom/screen-reader review.
- Перед real Preview проверить provider readiness и явно разрешить payload/retry,
  затем дать наблюдаемый user feedback. Не подтверждать карьерные факты заочно.
- Git delivery recovery documents / закрытие старого PR — отдельное решение;
  здесь ничего не опубликовано и не закрыто.

## Подробная документация

- [STATUS](../../.agents/state/STATUS.md), [NEXT](../../.agents/state/NEXT.md),
  [BLOCKERS](../../.agents/state/BLOCKERS.md),
  [Recovery Report](../../.agents/state/PROJECT_RECOVERY_REPORT.md).
- [Task index](../../.agents/tasks/README.md),
  [Roadmap](../01-Product/Roadmap.md), [ADR index](../03-ADR/INDEX.md).
- [Documentation Map](Documentation-Map.md), [User Guide](User-Guide.md),
  [Local Development](../10-Operations/Local-Development.md).
- [Design Handoff](../07-Design/Design-Handoff.md),
  [manual release task](../../.agents/tasks/b01-manual-accessibility-release-gate.md).
