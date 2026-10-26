---
title: B01 visual candidate — owner review
status: awaiting-review
owner: project
created: 2026-10-10
updated: 2026-10-10
tags: [design, human-review, candidate]
related: [DESIGN.md, README.md, manifest.json]
---

# Review владельца

Статус полного visual/state review: **awaiting-review**. Отдельно записано
scope-limited approval владельца проекта по сообщению в чате от 2026-10-10:
одобрены направление «Орбита решения» и персональное превью двух вакансий.
Это не одобрение каждого состояния/viewport и не замена active reference.

Открыть [кандидат](http://127.0.0.1:8769/?scenario=normal&opportunity=northstar&level=work#materials)
и сравнить с [active B01](http://127.0.0.1:8768/?scenario=normal&opportunity=northstar&level=work#materials).
Точные hashes текущей версии находятся в [manifest](manifest.json).

Reviewer: владелец проекта (по явному сообщению в чате). Дата сообщения: 2026-10-10.
Outcome: одобрены дизайн-кандидат «Орбита решения» и персональный preview двух
вакансий в общем заявленном scope. Browser/OS и точные состояния/viewport в
сообщении не указаны. Проверки keyboard/focus, zoom, assistive technology,
отдельных сценариев и конкретных размеров экрана этим решением не подтверждены.

Запись относится к candidate в этой папке и парному личному preview. Она не
подменяет review канонического B01 reference в `docs/07-Design/reference/REVIEW.md`
и не меняет active mapping или accepted ADR. `isActiveReference` остаётся false.
Заявленные ограничения и автоматические результаты приведены отдельно в
[README](README.md) и manifest.


## Что проверить после Apple refinement

Сравнить с `validation/baseline/`, оценить читабельность Arial + Space Grotesk,
иерархию текста/причин/риска, доступность mobile пути назад и отсутствие ненужного
движения. Проверить открытие/закрытие и перенаправление account menu, Escape,
Tab/Shift+Tab, scroll/focus return, user/admin/guest. Убедиться, что принятие правки
не выглядит как одобрение текста. [Финальные результаты](README.md),
[аудит](APPLE-REFINEMENT.md).

Отдельные ограничения: реальный 200% browser zoom и manual screen reader не
проверялись. Свежие автоматические проверки 1246/99 не являются доказательством
этих пунктов; owner approval не расширяет заявленный scope.

## Текущая версия после восстановления

STAGE A имеет TECHNICAL PASS; [README](README.md) и manifest относятся к финальной
типографике. Stage B personal preview завершён отдельно (запись сохранена в приватном
локальном каталоге `.local/b01-personal-preview/REVIEW.md`). Владелец одобрил оба кандидата
только в scope, записанном выше. Active reference не заменён; точный B01 review
gate остаётся в `docs/07-Design/reference/REVIEW.md`.

## Superseding canonical decision — 2026-10-10

Decision: **APPROVED VISUAL DIRECTION**. Владелец продукта выбрал «Орбиту решения»
единственным canonical visual reference B01 и явно разрешил promotion.
Синтетические исходники перенесены в `../../reference/`; эта папка теперь
исторический source candidate, `isActiveReference: false`.
Предыдущие записи выше сохраняют исторический scope; утверждения «active reference
не заменён» описывают состояние до нового решения. Новая canonical REVIEW
фиксирует remaining state/viewport/zoom/AT gates без автоматического approval.
