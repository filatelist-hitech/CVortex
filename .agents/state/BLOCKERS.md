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

## Закрыто в QA-004 (PR #53) — Career Claim resolution race conditions (RACE-01–RACE-08)

2026-10-25 дефекты состояний гонки в `career-workspace.tsx` устранены в PR #53 (коммит `679c3fc` в `stage`).
Внедрены монотонные generation-токены запросов, эпохи мутаций, локальный кэш инвариантов разрешений (`resolvedClaimsRef`),
координация с `AbortController`, взаимное исключение активных мутаций и фонового поллинга, а также строгая изоляция
жизненного цикла при смене пользователя и демонтировании компонента.
Детерминированные регрессионные тесты RACE-01–RACE-08 (142/142 Vitest PASS) и backend Truth Guard isolation
(`ClaimResolutionUiTest`, 83/83 PHPUnit PASS) подтверждены в CI.

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

## Закрыто в QA-002 — Migration harness lifecycle safety & isolation gaps

2026-10-25 замечания R1, R2, R3 устранены:
- `R1 MAJOR`: В `scripts/vacancy_migration_harness.py` метод `resources()` использует флаг `-a` для `docker container ls`, учитывая остановленные контейнеры при инвентаризации, валидации токенов владения и контроле чистоты cleanup. Добавлен юнит-тест `test_resources_lists_all_containers_including_stopped`.
- `R2 MAJOR`: Фикстура `reconciled.sql` дополнена исчерпывающей валидацией FK и aggregate mapping `vacancy → snapshots → analyses → LLM runs` (проверка соответствия каждого снимка его вакансии и `source_url`, каждого анализа — его снимку и вакансии, LLM-прогонов — снимку). В harness отрицательный контроль `QA002-14` перенесён в пост-миграционную фазу после применения `REPAIRS` и прохождения базового `reconciled.sql`, снабжён явной проверкой предусловий (наличие записи, обновление ровно одной строки) и проверяет точную сигнатуру ошибки (`snapshot source_url or owner_id differs from parent vacancy`). Добавлены юнит-тесты защиты от ложного PASS в `test_vacancy_migration_harness.py`.
- `R3 MINOR`: В `scripts/qa_evidence.py` внедрена функция `validate_qa002`, выполняющая строгую валидацию JSON, сверку SHA-256 файлов из `log-exports.json` (включая распаковку raw gzip), структуры `exact-runs.json`, неизменности `development-preservation.json` и целостности ссылок в `README.md`. Добавлены тесты верификации и обнаружения подделки данных в `test_qa_evidence.py`.
- Полный lifecycle проверен запуском `scripts/test-vacancy-postgres-duplicate-migration.sh --regression-controls` в гарантированно изолированном disposable Compose-окружении (PASS, чистый cleanup).

## Закрыто в QA-003 — Diagnostics timestamp-tie negative control & latest_operation

2026-10-25 дефекты регрессионного теста устранены:
- В `DiagnosticsPostgresTest.php` (`test_runtime_role_uses_id_to_break_latest_occurrence_timestamp_ties`):
  - Инвертирован физический порядок обновления кортежей относительно лексикографического порядка идентификаторов: строка с большим ID обновляется первой, а с меньшим ID — второй (получая больший TID в куче PostgreSQL).
  - Внедрен строгий negative control: прямой SQL-запрос `ORDER BY created_at DESC LIMIT 1` без обязательного `id DESC` возвращает более старую запись (`$smallerId`), что доказательно исключает ложный PASS при удалении `id DESC` из контроллера.
  - Добавлена проверка tie-breaker для поля `latest_operation` (`operation_newer` vs `operation_older`).
  - Тест валидирован мутационным тестированием (удаление `id DESC` из контроллера вызывает немедленный FAIL тестов) и подтвержден на изолированном PostgreSQL (5/5 PASS, 58 assertions, 0 SKIP).

## 4. QA-001 — non-blocking review follow-ups (PASS on stage)

- PR #47 объединён в `stage`, canonical `--check` PASS, 10/10 Node тестов PASS,
  8/8 Python evidence regressions PASS. Исходный hash mismatch устранён.
- Сохраняются открытыми non-blocking замечания:
  - `REVIEW-QA001-01 (MINOR)`: полнота покрытия постоянными параметризованными тестами
    отдельных guard-веток (metadata SHA/length, path, base64 errors).
  - `REVIEW-QA001-02 (INFORMATIONAL)`: документационное разграничение snapshot integrity
    и upstream authenticity в README. Локальная snapshot integrity не доказывает подлинность upstream.

## 5. Preview 0.1 → M2 product gate

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
