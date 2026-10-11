# Внешний UX research: реестр источников

Research/access date: **2026-10-07**. Review after: **2027-01-07** для product capabilities. Даты публикации не подменяются датой доступа. «Не указана» означает отсутствие подтверждённой точной даты; relative updated badges не превращены в точную календарную дату.

Это desk research официальных описаний, help pages и опубликованных примеров. Не проводились авторизованные product sessions или пользовательское исследование конкурентов. Confidence относится к существованию документированного паттерна; эффективность для CVortex остаётся inference. Продуктовые обещания роста response rate не использованы как доказательство.

## Job-search products

| ID / source | Publication / update | Evidence / finding | Relevance / inference | Confidence |
|---|---|---|---|---|
| J1 [Teal: tracking applications](https://help.tealhq.com/en/articles/14435727-how-to-track-your-job-applications) | Точная дата не указана | Spreadsheet-style tracker, stages, notes, contacts и resume рядом с вакансией; guidance зависит от этапа. | У отклика должна быть постоянная рабочая область; потерянная вакансия требует сохранённого snapshot. | High для documented pattern; эффективность не измерена |
| J2 [Teal: interviews](https://www.tealhq.com/tool/interview-tracker) | Не указана | Интервью связывает дату, формат, контакт, раунд и notes с job. | В A/D показывать ближайшее событие и подготовку, а не голый счётчик. | High / official product page |
| J3 [Huntr: Job Card](https://help.huntr.co/en/articles/12640406-understanding-the-job-card) | 2025-10-24 | Job Card собирает description, salary, deadline, application log, interviews и документы. | Хорошая единица контекста для B. Цветовая кодировка сама по себе не подходит CVortex: необходимы текстовые состояния. | High |
| J4 [Huntr: Job Board](https://help.huntr.co/en/articles/13413245-the-job-board) | 2026-04-30 | Настраиваемые stage columns; drag-and-drop и Move button. Между отдельными boards перенос jobs не поддерживается по этой статье. | Board пригоден для общей стадии; разделение треков на независимые boards может фрагментировать Employer Memory. | High |
| J5 [Simplify: job tracker](https://help.simplify.jobs/en/articles/2140179-using-the-job-tracker) | Exact date не указана; relative updated badge | Jobs, statuses, documents/history в одном месте; фильтры company/role/date/type/stage и сохранение фильтра. | Сохранённый вид «нужен мой review» полезнее меню сущностей. Автоматическую фиксацию submission не переносим без доказанного действия. | High |
| J6 [Simplify Copilot](https://simplify.jobs/copilot) | Не указана | Inline autofill, resume tailoring и tracking заявлены на product page. | AI полезен в момент задачи; auto-apply и score не соответствуют CVortex invariants. | Medium: marketing evidence |
| J7 [Jobscan tutorial](https://www.jobscan.co/jobscan-tutorial) | Не указана | Report показывает Match Rate и skill/keyword comparison; vendor рекомендует порог score. | Берём раскрываемый requirement context; отвергаем трактовку score как вероятности интервью и добавление неподтверждённого опыта. | High для UI-паттерна; claims об эффективности не подтверждены |
| J8 [LinkedIn Job tracker](https://www.linkedin.com/help/linkedin/answer/a8684146) | Exact date не указана; relative update | Stages, notes, date filters, connections; различает Easy Apply и off-site manual stage tracking, описывает удаление после года. | Явно маркировать происхождение status и ручную фиксацию. Долговременная Employer Memory не должна молча исчезать. | High |
| J9 [Indeed My Jobs](https://support.indeed.com/hc/en-us/articles/205332490-My-Jobs-Section-Overview) | Не указана | Saved/Applied/Interviews/Archived; неполная история, off-site submissions требуют manual changes. | «Нет события» не означает «не откликался»; CVortex должен сохранять свои records и историю. | High |
| J10 [hh.ru: понятные статусы](https://hh.ru/article/statusy-otklika-na-hhru-stali-ponyatnyeye) | 2025-04-03 | «Собеседование» заменяет «Приглашение»; label описывает происходящее понятнее пользователю. | Термины должны говорить о реальном состоянии, а status не должен обещать больше, чем известно. | High |
| J11 [Habr Career: vacancies](https://career.habr.com/vacancies?remote=1&sort=salary_desc&type=all) | Текущий динамический список | Salary visibility и отдельное «зарплата не указана»; remote filter в URL. | В dimensions отделяем неизвестную зарплату от несовпадения; не выводим общий market percentile из малого набора saved jobs. | Medium: dynamic public page |

## CRM и relationships

| ID / source | Date | Evidence / finding | Relevance / inference | Confidence |
|---|---|---|---|---|
| R1 [Attio: understanding records](https://attio.com/help/reference/attio-101/attios-data-model/understanding-records) | Не указана | Table/kanban/page представляют один record; record включает overview, activity, relationships, notes/tasks/files. | Employer context должен быть связан с откликами и обязательствами; табы/inspector сохраняют выбранный record. | High |
| R2 [folk: reminders](https://help.folk.app/en/articles/5664265-frequent-reminders-best-practices) | 2025-02-19 | Follow-up reminder связан с контактом после call/interview/meeting. | Event должен вести к конкретной задаче и работодателю; не создавать шумную общую activity feed. | High |
| R3 [folk: notifications center](https://help.folk.app/en/articles/8249797-notifications-center) | Exact date не указана | Central tasks/notifications, переход из уведомления в связанный contact profile. | A: action queue → соответствующий context. Рекомендации и failures не должны растворяться в toast. | High |
| R4 [HubSpot: record layout](https://knowledge.hubspot.com/records/work-with-records?link-not-found=true) | 2026-04-23 | Properties, middle timeline/overview и associated-record sidebar; отдельно указано различие новых аккаунтов Free/Starter. | B: стабильное разделение task и context. Не копировать все CRM cards и кастомизацию; варианты layout зависят от product rollout. | High для описанного варианта |

## Productivity / work management

| ID / source | Date | Evidence / finding | Relevance / inference | Confidence |
|---|---|---|---|---|
| W1 [Linear: Peek](https://linear.app/docs/peek) | Не указана | Space preview, arrows для соседних items, Escape; preview встроен в command menu. | Быстрый context drill-down сохраняет list position. В CVortex обязательно добавить видимую pointer/touch альтернативу. | High |
| W2 [Linear: custom views](https://linear.app/docs/custom-views) | Не указана | Filtered board/list можно сохранить; contextual views рядом с соответствующей областью. | «Нужно одобрить», «ждём ответа», «интервью» как виды одной коллекции. | High |
| W3 [Notion: layouts](https://www.notion.com/help/layouts) | Не указана | Детали в раскрываемой панели; tabs могут содержать linked database views. | B: не держать все свойства на экране; связь документов и вакансии может быть contextual. | High |
| W4 [GitHub Projects: view layouts](https://docs.github.com/en/issues/planning-and-tracking-with-projects/customizing-views-in-your-project/changing-the-layout-of-a-view) | Не указана | Table для плотных metadata, board для колонок, roadmap для дат; views одной модели. | Table default + optional stage grouping; timeline нужен для событий, не всех application attributes. | High |
| W5 [ClickUp: list vs board](https://help.clickup.com/hc/en-us/articles/6310314670359-List-view-vs-Board-view) | Не указана | List sorting/statuses; board grouping/subgroups и bulk operations. | Не вводить столько режимов, что пользователь сначала выбирает инструмент; начать с одного default. | High |
| W6 [Jira: view work item](https://support.atlassian.com/jira-software-cloud/docs/view-a-work-item/) | Не указана | List/board открывают item; J/K навигация по соседним records, search/filter. | Keyboard efficiency для активного списка без page hopping; не перетаскивать workflow-схему Jira целиком. | High |

Height рассматривался как кандидат из brief. По двум targeted primary-domain поискам не получен достаточный актуальный официальный источник. Текущая доступность/закрытие **не подтверждены**, интерфейс Height не используется как current evidence. Это ограничение покрытия, а не придуманная проверка.

## Observability / developer tools

| ID / source | Date | Evidence / finding | Relevance / inference | Confidence |
|---|---|---|---|---|
| O1 [Sentry: breadcrumbs improvement](https://sentry.io/changelog/improved-breadcrumbs-on-issues/) | Older material; точная дата не извлечена | Timeline обновлён; nested scrolling заменён View All/slide-out для breadcrumbs. | События работодателя — компактная timeline с disclosure; избегать маленьких scroll-клеток. | High для описанного historical pattern |
| O2 [Vercel: observability](https://vercel.com/docs/observability) | 2025-11-07 | Insight sections для архитектурного контекста, событий и investigations. | Состояние → причина → drill-down; operational UI полезен только когда ведёт к действию. | High |
| O3 [Grafana: dashboard links](https://grafana.com/docs/grafana/latest/visualizations/dashboards/build-dashboards/manage-dashboard-links/) | Не указана | Различает dashboard/panel/data links; links могут сохранять variables/time range. | При drill-down сохранять выбранную vacancy, фильтр и source revision. Самими charts проблему поиска работы не решить. | High |
| O4 [Datadog: Events Explorer](https://docs.datadoghq.com/events/explorer/navigate/) | Не указана | Event selection открывает side panel с messages/tags; facets/time range и query в URL. | A/B: selected record + inspector без потери списка; badges передают actionable status. | High |
| O5 [Raycast: Action Panel](https://manual.raycast.com/action-panel) | Не указана | Contextual actions, primary action, search, keyboard и Escape/back. | Один primary action для текущего state; command menu ускоряет, но не скрывает доступные действия. | High |

## AI-native UX и assets

| ID / source | Date | Evidence / finding | Relevance / inference | Confidence |
|---|---|---|---|---|
| AI1 [Google PAIR: Explainability + Trust](https://pair.withgoogle.com/guidebook-v2/chapter/explainability-trust/) | Foundational; точная дата страницы не указана | Объяснения data sources, unknown information и привязка объяснения к действию; calibrated trust. | «Что использовано / чего не хватает / что изменится» возле review. Model confidence не означает карьерную истину. | High для guidance; transfer — inference |
| AI2 [Microsoft HAX guidelines](https://www.microsoft.com/en-us/haxtoolkit/ai-guidelines/) | Исследовательская основа 2019; current toolkit page | Evidence-based guidelines охватывают initial interaction, during use, wrong output и over time. | Встроить исправление/отклонение и ограничение capabilities в основной workflow. | High / primary research toolkit |
| AI3 [GitHub Copilot review](https://docs.github.com/en/copilot/how-tos/use-copilot-agents/request-a-code-review/use-code-review?tool=mobile&trk=public_post_comment-text) | Не указана | Inline suggested changes можно apply/discard; applied changes не становятся автоматически commits. | Отделить accept/edit/reject от финального human approval и внешнего действия. | High |
| F1 [Space Grotesk upstream](https://github.com/floriankarsten/space-grotesk) | Pinned revision в assets/font-source.json | Шрифт распространяется под SIL OFL 1.1; сохранены файл лицензии, revision и checksum. | Offline self-contained research previews. Production acquisition/loading decision не изменён. | High |

## Синтез, отдельно от evidence

**Inference:** application workspace B лучше сохраняет dense context; A помогает выбрать задачу, C объясняет доказанный капитал, D помогает освоить процесс. Источники подтверждают существование patterns, но не доказывают конкретный выигрыш CVortex в секундах/конверсии.

**Alternatives:** board удобен для стадий, table — для сравнения признаков, grouped list — для действия, timeline — для событий. Hybrid должен оставаться несколькими видами одних records, а не четырьмя несовместимыми системами.

**Weaknesses / transfer limits:** tracking products могут неполно учитывать off-site действия; CRM automation может нарушить human approval; work tools могут породить лишнюю иерархию; observability charts могут выглядеть информативно без полезного next action; AI score провоцирует ложную уверенность. Эти риски — design inference, кроме явно документированных history/off-site ограничений J8/J9.

**Нужное дальнейшее доказательство:** после выбора направления проверить на реальном пользователе три задачи: решить should-I-apply с evidence, одобрить правку без новой выдуманной компетенции, найти противоречие Employer Memory. Дать 5-секундную экспозицию home и спросить, что срочно/что блокирует/что дальше. До этого оценки концептов — экспертные гипотезы.
