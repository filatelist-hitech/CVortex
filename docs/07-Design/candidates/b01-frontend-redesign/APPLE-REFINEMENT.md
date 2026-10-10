---
title: Орбита решения — Apple Design refinement
status: awaiting-review
owner: project
created: 2026-10-10
updated: 2026-10-10
tags: [design, accessibility, motion, candidate]
related: [DESIGN.md, README.md, REVIEW.md]
---

# Аудит и решение

Запись предыдущего блока Apple refinement. Текущая типографика и тексты описаны
в [TYPOGRAPHY.md](TYPOGRAPHY.md), свежие проверки находятся в [README](README.md).
Числа проверок и font mapping ниже относятся к тому блоку.

Bounded user override: IMPLEMENT на существующей ветке
`chore/application-workspace-reference-foundation`, base/target `stage`.
Preflight `check-agent-contract.sh implementation ... --write --user-override` PASS.
Только candidate и STATUS/BLOCKERS. NEXT, active B01 и authority сохранены.
Один агент, без OMX/subagents. Полный snapshot до правок:
`/tmp/cvortex-orbit-before-apple.zip` (временный локальный архив).
Сохраняемые [desktop](validation/baseline/1440-normal.png) и
[mobile](validation/baseline/390-normal.png), manifest и baseline reports —
в `validation/baseline/`. Предыдущие 449 checks / 75 captures не являются
доказательством текущего результата.

| Приоритет | Наблюдение до правок | Решение / принцип |
| --- | --- | --- |
| P1 | Space Grotesk Latin и Arial Cyrillic смешаны внутри каждой строки; жирные подписи конкурируют с контентом | Явный Arial для русского рабочего текста, Space Grotesk для brand/Latin company/salary; weight/leading по роли. Clarity, consistency. |
| P1 | Профиль открывается из угла в центре экрана, закрывается без обратного пути; keyframes нельзя перенаправить | Desktop menu привязано к trigger, mobile sheet снизу; critically damped spring из текущего значения и скорости; Escape/close обратимы. Spatial continuity, agency. |
| P1 | На mobile путь назад пропадает при прокрутке, no safe-area treatment | Sticky путь list/work/evidence и доступное меню; safe-area padding, scroll lock только для modal. Wayfinding, flexibility. |
| P2 | Причины растягивают review card и оставляют пустоту около действий | Выравнивание колонки сверху, меры текста, более точная иерархия surfaces/approval. Deference, craft. |
| P2 | Hover/selected заметны, press даёт лишь 1px сдвиг; account feedback сохраняет старое сообщение | Мгновенный press, явные hover/focus/disabled, semantic action feedback; feedback reset на open. Response, predictability. |
| P2 | Шапка dialog на 320 занимает много места и при прокрутке отрезает контекст | Компактная sticky шапка, scroll внутри sheet, видимое закрытие. Mobile ergonomics. |
| P2 | Нет отдельной проверки reduced transparency / increased contrast / motion interruption | Opaque default surfaces, explicit media fallbacks; фактические runtime assertions. Accessibility. |

## Источники и применение skill

Изучен `/Users/filatelist/.agents/skills/apple-design/SKILL.md`.
Других reference files в его каталоге нет. Проверены первичные материалы Apple:
[Designing Fluid Interfaces, WWDC18](https://developer.apple.com/videos/play/wwdc2018/803/),
[Motion HIG](https://developer.apple.com/design/human-interface-guidelines/motion),
[Typography HIG](https://developer.apple.com/design/human-interface-guidelines/typography)
(доступ 2026-10-10; HIG content прочитан через официальный JSON endpoint).
Response, redirection, короткое уместное движение и статическое reduced-motion
применяются к существующим действиям. Gesture/swipe, sound/haptics не вводятся:
в этом кандидате для них нет пользовательской задачи.

## Typography и motion

Канонические machine values не меняются. Arial уже входит в разрешённый fallback;
явный выбор рабочего текста устраняет случайное чередование гарнитур в API/pytest
и русской прозе. Latin brand/company и цифры salary используют существующий
licensed local Space Grotesk. Кириллица не объявляется Space Grotesk.
Новые font assets не скачиваются. Это candidate-only composition, без решения
о production fonts. Sizes/spacing/layers/colors берутся из canonical roles.

Движение: один локальный `motion.js`, без dependency. Критически затухающая
пружина, response 0.30s, no bounce, transform/opacity, rAF с analytic integration.
Retarget сохраняет presentation value и скорость. Modal close сохраняет focus
trap до завершения; новое взаимодействие внутри закрывающегося меню возвращает
его к открытому состоянию. Повторный Escape завершает закрытие. Reduced motion
немедленно завершает движение, в том числе если preference изменена в полёте.
Вкладки, warnings, role switches и approval обновляют семантику немедленно;
движение не задерживает gates и не изображает неподтверждённые данные принятыми.

## Проверки и human gate

Финальный прогон после последней CSS/capture правки: 528 checks / 85 captures PASS;
379 inherited + 70 candidate + 79 Apple checks. 2/2 shared transformer tests PASS.
Визуально просмотрены fresh state/section sheets и full-size выборка.
Минимум sampled text contrast 6.20:1; browser errors/external requests 0/0.
424 protected Git-visible hashes сохранены. Результаты и точные ограничения —
в README и validation reports. Native zoom попытка заблокирована unavailable
Chrome connection и ScreenCaptureKit -3811; manual screen reader не запускался.
По visual review исправлены group wrap salary, phantom action grid gap и sticky
full-page capture. Double-close backdrop найден и исправлен, assertions не ослаблены. Human review остаётся pending;
эта работа не назначает candidate active и не начинает B02.
