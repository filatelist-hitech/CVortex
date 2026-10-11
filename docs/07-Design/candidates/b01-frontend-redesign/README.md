---
title: B01 — отдельный визуальный кандидат
status: awaiting-review
owner: project
created: 2026-10-10
updated: 2026-10-10
tags: [design, candidate, validation, apple-design]
related: [DESIGN.md, APPLE-REFINEMENT.md, REVIEW.md, ../../Design-Handoff.md]
---

# Орбита решения

Концепция создана с `frontend-design` и доработана с `apple-design` по отдельному
брифу владельца. [Исходный выбор направления](DESIGN.md),
[аудит, приоритеты и refinement](APPLE-REFINEMENT.md).
Сохранены бренд, sidebar, вакансия/зарплата, сравнение текста, отдельные факты/риски
и exact-content approval. Владелец отдельно одобрил этот дизайн-кандидат и
персональное превью двух вакансий в ограниченном scope; см. [review](REVIEW.md).
Полный visual/state review остаётся awaiting-review. Кандидат не является active
reference, accepted visual baseline не заменён.

[Открыть кандидат](http://127.0.0.1:8769/?scenario=normal&opportunity=northstar&level=work#materials).
[Active B01](http://127.0.0.1:8768/?scenario=normal&opportunity=northstar&level=work#materials).
[Review владельца](REVIEW.md).

## Что доработано

- Русский и смешанный рабочий текст используют уже разрешённый Arial; локальный
  Space Grotesk сохраняется для бренда, цифр зарплаты и знака ₽; названия компаний используют Arial. CDP подтвердил
  реальные glyph providers, без заявления о кириллическом Space Grotesk.
- Заголовки используют согласованный weight/leading и canonical compact tracking;
  меры текста ограничены. Числа зарплаты переносятся целыми группами.
- Surfaces и transient shadows используют существующие canonical roles. Opaque
  surfaces сохраняют читаемость; отдельные fallbacks учитывают contrast/transparency.
- Press feedback начинается до click. Hover/focus/selected/disabled различимы.
  Принятие предложения и одобрение текста сохраняют отдельные состояния.
- Desktop профиль привязан к trigger; на mobile это нижний sheet с safe areas,
  внутренней прокруткой и sticky закрытием. Modal блокирует background scroll,
  сохраняет trap/return, закрывается по Escape/кнопке/нажатию на backdrop.
- Один локальный critically damped spring без зависимости сохраняет текущую
  position/velocity при retarget. Reduced motion завершает движение даже в полёте.
  Вкладки, role switches, warnings и approval обновляются немедленно.
- Mobile navigation list/work/evidence закреплена при чтении длинного текста;
  меню доступно с клавиатуры и закрывается по Escape. Риск расположен до действий.

## Локальный запуск и воспроизведение

Из корня, без установки dependencies:

```bash
node docs/07-Design/candidates/b01-frontend-redesign/build-reference.cjs --check
node docs/07-Design/candidates/b01-frontend-redesign/preview-server.cjs
```

Read-only loopback `127.0.0.1:8769`, CSP `connect-src 'none'`, локальные assets.
API/AI, storage, настоящая authentication и employer actions отсутствуют.
User/admin/guest меняют только synthetic состояние в памяти. Northstar показывает
принятие и отдельное одобрение; Cedar — конфликт удалённой/гибридной работы.
[Missing salary](http://127.0.0.1:8769/?scenario=normal&opportunity=northstar&level=work&salary=missing#overview)
честно показывает «Не указана», без выдуманной суммы/периода.

```bash
export CVORTEX_PLAYWRIGHT_MODULE='/path/to/playwright'
export CVORTEX_CHROMIUM_PATH='/path/to/chromium'
node docs/07-Design/candidates/b01-frontend-redesign/validate-browser.cjs
node docs/07-Design/candidates/b01-frontend-redesign/validate-candidate.cjs
node docs/07-Design/candidates/b01-frontend-redesign/validate-apple.cjs
node docs/07-Design/candidates/b01-frontend-redesign/validate-typography.cjs
```

## Финальная проверка после последних правок

| Проверка | Фактический результат |
| --- | --- |
| [Inherited suite](validation/browser-results.json) | PASS: 379 checks / 53 captures; все восемь состояний на 1440×900 / 768×1024 / 390×844 / 320×640 |
| [Candidate sections](validation/candidate-results.json) | PASS: 70 checks / 22 captures; пять sections, missing salary, 720 px reflow, reduced motion |
| [Apple interactions](validation/apple-results.json) | PASS: 83 checks / 10 captures; press-before-commit, release cancellation, anchored menu/sheet, reversal without jump, focus/scroll/Escape, preference change in flight, high contrast/transparency, actual fonts |
| [Typography](validation/typography/after/results.json) | PASS: 714 checks / 14 captures; 12 ролей, 31 DOM context, heading hierarchy, salary, нейтральный отказ, long text |
| Общий результат | **1246 checks / 99 свежих captures**; прежние 528 / 85 относятся к Apple baseline |
| Text/control contrast | PASS в custom audit; минимум sampled text 6.20:1; focus/controls проверены |
| Browser errors / external requests | **0 / 0** во всех четырёх финальных suites |
| [Actual fonts](validation/font-results.json) | CDP: русский заголовок и mixed comparison — Arial; brand/salary digits/₽ — custom Space Grotesk; company и «тыс.» — Arial |
| [Static checks](validation/static-results.json) | Syntax, canonical derivation, source/capture hashes, JSON/PNG, local links, whitespace |
| [Integrity](validation/integrity-results.json) | 639 Git-visible protected start/end hashes совпали: production, tokens, active reference, original research, authority/NEXT |
| Shared alias/type/unit tests | 2/2 PASS; active reference transformer не изменён |

Все старые approval/provenance assertions сохранены: missing/pending/stale/conflict
блокируют принятие/одобрение, accept/content approval/submission различаются,
неподтверждённый edit отклоняется, pin/context сбрасываются, unsaved guard работает.
Active B01 не прогонялся повторно: его файлы/manifest/captures неизменны по hashes.
[Предыдущая проверка active](validation/active-regression-results.json) — историческое
baseline evidence, а не свежий прогон этого блока.

## Визуальный просмотр и сравнение

Агент сравнил шесть пар BEFORE/AFTER: overview, materials и rejected на 1440 и
390 px. Все шесть свежих AFTER просмотрены полноразмерно. Название, суммы,
причины, источники и риск читаются; отказ нейтрален; approval отделён от принятия.
Пары и inventory доступны в [TYPOGRAPHY.md](TYPOGRAPHY.md). Старые review-sheet
относятся к Apple baseline и не используются как свежая visual evidence.

| Экран | Свежие снимки |
| --- | --- |
| Desktop 1440 | [Обзор](validation/typography/after/1440-overview.png), [материалы](validation/typography/after/1440-materials.png), [отказ](validation/typography/after/1440-rejected.png) |
| Mobile 390 | [Обзор](validation/typography/after/390-overview.png), [материалы](validation/typography/after/390-materials.png), [отказ](validation/typography/after/390-rejected.png) |
| Tablet 768 / mobile 320 | [768 normal](validation/768-normal.png), [320 pending](validation/320-pending.png) |

Fixed modal screenshots снимаются в фактическом viewport: full-page capture может
временно менять Chromium viewport и закрывать responsive list. Full-page captures
остальных состояний нормализуют scroll в начало, чтобы sticky navigation не
попадала в PNG со старой позицией. Это методика capture, keyboard assertions не
ослаблялись. После visual review также исправлены phantom grid rows около действий
и перенос числа зарплаты. Обнаруженный double-close от backdrop исправлен проверкой
target и начала pointer gesture; reversal regression проходит на четырёх размерах.
Асинхронная доставка media change ожидается в браузере перед assertion.

## Ограничения и следующий шаг

- Approval владельца ограничен дизайн-направлением и персональным preview двух
  вакансий. Он не заявляет review конкретных состояний/viewport, keyboard/focus,
  native zoom или assistive technology.
- Канонический B01 reference в `docs/07-Design/reference/` не рассмотрен этим
  решением; его review gate остаётся открытым. Кандидат не назначен active.
- Нативный 200% browser zoom пытались проверить через CUA. Chrome browser connection
  недоступен; подключение к native Chrome завершилось ScreenCaptureKit `-3811`.
  Нативный zoom **не проверен**. 720 CSS px reflow не выдаётся за zoom; CSS zoom не используется.
- Ручной screen reader не проверялся. Custom contrast audit не является полной WCAG certification.
- Font rendering подтверждён на Chromium/macOS; другие OS могут выбрать иной sans-serif fallback.
  Production fonts и asset shipping требуют отдельного решения.
- Synthetic Truth Guard принимает две исходные формулировки; production/API enforcement,
  экспорт, реальный employer history и настоящий account/admin backend сюда не входят.

В историческом Stage A отчёте ниже статус Stage B `BLOCKED` отражает точку проверки
2026-10-10T17:33:43Z. После неё Stage B был выполнен; актуальные результаты и
приватная evidence находятся в [personal-preview README](../b01-personal-preview/README.md)
и [manifest](../b01-personal-preview/manifest.json). Старый отчёт и его hashes
сохранены без переписывания.

Production suites не запускались в этом approval-closeout; dependencies, accepted
ADR и canonical values не менялись. Active mapping и NEXT не изменены.

## Завершение прерванной задачи, 10 октября 2026

STAGE A: **TECHNICAL PASS**. На старте ветка была чистой, HEAD `89f30e3`;
HTML/CSS/JS, типографика и BEFORE/AFTER уже сохранены. Typography и inherited
reports имели PASS, Apple report содержал FAIL, manifest был IN_PROGRESS,
README/REVIEW описывали прежний baseline. Все четыре suites запущены заново.

Воспроизведён FAIL «Russian salary unit remains Arial». CDP возвращает шрифты
контейнера вместе с дочерним ₽: Arial для «тыс.» и Space Grotesk для ₽. Проверка
теперь обращается к собственному текстовому узлу «тыс. » и требует реальный Arial
с ненулевым glyph count; отдельное строгое условие для ₽ сохранено. UI не менялся.
Assertions для keyboard, provenance и approval сохранены.

UI copy проверен по исходникам и DOM inventory: просмотр, принятие, изменение,
отклонение, одобрение текста и отправка различаются. Проверены оба диапазона RUB,
месячный период, до налогов и отсутствие зарплаты. Fixture одной суммы или иной
валюты отсутствует; универсальный salary formatter этим кандидатом не заявлен.

STAGE B в этом отчёте: **BLOCKED на момент проверки**. В том сообщении приложен
бриф, полного резюме не было. Этот point-in-time результат не отменён и не
переписан. Позднее полный source был получен, после чего отдельный Stage B создал
personal preview; его технические проверки описаны в отдельном README/manifest.
Сведения preview не являются подтверждёнными Career Facts.

[Отчёт завершения](validation/finalization-results.json) фиксирует проверки Stage A
и тогдашнюю блокировку Stage B с датой и source hashes. Stage B закрыт отдельным
артефактом. Owner approval кандидата scope-limited; native zoom и screen reader не
проверялись, а активный B01 reference не заменён.

## Canonical promotion — 2026-10-10

Owner selected this visual direction and authorized promotion to `../../reference/`.
This directory is retained as historical source; it is not a second active reference.
Historical reports and protected baselines remain unchanged. The historical builder's
strict integrity assertion may fail after authorized canonical/governance changes;
do not weaken it or rewrite its baseline. Current integrity and browser evidence is
in `../../reference/validation/closure-2026-10-10-v1/closure-results.json`.
