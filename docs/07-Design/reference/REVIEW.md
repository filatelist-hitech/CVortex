---
title: B01 Canonical Owner Decision and Review
status: accepted
owner: project
created: 2026-10-08
updated: 2026-10-11
tags: [design, human-review, accessibility]
related: [manifest.json, README.md, ../Design-Handoff.md]
---

# Решение владельца B01

Актуальное окончательное решение — **OWNER ACCEPTANCE, 2026-10-11**, записано ниже.
Записи от 2026-10-10 и подготовки human gate сохранены как история.
Текущий итог: **B01 ACCEPTED** по ADR-0025; B02 **AUTHORIZED**. Ниже сохранены исторические решения до governance amendment.

Decision: **APPROVED VISUAL DIRECTION**. Reviewer: владелец продукта CVortex,
явное решение в чате. Decision date: **2026-10-10**.
Scope: **B01 Application Workspace Visual Reference**.
Selected reference: **«Орбита решения»**, исходный объект
`docs/07-Design/candidates/b01-frontend-redesign/index.html`.

Утверждённое направление перенесено в единственный canonical reference:
[index.html](index.html). Точные source/token hashes и связь с выбранным
исходником — в [manifest](manifest.json). Прежний canonical draft не выбран;
его исходники, manifest, review и evidence сохранены без изменения в
[архиве](../archive/b01-pre-orbita-2026-10-10/README.md).
Персональное превью служит проверке реальных сценариев; оно не является вторым
canonical reference. Личные данные и `.local/` не переносились.

Это подтверждение выбора visual direction/reference. Оно не приписывает владельцу
ручную проверку конкретных состояний, viewport, keyboard, 200% zoom или AT.
Предыдущее scope-limited approval candidate и personal preview от 2026-10-10
сохранено в архиве и candidate REVIEW. Ни одна запись не устанавливает эти проверки.

## Оставшийся обязательный review

**B01 PARTIAL / awaiting-review.** Lifecycle остаётся `draft`: owner selection
завершён, visual/state/accessibility acceptance ещё не установлен.
Автоматический прогон и просмотр скриншотов агентом — отдельное evidence,
[closure v1](validation/closure-2026-10-10-v1/closure-results.json).

| Объект ручной проверки | Статус |
| --- | --- |
| normal/loading/empty/failed/stale/blocked/pending/approval на 1440×900, 768×1024, 390×844, 320×640 | not manually reviewed |
| Cedar conflict, missing/pending/stale support, inline reason, Accept → exact-content approval → not sent | not manually reviewed |
| list → workspace → evidence; Back/Forward; разделы overview/requirements/employer/history/materials | not manually reviewed |
| Меню user/admin/guest, unreadable/long text, отсутствующая зарплата | not manually reviewed |
| Tab/Shift+Tab, видимый focus, Escape, focus return, transient panel trap | not manually reviewed |
| Реальный browser zoom 200%, reflow и доступность контролов | not manually reviewed; не выполнено в этом прогоне |
| Manual screen reader: headings/landmarks, чтение evidence, live announcements loading/error/approval, modal focus | not manually reviewed; не выполнено в этом прогоне |

Для закрытия записать reviewer, дату, browser/OS/AT, конкретные состояния/viewport,
результат и findings для текущих manifest hashes. ADR-0024 требует ручную AT
assessment; Design Handoff и Foundation требуют zoom/reflow. Автоматическая
проверка 720px reflow не заменяет реальный 200% zoom.
При PASS без блокирующих findings обновить lifecycle и B01 state отдельным
bounded block. B02 требует закрытия B01 и отдельного разрешения на implementation.
Career Facts не подтверждены, отклики не отправлены. STOP.

## Final Human Acceptance Gate — 2026-10-11

[Полная ручная матрица, guided review и неподписанная запись решения](MANUAL-ACCEPTANCE.md).
Новый ограниченный native 200% zoom assessment агента сохранён отдельно в
`validation/human-gate-2026-10-11/`; он не устанавливает human acceptance.
Screen reader — NOT TESTED; owner state/viewport/keyboard/zoom gates остаются открытыми.
Historical closure v1/source hashes сверены и переиспользованы без перезаписи.
B01 PARTIAL / awaiting-review; lifecycle draft, NEXT B01. STOP.


## Окончательное OWNER ACCEPTANCE — 2026-10-11

Reviewer / decision maker: **владелец продукта CVortex**, явно подтвердивший
окончательное решение в текущем чате «Final B01 Acceptance → B02 Production
Implementation». Имя и результаты отдельных ручных сеансов не предоставлены.
Первоначальное утверждение визуального направления: **2026-10-10**.
Текущее решение: **2026-10-11**, Europe/Moscow.
Scope: **B01 Application Workspace Visual Reference — «Орбита решения»**.
Canonical: `docs/07-Design/reference/`; персональное превью принято как
демонстрационный сценарий, без переноса его личных данных в production.

### 1. Visual approval — PASS

Владелец окончательно утвердил визуальную концепцию, композицию интерфейса,
типографику, систему компонентов, адаптивный дизайн как направление и canonical
reference. Это завершённое решение о дизайне; повторного редизайна не требуется.

### 2. Product/UX acceptance — PASS в заявленном scope

Владелец утвердил навигационные решения, интерфейсные тексты и персональное
превью как демонстрационный сценарий. Это owner acceptance продукта/UX в указанном
scope, без утверждения о выполнении 32 state/viewport проверок, keyboard/zoom/AT
тестов, production implementation или Preview 0.1 end-to-end acceptance.

### 3. Automated validation — PASS существующего baseline

[Closure v1](validation/closure-2026-10-10-v1/closure-results.json): **1246 checks /
99 captures**, **2568 integrity assertions / 820 snapshot files**, без browser
errors и external requests. На 2026-10-11 повторно сверены 14 source hashes,
SHA-256 и counts четырёх suite reports, hash integrity report и canonical tokens.
Исторические suites не перезапускались и отчёты не переписывались. Проверка
актуальности и сохранности текущего блока записана в
[owner-acceptance verification](validation/owner-acceptance-2026-10-11.json).

### 4. Manual validation — PARTIAL

Все непроверенные строки [MANUAL-ACCEPTANCE](MANUAL-ACCEPTANCE.md) сохраняют
**NOT TESTED**: 32 комбинации состояний/viewport, ручные сценарии взаимодействий,
K1 keyboard/focus, полный Z1 native 200% zoom и A1 screen reader.
Ограниченное agent evidence native zoom 200% (734×385 CSS px, одно окно, восемь
состояний materials и отдельные controls/menu/evidence) сохранено отдельно.
Оно не доказывает полное покрытие Z1 или human acceptance. Screen-reader сеанса
нет; DOM/AX и автоматические assertions его не заменяют.

### 5. Accepted residual risks

Владелец ознакомлен с автоматическими результатами и известными ограничениями
и принимает остаточный риск непроведённых ручных проверок **насколько это
допускается действующими правилами проекта**. Риски: непроверенное человеком
восприятие/читаемость состояний на всех размерах, keyboard/focus в полном scope,
clipping/overlap и доступность controls при zoom, реальное чтение/announcements AT.
Принятие риска записано; обязательные проверки им не заменяются.

### Closure decision — PARTIAL; readiness B02 — BLOCKED

Действующий контракт не предусматривает risk-acceptance waiver:

- [ADR-0024, Security and validation impact](../../03-ADR/ADR-0024-git-design-reference-workflow.md#security-and-validation-impact)
  требует manual assistive-technology assessment наряду с остальными проверками.
- [Design Handoff, Handoff lifecycle](../Design-Handoff.md#handoff-lifecycle)
  требует state/viewport scope и human review; при pending human review manifest
  остаётся `draft`. Разделы Change and review procedure / Validation contract
  сохраняют desktop/mobile/zoom и manual screen-reader assessment.
- [Design Foundation](../Design-Foundation.md#accessibility-acceptance-baseline)
  сохраняет keyboard, focus, 200% zoom/reflow и screen-reader requirements.
- [B01 task specification](../../../.agents/tasks/application-workspace-reference-foundation.md#completion-criteria)
  запрещает PASS и переход к B02 при остающемся awaiting-review;
  [ручная матрица](MANUAL-ACCEPTANCE.md) сохраняет обязательные NOT TESTED.

Даже окончательное owner acceptance не устраняет отсутствие обязательного AT
assessment. Критерии, ADR и матрица не изменены задним числом. B01 остаётся
**PARTIAL / awaiting-review**, lifecycle **draft**, NEXT —
`application-workspace-reference-foundation`.

Владелец **явно разрешил исключительно B02** по
`.agents/tasks/b02-global-shell-navigation.md` **после корректного закрытия B01**.
Разрешение получено и сохраняется; условие запуска не выполнено. B02 не начат;
production files/React components не изменены. Следующее действие: выполнить и
записать недостающие обязательные ручные проверки B01, затем повторно оценить
closure по действующему контракту. B03 не разрешён. **STOP.**

## Governance closure — 2026-10-11 (current)

Decision maker: **владелец продукта CVortex**, explicit current request
“Finalize B01 Governance → Execute B02”. Accepted [ADR-0025](../../03-ADR/ADR-0025-b01-reference-acceptance-release-gate.md)
amends B01 closure timing. **B01 ACCEPTED / lifecycle reviewed** as a visual/product
implementation baseline. No redesign required. Owner acceptance, verified historical
automatic baseline, documented risks above and mandatory follow-up satisfy the new gate.

Manual matrix outcomes are unchanged: **NOT TESTED** remains NOT TESTED; no human
screen-reader session or full manual state/viewport/keyboard/zoom PASS is claimed.
[Mandatory release gate](../../../.agents/tasks/b01-manual-accessibility-release-gate.md)
is OPEN and blocks production release. Historical automatic checks are not weakened
or rerun by this decision. B02 authorization is now effective; B03 remains unauthorized.
