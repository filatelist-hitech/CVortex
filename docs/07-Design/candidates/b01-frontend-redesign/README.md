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
и exact-content approval. Кандидат **awaiting-review**, `isActiveReference: false`.
Accepted visual baseline и active B01 не заменены.

[Открыть кандидат](http://127.0.0.1:8769/?scenario=normal&opportunity=northstar&level=work#materials).
[Active B01](http://127.0.0.1:8768/?scenario=normal&opportunity=northstar&level=work#materials).
[Review владельца](REVIEW.md).

## Что доработано

- Русский и смешанный рабочий текст используют уже разрешённый Arial; локальный
  Space Grotesk сохраняется для бренда, Latin company и зарплаты. CDP подтвердил
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
export CVORTEX_PLAYWRIGHT_MODULE='/Users/filatelist/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'
export CVORTEX_CHROMIUM_PATH='/Users/filatelist/Library/Caches/ms-playwright/chromium-1208/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing'
node docs/07-Design/candidates/b01-frontend-redesign/validate-browser.cjs
node docs/07-Design/candidates/b01-frontend-redesign/validate-candidate.cjs
node docs/07-Design/candidates/b01-frontend-redesign/validate-apple.cjs
```

## Финальная проверка после последних правок

| Проверка | Фактический результат |
| --- | --- |
| [Inherited suite](validation/browser-results.json) | PASS: 379 checks / 53 captures; все восемь состояний на 1440×900 / 768×1024 / 390×844 / 320×640 |
| [Candidate sections](validation/candidate-results.json) | PASS: 70 checks / 22 captures; пять sections, missing salary, 720 px reflow, reduced motion |
| [Apple interactions](validation/apple-results.json) | PASS: 79 checks / 10 captures; press-before-commit, release cancellation, anchored menu/sheet, reversal without jump, focus/scroll/Escape, preference change in flight, high contrast/transparency, actual fonts |
| Общий результат | **528 checks / 85 свежих captures**; baseline до refinement: 449 / 75 |
| Text/control contrast | PASS в custom audit; минимум sampled text 6.20:1; focus/controls проверены |
| Browser errors / external requests | **0 / 0** во всех трёх финальных suites |
| [Actual fonts](validation/font-results.json) | CDP: русский заголовок и mixed comparison — Arial; brand/company/salary — custom Space Grotesk |
| [Static checks](validation/static-results.json) | Syntax, canonical derivation, source/capture hashes, JSON/PNG, local links, whitespace |
| [Integrity](validation/integrity-results.json) | 424 Git-visible protected start/end hashes совпали: production, tokens, active reference, original research, authority/NEXT |
| Shared alias/type/unit tests | 2/2 PASS; active reference transformer не изменён |

Все старые approval/provenance assertions сохранены: missing/pending/stale/conflict
блокируют принятие/одобрение, accept/content approval/submission различаются,
неподтверждённый edit отклоняется, pin/context сбрасываются, unsaved guard работает.
Active B01 не прогонялся повторно: его файлы/manifest/captures неизменны по hashes.
[Предыдущая проверка active](validation/active-regression-results.json) — историческое
baseline evidence, а не свежий прогон этого блока.

## Визуальный просмотр и сравнение

Агент просмотрел свежие четыре state/account sheets, матрицу 20 sections и
полноразмерные desktop normal, tablet evidence, compact workspace/pending/account,
high contrast; сравнил со [старым desktop](validation/baseline/1440-normal.png) и
[старым mobile](validation/baseline/390-normal.png). Matrix review проверяет
композицию; он не заявляет чтение каждой мелкой подписи на thumbnail.

| Экран | Снимки |
| --- | --- |
| Desktop 1440 | [Workspace](validation/1440-normal.png), [account](validation/apple-1440-menu.png), [states](validation/review-sheet-1440.png) |
| Tablet 768 | [Workspace](validation/768-normal.png), [evidence](validation/768-evidence.png), [states](validation/review-sheet-768.png) |
| Mobile 390 | [Workspace](validation/390-normal.png), [account](validation/apple-390-menu.png), [states](validation/review-sheet-390.png) |
| Small mobile 320 | [Pending](validation/320-pending.png), [account](validation/apple-320-menu.png), [states](validation/review-sheet-320.png) |
| Sections / accessibility | [20 sections](validation/review-sheet-sections.png), [high contrast](validation/apple-high-contrast.png) |

Fixed modal screenshots снимаются в фактическом viewport: full-page capture может
временно менять Chromium viewport и закрывать responsive list. Full-page captures
остальных состояний нормализуют scroll в начало, чтобы sticky navigation не
попадала в PNG со старой позицией. Это методика capture, keyboard assertions не
ослаблялись. После visual review также исправлены phantom grid rows около действий
и перенос числа зарплаты. Обнаруженный double-close от backdrop исправлен проверкой
target и начала pointer gesture; reversal regression проходит на четырёх размерах.
Асинхронная доставка media change ожидается в браузере перед assertion.

## Ограничения и следующий шаг

- Именной human visual/state review остаётся pending; screenshots агента его не заменяют.
- Нативный 200% browser zoom пытались проверить через CUA. Chrome browser connection
  недоступен; подключение к native Chrome завершилось ScreenCaptureKit `-3811`.
  Нативный zoom **не проверен**. 720 CSS px reflow не выдаётся за zoom; CSS zoom не используется.
- Ручной screen reader не проверялся. Custom contrast audit не является полной WCAG certification.
- Font rendering подтверждён на Chromium/macOS; другие OS могут выбрать иной sans-serif fallback.
  Production fonts и asset shipping требуют отдельного решения.
- Synthetic Truth Guard принимает две исходные формулировки; production/API enforcement,
  экспорт, реальный employer history и настоящий account/admin backend сюда не входят.

Изменены только candidate и STATUS/BLOCKERS, по bounded user override. Production
suites не запускались; dependencies, accepted ADR и canonical values не менялись.
Commit/push/PR/публикация не выполнялись. Следующий bounded шаг: review владельца
по REVIEW.md. NEXT остаётся `application-workspace-reference-foundation`.
STOP до решения, без B02 или замены active reference.
