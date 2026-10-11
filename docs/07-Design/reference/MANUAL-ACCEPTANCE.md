---
title: B01 Final Human Acceptance Gate
status: awaiting-review
owner: project
created: 2026-10-11
updated: 2026-10-11
tags: [design, human-review, accessibility]
related: [REVIEW.md, manifest.json, validation/closure-2026-10-10-v1/closure-results.json]
---

# B01: финальная ручная приёмка

**Manual validation PARTIAL / awaiting-review; B01 reference ACCEPTED under ADR-0025.** Canonical: [«Орбита решения»](index.html),
`docs/07-Design/reference/`. Visual direction approval: владелец CVortex,
2026-10-10. Окончательное **OWNER ACCEPTANCE от 2026-10-11** и принятие
остаточных рисков записаны в [REVIEW](REVIEW.md#окончательное-owner-acceptance--2026-10-11).
Результаты state/viewport/keyboard/zoom/AT проверок этим решением не установлены. Scope: B01 Application Workspace Visual Reference.

Preview запущен: [canonical preview](http://127.0.0.1:8768/).
Сервер слушает только loopback; production не используется.
Проверяемая ревизия: source hashes из [closure v1](validation/closure-2026-10-10-v1/closure-results.json)
совпадают с текущими исходниками: [integrity-current](validation/human-gate-2026-10-11/integrity-current.json).
Token SHA-256: `72565fc8a72458e0f5c28563ab5b4656a5cdbaf1bfa58e8c6c353b87402358e7`.

## Матрица состояний — ручной gate

Каждая строка ниже требует отдельного результата. `NOT TESTED` означает отсутствие
записанной ручной проверки, а не отсутствие автоматического evidence.
Reviewer `—` означает, что человек пока не назван. После проверки указать имя,
дату, browser/OS, фактический результат и evidence; при дефекте — FAIL и finding.
Нельзя присвоить всему viewport PASS по одной happy-path картинке.

| ID / сценарий | Viewport | Ожидаемый результат | Фактический результат | Evidence | Reviewer | Статус |
| --- | --- | --- | --- | --- | --- | --- |
| normal-1440: [normal](http://127.0.0.1:8768/?scenario=normal&opportunity=northstar&level=work#materials) | 1440×900 | Правка и provenance читаются; Accept доступен, одобрение только после принятия. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/1440-normal.png); human evidence отсутствует | — | NOT TESTED |
| normal-768: [normal](http://127.0.0.1:8768/?scenario=normal&opportunity=northstar&level=work#materials) | 768×1024 | Правка и provenance читаются; Accept доступен, одобрение только после принятия. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/768-normal.png); human evidence отсутствует | — | NOT TESTED |
| normal-390: [normal](http://127.0.0.1:8768/?scenario=normal&opportunity=northstar&level=work#materials) | 390×844 | Правка и provenance читаются; Accept доступен, одобрение только после принятия. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/390-normal.png); human evidence отсутствует | — | NOT TESTED |
| normal-320: [normal](http://127.0.0.1:8768/?scenario=normal&opportunity=northstar&level=work#materials) | 320×640 | Правка и provenance читаются; Accept доступен, одобрение только после принятия. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/320-normal.png); human evidence отсутствует | — | NOT TESTED |
| loading-1440: [loading](http://127.0.0.1:8768/?scenario=loading&opportunity=northstar&level=work#materials) | 1440×900 | Явная загрузка; анализа и ложного процента готовности нет; правка недоступна. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/1440-loading.png); human evidence отсутствует | — | NOT TESTED |
| loading-768: [loading](http://127.0.0.1:8768/?scenario=loading&opportunity=northstar&level=work#materials) | 768×1024 | Явная загрузка; анализа и ложного процента готовности нет; правка недоступна. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/768-loading.png); human evidence отсутствует | — | NOT TESTED |
| loading-390: [loading](http://127.0.0.1:8768/?scenario=loading&opportunity=northstar&level=work#materials) | 390×844 | Явная загрузка; анализа и ложного процента готовности нет; правка недоступна. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/390-loading.png); human evidence отсутствует | — | NOT TESTED |
| loading-320: [loading](http://127.0.0.1:8768/?scenario=loading&opportunity=northstar&level=work#materials) | 320×640 | Явная загрузка; анализа и ложного процента готовности нет; правка недоступна. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/320-loading.png); human evidence отсутствует | — | NOT TESTED |
| empty-1440: [empty](http://127.0.0.1:8768/?scenario=empty&opportunity=northstar&level=work#materials) | 1440×900 | Анализа нет; соответствие не оценено; одобрение недоступно. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/1440-empty.png); human evidence отсутствует | — | NOT TESTED |
| empty-768: [empty](http://127.0.0.1:8768/?scenario=empty&opportunity=northstar&level=work#materials) | 768×1024 | Анализа нет; соответствие не оценено; одобрение недоступно. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/768-empty.png); human evidence отсутствует | — | NOT TESTED |
| empty-390: [empty](http://127.0.0.1:8768/?scenario=empty&opportunity=northstar&level=work#materials) | 390×844 | Анализа нет; соответствие не оценено; одобрение недоступно. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/390-empty.png); human evidence отсутствует | — | NOT TESTED |
| empty-320: [empty](http://127.0.0.1:8768/?scenario=empty&opportunity=northstar&level=work#materials) | 320×640 | Анализа нет; соответствие не оценено; одобрение недоступно. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/320-empty.png); human evidence отсутствует | — | NOT TESTED |
| failed-1440: [failed](http://127.0.0.1:8768/?scenario=failed&opportunity=northstar&level=work#materials) | 1440×900 | Ошибка и следующий шаг явны; повторный запрос не выполняется. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/1440-failed.png); human evidence отсутствует | — | NOT TESTED |
| failed-768: [failed](http://127.0.0.1:8768/?scenario=failed&opportunity=northstar&level=work#materials) | 768×1024 | Ошибка и следующий шаг явны; повторный запрос не выполняется. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/768-failed.png); human evidence отсутствует | — | NOT TESTED |
| failed-390: [failed](http://127.0.0.1:8768/?scenario=failed&opportunity=northstar&level=work#materials) | 390×844 | Ошибка и следующий шаг явны; повторный запрос не выполняется. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/390-failed.png); human evidence отсутствует | — | NOT TESTED |
| failed-320: [failed](http://127.0.0.1:8768/?scenario=failed&opportunity=northstar&level=work#materials) | 320×640 | Ошибка и следующий шаг явны; повторный запрос не выполняется. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/320-failed.png); human evidence отсутствует | — | NOT TESTED |
| stale-1440: [stale](http://127.0.0.1:8768/?scenario=stale&opportunity=northstar&level=work#materials) | 1440×900 | Устаревший источник и причина видны; Accept/approval запрещены. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/1440-stale.png); human evidence отсутствует | — | NOT TESTED |
| stale-768: [stale](http://127.0.0.1:8768/?scenario=stale&opportunity=northstar&level=work#materials) | 768×1024 | Устаревший источник и причина видны; Accept/approval запрещены. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/768-stale.png); human evidence отсутствует | — | NOT TESTED |
| stale-390: [stale](http://127.0.0.1:8768/?scenario=stale&opportunity=northstar&level=work#materials) | 390×844 | Устаревший источник и причина видны; Accept/approval запрещены. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/390-stale.png); human evidence отсутствует | — | NOT TESTED |
| stale-320: [stale](http://127.0.0.1:8768/?scenario=stale&opportunity=northstar&level=work#materials) | 320×640 | Устаревший источник и причина видны; Accept/approval запрещены. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/320-stale.png); human evidence отсутствует | — | NOT TESTED |
| blocked-1440: [blocked](http://127.0.0.1:8768/?scenario=blocked&opportunity=northstar&level=work#materials) | 1440×900 | Нет подтверждений; inline reason виден; Accept/approval запрещены. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/1440-blocked.png); human evidence отсутствует | — | NOT TESTED |
| blocked-768: [blocked](http://127.0.0.1:8768/?scenario=blocked&opportunity=northstar&level=work#materials) | 768×1024 | Нет подтверждений; inline reason виден; Accept/approval запрещены. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/768-blocked.png); human evidence отсутствует | — | NOT TESTED |
| blocked-390: [blocked](http://127.0.0.1:8768/?scenario=blocked&opportunity=northstar&level=work#materials) | 390×844 | Нет подтверждений; inline reason виден; Accept/approval запрещены. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/390-blocked.png); human evidence отсутствует | — | NOT TESTED |
| blocked-320: [blocked](http://127.0.0.1:8768/?scenario=blocked&opportunity=northstar&level=work#materials) | 320×640 | Нет подтверждений; inline reason виден; Accept/approval запрещены. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/320-blocked.png); human evidence отсутствует | — | NOT TESTED |
| pending-1440: [pending](http://127.0.0.1:8768/?scenario=pending&opportunity=northstar&level=work#materials) | 1440×900 | F-31 не подтверждён; не выглядит confirmed; Accept/approval запрещены. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/1440-pending.png); human evidence отсутствует | — | NOT TESTED |
| pending-768: [pending](http://127.0.0.1:8768/?scenario=pending&opportunity=northstar&level=work#materials) | 768×1024 | F-31 не подтверждён; не выглядит confirmed; Accept/approval запрещены. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/768-pending.png); human evidence отсутствует | — | NOT TESTED |
| pending-390: [pending](http://127.0.0.1:8768/?scenario=pending&opportunity=northstar&level=work#materials) | 390×844 | F-31 не подтверждён; не выглядит confirmed; Accept/approval запрещены. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/390-pending.png); human evidence отсутствует | — | NOT TESTED |
| pending-320: [pending](http://127.0.0.1:8768/?scenario=pending&opportunity=northstar&level=work#materials) | 320×640 | F-31 не подтверждён; не выглядит confirmed; Accept/approval запрещены. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/320-pending.png); human evidence отсутствует | — | NOT TESTED |
| approval-1440: [approval](http://127.0.0.1:8768/?scenario=approval&opportunity=northstar&level=work#materials) | 1440×900 | Правка принята, точный текст ещё не одобрен; отклик не отправлен. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/1440-approval.png); human evidence отсутствует | — | NOT TESTED |
| approval-768: [approval](http://127.0.0.1:8768/?scenario=approval&opportunity=northstar&level=work#materials) | 768×1024 | Правка принята, точный текст ещё не одобрен; отклик не отправлен. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/768-approval.png); human evidence отсутствует | — | NOT TESTED |
| approval-390: [approval](http://127.0.0.1:8768/?scenario=approval&opportunity=northstar&level=work#materials) | 390×844 | Правка принята, точный текст ещё не одобрен; отклик не отправлен. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/390-approval.png); human evidence отсутствует | — | NOT TESTED |
| approval-320: [approval](http://127.0.0.1:8768/?scenario=approval&opportunity=northstar&level=work#materials) | 320×640 | Правка принята, точный текст ещё не одобрен; отклик не отправлен. | Ручной результат не записан | [Автоматический capture](validation/closure-2026-10-10-v1/320-approval.png); human evidence отсутствует | — | NOT TESTED |

## Матрица взаимодействий — ручной gate

V = 1440×900 / 768×1024 / 390×844 / 320×640. Для строк с V записывать
результаты по каждому размеру; строка закрывается только когда покрыт весь scope.

| ID / сценарий | Viewport | Ожидаемый результат | Фактический результат | Evidence | Reviewer | Статус |
| --- | --- | --- | --- | --- | --- | --- |
| G1 Cedar conflict | V | Удалённая работа противоречит гибриду; причина рядом; Accept/approval недоступны | Нет ручного результата | [Cedar](http://127.0.0.1:8768/?scenario=normal&opportunity=cedar&level=work#materials); closure captures | — | NOT TESTED |
| G2 provenance | V | Text → CL-08 → F-12/F-18 → excerpt/source; missing/pending/stale явно различимы | Нет ручного результата | Кнопка «Факты и источник»; closure evidence captures | — | NOT TESTED |
| G3 approval separation | V | normal: принять → проверить точный текст → отдельно одобрить; статус «отклик не отправлен». Reject сохраняет прежний текст | Нет ручного результата | [Normal materials](http://127.0.0.1:8768/?scenario=normal&opportunity=northstar&level=work#materials) | — | NOT TESTED |
| N1 уровни и история | V | list → workspace → evidence → назад; выбор и раздел сохранены; Back/Forward корректны; новая вакансия снимает pin | Нет ручного результата | [List](http://127.0.0.1:8768/?scenario=normal&opportunity=northstar&level=list#overview); кнопки evidence/Back | — | NOT TESTED |
| N2 разделы | V | overview/requirements/employer/history/materials доступны; unavailable обозначен честно | Нет ручного результата | Ссылки ниже | — | NOT TESTED |
| M1 user/admin/guest | V | Профиль открывается/закрывается; режимы вымышленные; diagnostics только admin; real auth не выполняется | Нет ручного результата | «Профиль» → «Режим макета» | — | NOT TESTED |
| T1 длинный/нечитаемый текст | V | Заголовки/источники/длинные строки читаются, не перекрывают controls; неполные данные не выглядят подтверждёнными | Нет ручного результата | [Typography captures](validation/closure-2026-10-10-v1/typography/after/results.json), *-long-names.png; live filter/edit для длинного текста | — | NOT TESTED |
| T2 нет зарплаты | V | Отсутствие суммы явно обозначено; сумма не придумана | Нет ручного результата | [Missing salary](http://127.0.0.1:8768/?scenario=normal&opportunity=northstar&level=work&salary=missing#overview) | — | NOT TESTED |
| K1 keyboard/focus | V | Tab/Shift+Tab, visible focus, Escape/return, trap только transient modal; все controls достижимы | Нет человеческого результата; агент проверил ограниченный scope при zoom | closure browser report + новый agent session ниже | — | NOT TESTED |
| Z1 native zoom 200% | Реальное desktop окно; приёмка reflow/controls и transient surfaces | Нет clipping/overflow/overlap, потери focus или недоступных controls/dialogs | Ограниченный agent assessment ниже; human gate открыт | [Zoom session](validation/human-gate-2026-10-11/native-zoom-session.json) | — | NOT TESTED |
| A1 screen reader | Desktop + compact; зафиксировать реальные размеры, AT/browser/OS | Headings/landmarks, evidence reading, loading/error/approval announcements, modal focus/return понятны на слух | Screen-reader assessment не проведён; DOM/AX его не заменяют | Human audible reading/announcement evidence отсутствует | — | NOT TESTED |

## Guided review

Не нужно заново прогонять 1246 автоматических assertions. Проверяйте человеческое
восприятие, чтение и работу controls в матрице, записывая findings.

1. Desktop 1440×900: восемь state links из матрицы; normal/Cedar и цепочка provenance;
   принять и отдельно одобрить вымышленный текст; проверить «отклик не отправлен».
2. Tablet 768×1024: те же состояния; list и evidence drawer, прокрутка, close и return focus.
3. Mobile 390×844 и 320×640: те же состояния; list → workspace → evidence, Back;
   локальный select разделов и меню без перекрытых controls. Это CSS viewport, не размер PNG.
4. Navigation: пройти [overview](http://127.0.0.1:8768/?scenario=normal&opportunity=northstar&level=work#overview),
   [requirements](http://127.0.0.1:8768/?scenario=normal&opportunity=northstar&level=work#requirements),
   [employer](http://127.0.0.1:8768/?scenario=normal&opportunity=northstar&level=work#employer),
   [history](http://127.0.0.1:8768/?scenario=normal&opportunity=northstar&level=work#history),
   [materials](http://127.0.0.1:8768/?scenario=normal&opportunity=northstar&level=work#materials).
   Evidence открывается кнопкой; прямой URL `level=context` сам по себе не выбирает источник.
   Проверить Back/Forward, смену вакансии, pin и три режима профиля.
5. Keyboard: убрать мышь; Tab/Shift+Tab, skip link, selects, menu, evidence,
   Escape и возврат к инициатору. Focus должен быть виден, modal trap — замыкаться.
6. Zoom: **native browser menu → 200%**, проверить состояния и прокрутку до нижних
   actions, menu/evidence/approval. Записать исходный размер окна и эффективный CSS viewport;
   уменьшение DevTools viewport и CSS zoom не являются этой проверкой. Вернуть 100% после.
7. AT: человек с VoiceOver + Chrome/Safari (либо другим доступным screen reader)
   читает заголовки/landmarks, evidence chain и источник; меняет loading → failed → approval,
   слушает announcements, открывает/закрывает menu и evidence, проверяет modal focus/return.
   Записать **услышанное**, AT/version, browser/OS, состояние/viewport и findings.
   Без реального сеанса оставлять A1 NOT TESTED. DOM inspection недостаточен.

Long-name fixture captions из typography harness не доступны отдельным URL:
их captures можно вручную прочитать на каждом размере; это не live interaction assessment.
Если capture недостаточен для T1, оставить строку открытой и организовать отдельный
synthetic stress-сеанс без изменения canonical source.

## Доступные проверки агента — 2026-10-11

Reviewer: **Codex agent**, не владелец. Native Chrome на macOS; масштаб 200%
установлен Cmd+0 + пять увеличений и подтверждён Chrome AX UI:
[native-zoom-confirmation](validation/human-gate-2026-10-11/native-zoom-confirmation.txt).
Эффективный CSS viewport **734×385**, DPR 4. Проверено одно реальное окно.
Полные версии Chrome/macOS в этом сеансе не зафиксированы; closure capture environment
относится к прошлому автоматическому прогону и не подставляется вместо них.

| Проверка агента | Фактический результат | Evidence | Статус в указанном scope |
| --- | --- | --- | --- |
| 8 состояний на 200%, #materials | scrollWidth = clientWidth = 734 во всех восьми; сообщения визуально различимы | [metrics](validation/human-gate-2026-10-11/zoom-metrics.json), native-*-materials.png | PASS |
| Меню: Tab/Shift+Tab, прокрутка, Escape | Close → Shift+Tab → role select → Tab → Close; visible focus; после анимации focus на account-trigger | session; zoom-menu.png; native-menu-return.png | PASS |
| Evidence и Escape | Compact evidence читается с вертикальной прокруткой; Escape возвращает focus на evidence button | session; AX наблюдение записано в session | PASS |
| Approval control | Кнопка достижима; Shift+Tab возвращает focus с manifest link на approve; button виден | native-approval-controls.png; native-approve-focus.png | PASS |
| Полное отсутствие clipping/overlap во всех разделах/dialogs/окнах | Полный scope не проверен; отдельный viewport не закрывает human Z1 | Требуется ручной результат Z1 | NOT TESTED |
| Screen reader | Нет человеческого сеанса чтения/прослушивания | Нет AT evidence | NOT TESTED |

Full-page PNG `zoom-*-materials.png`, `zoom-normal.png`, `zoom-evidence.png`
искажены capture API на 200% (367px вместо native 735px) и **исключены из evidence для visual PASS**.
Native viewport PNG `native-*-materials.png` имеют 735×386 и осмотрены агентом.
Позднее системный capture отказал (ScreenCaptureKit -3811); полный дальнейший осмотр
недоступен. Возврат масштаба к 100% не подтверждён: последний DOM measurement всё ещё
734×385/DPR 4. Владельцу перед началом выставить нужный масштаб через меню Chrome.
Отсутствие screen-reader evidence не превращается в PASS благодаря AX inspection.

## Шаблон результатов ручного сеанса — НЕ ЗАПОЛНЕН

Заполняет владелец после проверки; это шаблон test evidence. Окончательное
owner acceptance уже записано в REVIEW и не заполняет результаты тестов:

```text
Decision: [PASS / FAIL / PARTIAL]
Reviewer: [имя владельца]; Date: [дата]
Scope: B01 Application Workspace Visual Reference
Selected canonical: docs/07-Design/reference/ — «Орбита решения»
Revision: source hashes из closure v1, сверенные integrity-current.json
Browser/version; OS/version: [указать]
States/viewports actually reviewed:
1440×900: [normal/loading/empty/failed/stale/blocked/pending/approval — результаты]
768×1024: [перечень и результаты]
390×844: [перечень и результаты]
320×640: [перечень и результаты]
Cedar/provenance/approval separation: [G1/G2/G3]
Navigation/sections/menu/text/salary: [N1/N2/M1/T1/T2]
Keyboard/focus: [K1 — фактический outcome и evidence]
Native 200% zoom: [Z1 — размеры, состояния, clipping/overflow/controls/dialogs/focus]
Screen reader: [A1 — AT/version, услышанное, состояния/viewport, outcome/evidence]
Findings / remaining limitations: [перечислить; непройденное = NOT TESTED]
Owner decision: [явно подтвердить outcome именно этого scope/revision]
```

Одного сообщения «нравится / одобряю направление» недостаточно для закрытия
обязательных K1/Z1/A1 и state/viewport строк. Agent PASS не приписывается владельцу.
После явного решения сверить все обязательные строки: если есть FAIL или NOT TESTED,
оставить **PARTIAL**, записать причину. Если всё пройдено — отдельным bounded block
зафиксировать review outcome, lifecycle, STATUS/BLOCKERS/NEXT и проверить связность.
Никаких исключений из критериев без принятого процесса изменения решения.

## Integrity и STOP

1246 checks / 99 captures, 2568 integrity assertions / 820 snapshot files —
исторический closure v1 PASS, переиспользован после проверки hashes; suites не повторялись.
Новые captures/session/integrity-current записаны отдельно в `human-gate-2026-10-11/`.
Historical reports, snapshots, canonical sources, tokens и production не переписывались.
REVIEW содержит ссылку на эту матрицу; STATUS/BLOCKERS/NEXT сохраняют PARTIAL/B01
до выполнения обязательных ручных критериев. Окончательное owner acceptance
получено 2026-10-11; разрешение B02 условно и действует после корректного закрытия
B01. Production, commit/push/PR, Career Facts и отклики в этом блоке не изменяются. **STOP.**

## Current B01 governance outcome — 2026-10-11

Under accepted [ADR-0025](../../03-ADR/ADR-0025-b01-reference-acceptance-release-gate.md), B01 is **ACCEPTED** as the owner-approved visual/product implementation baseline; lifecycle `reviewed`. Earlier PARTIAL/draft/STOP records above are historical. Unperformed manual checks remain **NOT TESTED**, with mandatory follow-up `.agents/tasks/b01-manual-accessibility-release-gate.md` blocking production release. B02 is explicitly authorized after this closure; B03 is not authorized.
