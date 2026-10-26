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
- Мобильная навигация по разделам (список / работа / факты) зафиксирована при чтении длинного текста;
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

Все проверки утверждения и происхождения фактов сохранены: отсутствующие, ожидающие проверки, устаревшие и конфликтующие данные блокируют принятие и утверждение; шаги принятия, утверждения и отправки строго разграничены; неподтверждённые правки отклоняются.
Канонический эталон B01 повторно не тестировался: его файлы, манифест и снимки не изменялись.
[Предыдущая проверка эталона](validation/active-regression-results.json) приведена как историческое свидетельство, а не новый прогон.

## Визуальный просмотр и сравнение

Агент сравнил шесть пар BEFORE/AFTER: overview, materials и rejected на 1440 и
390 px. Все шесть свежих AFTER просмотрены полноразмерно. Название, суммы,
причины, источники и риск читаются; отказ нейтрален; approval отделён от принятия.
Пары и inventory доступны в [TYPOGRAPHY.md](TYPOGRAPHY.md). Старые review-sheet
относятся к Apple baseline и не используются как свежая visual evidence.

| Экран | Свежие снимки (артефакты оптимизированы в PR #51) |
| --- | --- |
| Desktop 1440 | `1440-overview.png`, `1440-materials.png`, `1440-rejected.png` |
| Mobile 390 | `390-overview.png`, `390-materials.png`, `390-rejected.png` |
| Tablet 768 / mobile 320 | `768-normal.png`, `320-pending.png` |

Fixed modal screenshots снимаются в фактическом viewport: full-page capture может
временно менять Chromium viewport и закрывать responsive list. Full-page captures
остальных состояний нормализуют scroll в начало, чтобы sticky navigation не
попадала в PNG со старой позицией. Это методика capture, keyboard assertions не
ослаблялись. После visual review также исправлены phantom grid rows около действий
и перенос числа зарплаты. Обнаруженный double-close от backdrop исправлен проверкой
target и начала pointer gesture; reversal regression проходит на четырёх размерах.
Асинхронная доставка media change ожидается в браузере перед assertion.

## Ограничения и следующий шаг

- Согласование владельца касается общего визуального направления и персонального превью двух вакансий. Оно не подменяет собой ручную проверку состояний экранов, навигации с клавиатуры, масштабирования верстки или программ экранного доступа.
- Канонический эталон B01 в `docs/07-Design/reference/` не рассматривался этим решением; контрольный рубеж ручной приёмки остаётся открытым. Кандидат не назначен активным.
- Нативное масштабирование до 200% в реальном браузере не завершено из-за сбоя захвата экрана ScreenCaptureKit `-3811`. Тестирование ширины 720 px не заменяет реального зума до 200%.
- Ручное тестирование со скринридером не проводилось. Автоматический аудит контраста не заменяет полной сертификации доступности по стандартам WCAG.
- Отрисовка шрифтов верифицирована на Chromium/macOS; другие операционные системы могут использовать системные шрифты подстановки.
- Демонстрационный контроль Truth Guard принимает две исходные формулировки; боевая валидация API, экспорт и реальная история работодателей в этот макет не входят.

В историческом отчёте Stage A статус Stage B `BLOCKED` отражает точку проверки 10 октября 2026 года. После неё этап Stage B был выполнен; актуальные результаты и файлы персонального превью сохранены в локальном каталоге `.local/b01-personal-preview/` (включён в `.gitignore` по правилам защиты персональных данных).

Боевые наборы тестов не перезапускались при закрытии этого согласования; зависимости и канонические токены не менялись.

## Завершение прерванной задачи, 10 октября 2026

ЭТАП A: **ТЕХНИЧЕСКИЙ ПРОГОН УСПЕШЕН (TECHNICAL PASS)**. На старте рабочая ветка была чистой; файлы разметки, стилей и типографики сохранены. Все четыре тестовых набора были запущены заново.

Устранена проблема с отображением единиц зарплаты: Arial для сокращения «тыс.» и Space Grotesk для знака рубля. Проверки навигации с клавиатуры, цепочки фактов и утверждения текста полностью сохранены.

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
