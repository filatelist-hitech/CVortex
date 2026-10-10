---
title: B01 Application Workspace Reference
status: awaiting-review
owner: project
created: 2026-10-08
updated: 2026-10-10
tags: [design, reference, tokens, validation]
related: [../Design-Handoff.md, ../Application-Workspace.md, ../../../.agents/tasks/application-workspace-reference-foundation.md]
---

# Application Workspace reference (B01)

One normalized, synthetic HTML reference for Concept B. Lifecycle is **draft**;
human visual/state review is pending. This is not a production screen or an
approved visual regression baseline. It uses native HTML/CSS/JavaScript isolated
from the Next.js application; no prototype HTML was copied into production.

## Open locally

From the repository root, with the existing Node runtime:

```bash
node docs/07-Design/reference/build-reference.cjs --check
node docs/07-Design/reference/preview-server.cjs
```

Open [the loopback reference](http://127.0.0.1:8768/). On compact screens it starts
at the list; select Northstar or Cedar to enter the workspace. `Состояние макета`
selects normal/loading/empty/failed/stale/blocked/pending/approval. The local
section menu/links retain the opportunity. Evidence opens a separate mobile
level, a modal drawer at intermediate widths, or a wide complementary pane.
Escape/Close returns focus; selection clears the previous evidence and pin.

For a direct state link use
`/?scenario=pending&opportunity=northstar&level=work#materials`.
State changes update the URL. Back/Forward preserve the local section and level.
Filter text stays in memory; reload resets edits, approvals and filters.

The preview binds only to `127.0.0.1`; it serves this directory and denies writes,
path escape and network connections through CSP. No API, AI, employer action,
account data, persistent state or externally fetched asset is used.

## Authority and deliberate boundaries

[Manifest](manifest.json) identifies the one active reference, source/asset
SHA-256 values, canonical token hash, consumed roles, capture conditions and
separate review status. `tokens.css` derives from
[`cvortex.tokens.json`](../../../brand/tokens/cvortex.tokens.json).
Aliases, types, alpha and rem tracking are preserved; no token value or proposed
component alias was added. Existing surface/text/state roles cover this reference.
Pane measures and 44px targets are layout/accessibility constraints, not a second
palette or spacing scale. Media thresholds match canonical breakpoints.

The visible interface and assistive announcements are in Russian (`lang=ru`,
capture locale `ru-RU`). URLs, scenario IDs, canonical tokens and source references
keep their existing identifiers. Global Opportunities appears as «Вакансии»;
the other destinations retain their accepted roles under translated labels.

Space Grotesk remains the canonical family preference; this reference uses the
installed/system fallback and downloads no font. A production font asset remains
a separate obligation. Today/Career/Employers/Settings and employer history are
labelled unavailable. Fictional confirmation metadata inside the evidence fixture
is not a human review of B01.

Accept, exact-content approval and application submission are distinct. Missing,
pending, conflicting and stale support cannot be accepted or approved. Editing
recognizes only two exact synthetic wordings; it is a demonstration boundary,
not a semantic Truth Guard implementation. Production/API enforcement remains
unchanged. Reject needs no provider operation.

## Меню, зарплата и графика: 2026-10-10

По замечаниям владельца в шапке добавлено меню «Профиль». В нём можно посмотреть
три режима макета: пользователь, администратор и без входа. Кнопки входа и выхода
меняют только вымышленный профиль в памяти страницы. Перезагрузка возвращает
режим пользователя; сессия, права и данные приложения не меняются.

Диагностика появляется в примере для администратора. В production она уже
предусмотрена в `apps/frontend/src/app/access-shell.tsx` и `career-workspace.tsx`;
макет показывает только пункт меню и объяснение, без запросов или инцидентов.
Готовая панель пользователей и приглашений пока недоступна. Профиль и настройки
в макете отвечают сообщением о недоступности, не имитируют сохранение данных.
Меню дополняет принятую IA из пяти разделов. Оно открывается отдельно от панели
подтверждений, удерживает клавиатурный фокус и закрывается по Escape с возвратом
на кнопку профиля.

Зарплата вынесена в бирюзовый блок; сумма, период и условие «до налогов» сохранены
из вымышленных вакансий. Бирюзовый обозначает навигацию и основные действия,
фиолетовый выделяет предложения ИИ, синий используется для источников и компаний,
жёлтый и красный сохраняют значения предупреждений и блокировок. Новая палитра
и новые канонические токены не добавлялись.

В шапке вакансии появилась декоративная иллюстрация вихря с прозрачным фоном.
Она не выдаётся за логотип компании или подтверждённую карьерную информацию.
Иконки Lucide сохранены в HTML; во время просмотра внешние ресурсы не загружаются.
Версия, лицензия, SHA-256 иллюстрации и точный запрос генерации записаны в
[assets/sources.json](assets/sources.json); текст лицензии лежит в
[assets/lucide-LICENSE.txt](assets/lucide-LICENSE.txt).

## Reproduce automatic checks

```bash
node --test docs/07-Design/reference/build-reference.test.cjs
node docs/07-Design/reference/build-reference.cjs --check
```

With the preview running, use the already installed Playwright/browser paths
(override them for another available local runtime; no installation is required):

```bash
CVORTEX_PLAYWRIGHT_MODULE='/Users/filatelist/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright' \
CVORTEX_CHROMIUM_PATH='/Users/filatelist/Library/Caches/ms-playwright/chromium-1208/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing' \
node docs/07-Design/reference/validate-browser.cjs
```

This command recaptures synthetic evidence; it never approves a baseline. After a
reviewed source/token change, regenerate with `build-reference.cjs --write`,
recheck and recapture. Keep capture metadata and human review scope current.

## Recovery evidence — 2026-10-08

The interrupted run left the reference, two derivation tests, browser harness,
40 PNGs and a PASS report untracked. No tracked diff or staged change existed.
Manifest hashes for `app.js` and `style.css` were stale; review instructions,
capture metadata and state closeout were unfinished. Valid implementation was
preserved. Recovery fixed scenario URL persistence and the Cedar conflict reason,
added three rendered regressions and ran the final combined browser result.

Latest automatic/browser evidence: [browser-results.json](validation/browser-results.json)
(379 checks / 53 captures), [integrity-results.json](validation/integrity-results.json).
Two Node tests pass; derivation/hash check and syntax checks pass. The browser
report pins Chromium/Playwright/OS, locale, timezone, motion and font fallback.
No external request, missing asset or browser error occurred. Protected-file
hashes compare recovery start/end; they do not reconstruct an absent historical
hash baseline from before the interrupted session.

| B01 criterion | Recovered evidence / status |
| --- | --- |
| Local reference and canonical CSS/manifest | COMPLETE after hash regeneration and fresh checks |
| Eight states and mobile levels, no API/AI | COMPLETE, rendered 1440×900 / 768×1024 / 390×844 / 320×640 |
| Evidence paths and truthful review status | COMPLETE, report/captures and draft manifest |
| Concrete owner review surface | COMPLETE; human review NOT STARTED, awaiting-review |
| Production and original research untouched | COMPLETE, tracked production diff empty and protected hashes unchanged during recovery |
| STATUS/NEXT/BLOCKERS | Updated for awaiting-review; no B02 authority |

Agent visual inspection during recovery covered the capture matrix and full desktop evidence,
tablet drawer and compact workspace/evidence/block captures. Reflow, long Russian
text/identifiers, keyboard, visible focus, Escape/return focus, modal traps,
status/live-region changes, reduced-motion setting and rendered text/control
contrast were checked. Minimum sampled text contrast was 6.20:1. This narrow
custom audit is not a full WCAG or assistive-technology certification.

Real browser **200% zoom**, manual screen-reader assessment and human visual
review were **not performed**. Production ESLint/TypeScript/Vitest/build,
component tests and production visual regression are outside B01 and were not
run. No dependencies were installed. No commit, push, PR or publication.

## Русский текст интерфейса: 2026-10-10

По запросу владельца тексты макета переведены с применением `humanizer`.
Навигация, подсказки, ошибки, вымышленные примеры резюме и сообщения для экранного
диктора используют русский язык. Кнопки «Принять правку» и «Одобрить этот текст»
обозначают разные решения. Подтверждение карьерного факта остаётся отдельным
действием; одобрение текста не отправляет отклик.

Идентификаторы сценариев и адреса сохранены. Browser harness проверяет прежние
границы по русским текстам; актуальные captures и отчёт используют `ru-RU`.
Результаты прогона и ограничения записаны в [отчёте](validation/browser-results.json).
Общий положительный отзыв владельца не задаёт охват проверки состояний и размеров
экрана. Запись review пока остаётся незаполненной.

Проверки после визуальных правок: Chromium, 379 проверок и 53 captures, четыре
размера экрана. Проверены три режима меню, вход/выход только в памяти страницы,
доступность пунктов, Tab/Shift+Tab/Escape, возврат фокуса и видимость кнопки закрытия
при прокрутке. Исходные суммы зарплаты и условие до налогов сохранены, изображение
загружается локально и исключено из чтения экранным диктором. Восемь состояний
подготовки отклика и прежние ограничения одобрения проверены повторно.
Минимальный измеренный контраст текста: 6.20:1; внешних запросов и ошибок нет.
Осмотрены свежие desktop normal/Cedar requirements, 320 normal и меню администратора
на 390/320 px. Путь от источников к captures закреплён SHA-256 в browser report.
Реальный 200% zoom, screen reader и human review остаются непроверенными.

Next bounded action: owner visual/state review of this B01 revision using
[REVIEW.md](REVIEW.md). B01 is **awaiting-review**, not PASS. STOP before B02.
