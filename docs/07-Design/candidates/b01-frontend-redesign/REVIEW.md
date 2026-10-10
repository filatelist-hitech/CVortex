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

Статус: **awaiting-review**. Reviewer/date/outcome не заданы. Автоматические проверки
и просмотр screenshots агентом не заменяют human approval.

Открыть [кандидат](http://127.0.0.1:8769/?scenario=normal&opportunity=northstar&level=work#materials)
и сравнить с [active B01](http://127.0.0.1:8768/?scenario=normal&opportunity=northstar&level=work#materials).
Точные hashes текущей версии находятся в [manifest](manifest.json).

Нужно записать имя reviewer, дату, outcome, browser/OS, состояние и viewport,
замечания по композиции, клавиатуре и читаемости. Отдельно указать охват проверки
normal/loading/empty/failed/stale/blocked/pending/approval, Cedar conflict,
user/admin/guest, list/workspace/evidence на 1440/768/390/320.

Решение о направлении не является автоматическим одобрением всех состояний.
Active mapping и accepted ADR этим документом не меняются. После review требуется
отдельная команда на принятие/доработку кандидата; STOP до этого решения.


## Что проверить после Apple refinement

Сравнить с `validation/baseline/`, оценить читабельность Arial + Space Grotesk,
иерархию текста/причин/риска, доступность mobile пути назад и отсутствие ненужного
движения. Проверить открытие/закрытие и перенаправление account menu, Escape,
Tab/Shift+Tab, scroll/focus return, user/admin/guest. Убедиться, что принятие правки
не выглядит как одобрение текста. [Финальные результаты](README.md),
[аудит](APPLE-REFINEMENT.md).

Отдельные pending checks: реальный 200% browser zoom и manual screen reader.
Автоматическая проверка 528/85 и agent screenshot review не закрывают эти пункты
и не задают reviewer/date/outcome. Только владелец принимает направление.
