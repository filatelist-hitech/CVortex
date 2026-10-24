# Реальные blockers

Recovery проверен 2026-10-11; QA-001 обновлён 2026-10-12; scope/evidence в [Recovery Report](PROJECT_RECOVERY_REPORT.md).
Недостающие M2–M6 функции — planned deliverables, а не blockers текущей recovery task.

## Закрыто в RCV-01 — local schema mismatch

2026-10-11 existing migration применена только к `cvortex_test`, явно разрешённой
владельцем как текущая local development DB вопреки test-DB запрету исходной spec.
Private backup verified; 28/28 Ran; column/default present; данные и isolation
metadata сохранены; targeted mocked tests и health PASS.
[Execution report](LOCAL_RUNTIME_ALIGNMENT_REPORT.md). Live ChatGPT/account/provider
readiness этим не доказана. Production mutations и другие DB не выполнялись.

## 1. Production accessibility release gate

- **Причина:** mandatory reference/production state/viewport interactions,
  K1 keyboard/focus, Z1 native200% zoom/reflow и A1 audible human screen reader
  остаются NOT TESTED/OPEN.
- **Влияние:** production release BLOCKED по ADR-0025. B01 owner ACCEPTED и
  merged B02 не закрывают gate.
- **Разрешение:** existing
  [b01-manual-accessibility-release-gate](../tasks/b01-manual-accessibility-release-gate.md),
  named human evidence для всех mandatory rows, устранение blocking findings
  отдельными authorized tasks. Сначала current reference integrity.
- **Владелец:** нужен human reviewer/AT session; screenshots/DOM/AX недостаточны.

## 2. QA-004 — Career Claim resolution race conditions (RACE-01–RACE-08)

- **Причина:** В `career-workspace.tsx` функция `refresh()` выполняет несинхронизированный
  GET-запрос и безусловно перезаписывает стейт через `setOverview(result.data)` без проверки
  последовательности (request ID / sequence token) или AbortController.
  Устаревший GET после успешного PATCH resolution возвращает интерфейс к состоянию
  `USER_RESOLUTION_REQUIRED` (RACE-01); устаревший GET с PASS перезаписывает более новый
  BLOCK (RACE-02); фоновый polling с интервалом 1500ms перекрывает форму отправки (RACE-06);
  размонтирование компонента вызывает недопустимый commit состояния размонтированного
  компонента (RACE-07). Коммит `b09a081` исправил только код ошибки `RESOURCE_NOT_FOUND`
  и memoize, не устранив гонки.
- **Влияние:** Сквозной пользовательский сценарий Cross-task integration: FAIL.
  Интерфейс Career Workspace нестабилен и подвержен ложному отображению неразрешённых
  утверждений либо ложному PASS.
- **Разрешение:** Отдельная bounded задача remediation: внедрение sequence token /
  AbortController для отмены/игнорирования устаревших ответов `refresh()`, блокировка
  затирания локального стейта разрешения устаревшими GET-ответами.

## 3. QA-002 — Migration harness lifecycle safety & isolation gaps

- **Причина:** Независимое ревью выявило критические замечания к `scripts/vacancy_migration_harness.py`:
  - `R1 MAJOR`: инвентаризация контейнеров `inspect_owned` использует `docker container ls -q`
    без флага `-a`, в результате чего остановленные контейнеры не учитываются. Собственный
    остановленный контейнер блокирует cleanup, а контейнер с неверным run-token может обойти guard.
  - `R2 MAJOR`: фикстура `reconciled.sql` проверяет только скалярные counts и статусы,
    но не валидирует полные FK-связи aggregate mapping `vacancy → snapshots → analyses → LLM runs`,
    и не содержит negative control для предотвращения ошибочной перепривязки к другому агрегату.
  - `R3 MINOR`: `scripts/qa_evidence.py` не проверяет QA-002 evidence package.
- **Влияние:** Запуск PostgreSQL lifecycle harness заблокирован (`BLOCKED`). На хосте активен
  shared development PostgreSQL (`cvortex-postgres-1`) с существующими данными (`cvortex_test`),
  которые запрещено считать disposable.
- **Разрешение:** Устранить замечания R1, R2, R3 в harness и подтвердить гарантированную
  disposable изоляцию до запуска реального lifecycle.

## 4. QA-003 — Diagnostics timestamp-tie negative control gap

- **Причина:** Регрессионный тест в `DiagnosticsPostgresTest.php` не содержит строгого
  отрицательного контроля: SQL-контроль показал, что без условия `id DESC` выборка в PostgreSQL
  при одинаковых timestamps возвращает строки в порядке вставки (совпадающем с ID), что
  приводит к ложному PASS при удалении обязательного tie-breaker из контроллера.
  Кроме того, в CI используется SQLite, где данный тест пропускается (`SKIP`).
  Успешный CI не подтверждает прохождение на PostgreSQL, а запуск на изолированном PostgreSQL
  не подтверждён (`SKIP` не является `PASS`). Не проверяется tie для `latest_operation` (LOW).
- **Влияние:** Статус QA-003 на stage: `BLOCKED`. Контракт детерминированной сортировки
  не защищён от регрессий.
- **Разрешение:** Добавить строгий negative control (мутационный тест, инвертирующий порядок ID
  или явно требующий `id DESC`), обеспечить запуск на изолированном PostgreSQL.

## 5. QA-001 — non-blocking review follow-ups (PASS on stage)

- PR #47 объединён в `stage`, canonical `--check` PASS, 10/10 Node тестов PASS,
  8/8 Python evidence regressions PASS. Исходный hash mismatch устранён.
- Сохраняются открытыми non-blocking замечания:
  - `REVIEW-QA001-01 (MINOR)`: полнота покрытия постоянными параметризованными тестами
    отдельных guard-веток (metadata SHA/length, path, base64 errors).
  - `REVIEW-QA001-02 (INFORMATIONAL)`: документационное разграничение snapshot integrity
    и upstream authenticity в README. Локальная snapshot integrity не доказывает подлинность upstream.

## 6. Preview 0.1 → M2 product gate

- **Причина:** repository не содержит полной real-user acceptance и feedback
  по m1-4 Preview completion checklist. Synthetic UI и unit tests этого не доказывают.
- **Влияние:** M1.4 full PASS/Preview accepted не заявлены; M2 planning/implementation
  ещё не executable. Missing package features не отдельные blockers Preview.
- **Разрешение:** provider readiness, authorized real user E2E со всеми
  PASS/FAIL criteria и feedback; только затем reassessment NEXT для M2.
- **Владелец:** real user review и разрешение на конкретную передачу user content
  провайдеру, если требуется новый live analysis/generation.

## Provider limitation — последнее известное evidence, не новый live диагноз

Исторический authorized retry 2026-10-03: все три попытки RATE_LIMITED/HTTP429,
final FAILED/PROVIDER_ERROR, успешного analysis/matching result не было.
Причина quota/billing/rate limit не установлена. Текущий статус провайдера
**NOT_VERIFIED**: этот audit не делал billing/account read или live запросы.
Оператору требуется проверить конкретный configured project перед разрешённым
повтором; API-key и ChatGPT plan usage — разные каналы. Это ограничение live Preview,
не blocker документационных/локальных mocked checks.

Development-mode frontend build issue и PR #34 governance failures — risks/follow-ups
в Recovery Report, не blockers production-mode Preview или всех DOCS работ.
