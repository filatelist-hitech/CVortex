---
title: Орбита решения, типографика и тексты
status: awaiting-review
owner: project
created: 2026-10-10
updated: 2026-10-10
tags: [design, typography, ux-writing, candidate]
related: [DESIGN.md, README.md, REVIEW.md, ../../Design-Foundation.md]
---

# Типографика и визуальная иерархия

## Границы и исходный аудит

Repository-native IMPLEMENT, один агент. Bounded user override на ветке
`chore/application-workspace-reference-foundation`, base/target `stage`.
Git preflight с `--write --user-override` выполнен. Изменения ограничены кандидатом
и STATUS/BLOCKERS. Active B01, NEXT, production, accepted ADR и canonical tokens
сохраняются. Приложенного screenshot в доступном сообщении нет; исходные шесть
снимков сняты с фактического кандидата перед изменениями.

[DOM inventory до изменений](validation/typography/before/inventory.json) охватывает
все пять разделов, восемь состояний, отказ/принятие/одобрение, редактирование и его
ошибку, Cedar conflict, evidence, mobile list и три demo roles. Он включает H1–H6,
визуальные заголовки strong/p, подписи, статусы, кнопки и вспомогательные тексты.

| Наблюдение | Причина | Решение |
| --- | --- | --- |
| Название вакансии и зарплата конкурируют | Узкий title рядом с яркой карточкой, оба 32 px | Page Title 24 px, Numeric Highlight 24 px; зарплата без рамки и тени |
| Заголовки одного уровня выглядят по-разному | h2/h3 переопределены в pane, section, inspector, dialog | Визуальная роль задаётся классом независимо от HTML-тега |
| H2 списка предшествует H1 вакансии; на mobile нет видимого H1 списка | Семантика закреплена за desktop | Список desktop имеет подпись; list/work/evidence получают один видимый H1 |
| Отказ выглядит как блокировка | rejected использует danger | Отказ neutral; отсутствие подтверждений error; pending/stale warning |
| Диапазон «240 000…290 000 ₽» разрывается | Многоточие и слишком крупные цифры | Эквивалентные «240–290 тыс. ₽», отдельно месяц и налоги |
| Предложения и контекст нарезаны прямоугольниками | Рамки и подложки дублируют иерархию | Одна поверхность сравнения, контекст отделён whitespace |
| Следующее действие повторяет описание состояния | Общая подпись для разных gates | Действие зависит от просмотра, принятия, отказа и одобрения |
| Тексты многократно повторяют вымышленность и недоступность | Пояснения вставлены в каждый блок | Явная demo-рамка, краткие локальные ограничения и сохранённые риски |

## Направление до реализации

Сохраняется «Орбита решения»: вакансия задаёт контекст, сравнение текста занимает
основной объём, факты и риски стоят рядом с ним. Выравнивание влево, общая ось
заголовков, вкладок и контента. Шапка вакансии становится спокойнее; выразительный
элемент остаётся в предложенной правке, с фирменным violet и ясным действием.

```text
[Навигация][Вакансии][Компания / название                 Зарплата]
                    [Действие и короткое пояснение                ]
                    [Контекстные разделы                          ]
                    [Заголовок раздела                            ]
                    [Сейчас / предложенная правка][Факты и риск   ]
                    [Принять / изменить / отклонить               ]
                    [Отдельное одобрение точного текста           ]
```

Палитра берётся из canonical roles: canvas `#070B18`, default `#0B1224`, elevated
`#111C35`, text `#F7F9FC`, action `#35D6E8`, recommendation `#A78BFA`. Новой темы
нет. Spacing: 8 px внутри связанной пары, 16 px между элементами группы, 24 px
между группами, 32 px между крупными областями. Это существующая scale.

План проверен против брифа: убраны крупная зарплатная плашка, декоративная полоса
у следующего действия и многократные обводки статусов. Glass, новые gestures,
шрифты и декоративные появления не добавляются. Existing spring/focus поведение
сохраняется. Профессиональное рабочее пространство остаётся dark-first.

## Единые роли

Рабочая гарнитура всех ролей, кроме Numeric Highlight, Arial, sans-serif. Это уже
разрешённый fallback. Бренд и цифры используют имеющийся локальный Space Grotesk;
русская единица «тыс.» использует Arial, знак ₽ использует Space Grotesk. Latin названия компаний тоже переходят
на рабочую гарнитуру, чтобы контекст и русский текст читались как одна система.
Шрифт не скачивается. Фактические glyph providers проверяются через CDP.

Sizes/weights/leading/colors берутся из canonical tokens. Классы `type-*` являются
локальным отображением ролей на эту шкалу, а не второй token authority. Accepted
Foundation и её общая шкала H1/H2/H3 остаются прежними. HTML-теги отражают структуру,
размер определяется ролью в этом кандидате, как разрешено текущим брифом.

| Роль / class | Размер desktop → compact | Weight | Leading | Tracking | Color | Отступ до / после |
| --- | --- | --- | --- | --- | --- | --- |
| Page Title / type-page | 24 → 24 px | 600 | 1.25 | compact, -0.02rem | primary | 0 / 12 px |
| Section Title / type-section | 20 → 20 px | 600 | 1.25 | 0 | primary | 24 / 16 px |
| Subsection Title / type-subsection | 16 → 16 px | 600 | 1.25 | 0 | primary | 16 / 8 px |
| Card Title / type-card | 16 → 16 px | 600 | 1.25 | 0 | primary | 0 / 8 px |
| Navigation Label / type-nav | 14 → 14 px | 400, active 600 | 1.5 | 0 | muted, active primary | 0 / 0, target ≥44 px |
| Field Label / type-label | 14 → 14 px | 600 | 1.5 | 0 | secondary | 0 / 8 px |
| Body Text / type-body | 16 → 16 px | 400 | 1.5 | 0 | primary | 0 / 12 px при соседнем абзаце |
| Secondary Text / type-secondary | 14 → 14 px | 400 | 1.5 | 0 | secondary | 8 / 0 px |
| Supporting Text / type-support | 14 → 14 px | 400 | 1.5 | 0 | muted | 8 / 0 px |
| Caption / type-caption | 12 → 12 px | 400 | 1.5 | 0 | muted | 4 / 0 px |
| Status Text / type-status | 12 → 12 px | 500 | 1.5 | 0 | semantic level | 0 / 0, wrap allowed |
| Numeric Highlight / type-number | 24 → 24 px | 600 | 1.25 | compact, -0.02rem | primary | 4 / 4 px |

Отступы задаются layout-контейнером и группировкой, без скрытых margins в heading
classes. Нет механического clamp(). Название переносится по словам, без hyphens;
длинный непрерывный identifier имеет emergency wrap. Значимые данные не обрезаются.
На mobile заголовок списка или evidence становится Page Title с той же ролью.

## Продуктовый смысл текста

Просмотр предложения, принятие правки, одобрение точного текста и отправка отклика
сохраняют отдельные значения. Не подтверждены Playwright и руководство; эти риски
видны перед действиями. Cedar сохраняет противоречие удалённой/гибридной работы.
Демо-профиль не меняет реальную authentication или права. Employer history требует
подтверждённой связи; название компании её не создаёт.

## Проверки и review

Результаты после реализации, сравнение BEFORE/AFTER и ограничения записываются
в README и REVIEW. Статус остаётся awaiting-review до решения владельца.

## Inventory заголовков

[Полный inventory до](validation/typography/before/inventory.json) и
[после](validation/typography/after/inventory.json) содержат видимый текст, tag,
class, font, size, weight, leading, tracking, color, tooltips и placeholders.
Таблица ниже объясняет структурные роли, включая прежние strong/p-заголовки.
H5/H6 в кандидате не используются; уровни проверяются в каждом видимом состоянии.

| Текст / группа | HTML после | Визуальная роль |
| --- | --- | --- |
| Название вакансии Northstar / Cedar | H1 workspace; H2 в скрытом mobile workspace | Page Title |
| Вакансии в списке | p desktop; H1 mobile list | Section Title / Page Title |
| Обзор вакансии | H2 | Section Title |
| Требования и мой опыт | H2 | Section Title |
| Правка резюме | H2 | Section Title |
| Работодатель | H2 | Section Title |
| История | H2 | Section Title |
| На чём основан текст | H2 desktop; H1 mobile evidence | Section Title / Page Title |
| Профиль и доступ | H2 в dialog | Section Title |
| API и PostgreSQL / Playwright в обзоре | H3 | Card Title |
| Регрессионные тесты API на Python и pytest | H3 | Card Title |
| Playwright в рабочих проектах | H3 | Card Title |
| Уточнить опыт тестирования API | H3 | Card Title |
| Перед использованием текста | H3 | Card Title |
| История общения пока недоступна | H3 | Card Title |
| События этого примера | H3 | Card Title |
| Анализ выполняется / нет анализа / ошибка | H3 | Subsection Title |
| Подтверждения устарели / отсутствуют / pending | H3 | Subsection Title |
| Правка принята, текст ещё не одобрен / Cedar conflict | H3 | Subsection Title |
| От текста к источнику | H3 desktop; H2 mobile evidence | Subsection Title |
| Для администратора | H3 | Subsection Title |
| Сейчас / Предлагаемая правка | H4 | Field Label |
| Почему эта правка / Подтверждения / Что нельзя добавлять | H4 | Field Label |
| Одобрение текста | H4 | Subsection Title |
| Следующее действие, меняется по состоянию | p | Subsection Title; не новый раздел |
| Названия компаний | p / strong | Secondary Text; не раздел |
| Названия вакансий в списке | span | Card Title; часть button |
| Орбита решения / demo profile name | strong | Secondary Text / Field Label |
| Профиль в trigger | strong | Field Label; часть button |
| Название текущего mobile level / меню / вкладки | strong / summary / a | Navigation Label |
| CVortex | strong | Существующая brand роль |
| Salary range | div с numeric span и unit span | Numeric Highlight + Secondary Text |
| Badge / состояние правки / подпись «2 примера» | span | Status Text; не heading |

## Примеры текстов

| До | После | Смысл |
| --- | --- | --- |
| Следующий шаг: проверить предложение | Проверьте предложенную правку | Посмотреть и проверить; не принять автоматически |
| Зачем | Почему эта правка | Обоснование предложения |
| Риск | Что нельзя добавлять | Неподтверждённые Playwright и руководство остаются явно запрещены |
| Текст не одобрен, warning в исходном состоянии | Ожидает проверки, informational | Одобрение по-прежнему недоступно до принятия |
| В приложении пока недоступно | История общения пока недоступна | Требование подтверждённой связи с работодателем сохранено |
| Только вымышленные события | События этого примера | Demo boundary указан в шапке и тексте раздела |
| Правка принята · текст не одобрен | Правка принята, текст не одобрен | Принятие остаётся отдельным от approval |

## Зарплата и статусы

Northstar: исходные `240000` и `290000` рублей, Cedar: `250000` и `300000`.
Отображение «240–290 тыс. ₽» и «250–300 тыс. ₽» математически эквивалентно исходным
суммам. Десятичного округления нет. Числа не разрываются и не обрезаются; строка
«В месяц, до налогов» видима на всех размерах. Accessible label содержит полные
суммы, валюту, период и налоги. `salary=missing` не содержит выдуманных сумм,
периода или налогов. Это formatter двух существующих synthetic fixtures, не
новый production salary contract.

| Уровень | Состояния | Presentation |
| --- | --- | --- |
| neutral | Отклонённая правка, обычная метаинформация | Muted, явная подпись, без красной рамки |
| informational | Предложение ожидает проверки; loading/empty в state panel | Violet для предложения, blue для состояния; текст причины |
| success | Подтверждения есть; точный текст одобрен отдельным действием | Confirmed green; статус не означает отправку |
| warning | Pending fact, stale source, accepted but unapproved | Amber, явная причина и следующий шаг |
| error | Нет подтверждений, Cedar conflict, failed analysis, unsupported edit | Blocked/danger, причина остаётся видимой; gates закрыты |

Отказ переводит сообщение действия в `role=status`, даже после ошибки editing;
ошибка неподтверждённой правки остаётся `role=alert`. Color не несёт единственный
смысл. Отказ в блокированном состоянии не удаляет причину блокировки или риск.

## Сравнение screenshots

Каждая пара снята с одного viewport, fixture и действия. BEFORE использует
сохранённые до начала изменений исходники; AFTER использует финальный кандидат.
Снимки производные, baseline approval не устанавливают.

| Экран | BEFORE | AFTER |
| --- | --- | --- |
| Desktop 1440, обзор | [До](validation/typography/before/1440-overview.png) | [После](validation/typography/after/1440-overview.png) |
| Desktop 1440, материалы | [До](validation/typography/before/1440-materials.png) | [После](validation/typography/after/1440-materials.png) |
| Desktop 1440, отказ | [До](validation/typography/before/1440-rejected.png) | [После](validation/typography/after/1440-rejected.png) |
| Mobile 390, обзор | [До](validation/typography/before/390-overview.png) | [После](validation/typography/after/390-overview.png) |
| Mobile 390, материалы | [До](validation/typography/before/390-materials.png) | [После](validation/typography/after/390-materials.png) |
| Mobile 390, отказ | [До](validation/typography/before/390-rejected.png) | [После](validation/typography/after/390-rejected.png) |

Агент оценивает композицию и читаемость по изображениям отдельно от PASS тестов.
При проверке glyph providers выявлен системный STIX Two Math для знака ₽ в Arial.
Локальный Space Grotesk содержит этот glyph, поэтому знак получает ту же гарнитуру,
что цифры. Нового font asset нет.

В первом просмотре обнаружены лишний зазор после diff, ослабленный цвет ссылки
действия и одинаковый вес названий/пояснений в account menu. Все три исправлены
до финального прогона. Полный охват визуального просмотра и численные результаты
находятся в README. Владелец ещё не одобрил дизайн.
