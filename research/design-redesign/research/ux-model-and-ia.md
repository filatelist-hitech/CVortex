# UX diagnosis, модель и IA

Дата: 2026-10-07. Статус: exploratory proposal. Sources: [реестр evidence](sources.md); ограничения: [design plan](design-plan.md). Оценки — design inference, без пользовательских замеров.

## Что существует сейчас

На base `a62ff0899ea9a2dd2266eb5a972d618f803c9952` `page.tsx` открывает `AccessShell`, затем `CareerWorkspace`. В `career-workspace.tsx:85–96` размещены extraction/manual input, pending facts, confirmed facts, Claims и вложенный VacancyWorkspace. В `vacancy-workspace.tsx:262–285` — source import/list, source snapshot, requirements, recommendation, seven dimensions, gaps, unknowns, preparation; ниже — draft review с Before/After/Reason/Risk, provenance и отдельным approval. Diagnostics имеет отдельный admin-only переход.

Следовательно, список `Dashboard / Applications / Vacancies / Companies / Career / Documents / Research / Settings` из brief — **концептуальная модель для пересмотра, не наблюдавшееся текущее production menu**. Основной дефект по коду — длинный последовательный просмотр и равный визуальный вес блоков. Материальные связи уже моделируются и доступны в disclosures, но не собираются вокруг текущего решения. Нельзя утверждать измеренное число лишних переходов или потерянных секунд: текущую authenticated production UI-сессию и usability measurement эта задача не проводила.

Актуальная зрелость: Career/Vacancy/draft core есть; полный package, employer journey, interviews и analytics из vision не считаются готовыми. Preview 0.1 real-user acceptance отсутствует в текущем state, M2 gate сохраняется. Четыре previews показывают synthetic future workflow, а не выдаются за текущее приложение.

## Ответы на research questions

| Вопрос | Предлагаемое решение | Основание / tradeoff |
|---|---|---|
| Первые 5 секунд | Что срочно, что требует моего решения, какое действие следующее, что сохранено при сбое. Показывать ближайшее интервью/approval/conflict, а не общий «success score». | J2/R3/O2; выбор конкретного приоритета — inference. Срочность не должна всегда побеждать важность. |
| Applications: table / board / timeline / grouped list? | Default: фильтруемый list/table с next action + selected detail. Board — optional stage view тех же records; timeline — events внутри employer/opportunity. | J4/W4/W5. Board проще объясняет стадии, но хуже сравнивает salary/unknowns и разрастается. Grouped list лучше на mobile. |
| Vacancy/Application workspace | Одна opportunity сохраняет vacancy snapshot, match, preparation и current stage. Sections меняют task canvas, inspector показывает выбранное evidence/employer. Resume versions и cover — связанные артефакты, не отдельные универсальные экраны. | J1/J3/R1/R4/W3. Inspector требует приоритизации при узком viewport. |
| Career Fact Base без CRUD | Группировать по experience stories и career track, затем capabilities и usages. Из story видно, какие facts confirmed, pending или deprecated. Форма импорта — контекстная задача, не главный экран. | AI1 + J11 + продуктовый invariant; это преимущественно inference, не доказанная практика карьерных графов. |
| Content → Claim → Fact | На выбранном фрагменте компактный «2 facts / 1 claim»; открыть точную wording, source excerpt, статус и версию. Отсутствующий/устаревший provenance виден сразу и блокирует approval; полный graph on demand. | AI1/AI3 + ADR-0009. Скрытый claim-level conflict недопустим даже в режиме высокой density. |
| Employer Memory | Сначала commitments, unresolved contradictions и следующий разговор; далее timeline и linked applications/documents. Различать importer text, confirmed association и подтверждённые statements. | R1/R2/R4 + ADR-0009. Company name из vacancy не подтверждает association; текущий runtime не поддерживает показ всех vision-функций. |
| AI recommendations | Сохранять Before/After, Reason, Evidence и Risk. Accept/Edit/Reject непосредственно у diff; отдельный content approval привязан к exact revision. Edit инвалидирует prior validation. Background status внутри той же opportunity. | AI1–AI3. Экран может потребовать review нескольких фрагментов; не одобрять всё одним неясным bulk action. |
| Density | Always visible: company/role, stage, current source freshness, blockers, primary action, human approval state. Contextual: dimensions, selected evidence, commitments, diff reason/risk. On demand: raw source, full claim path, revisions, detailed research/event history. | R1/W1/W3/O4/O5. Disclosure уменьшает шум, но не должен скрывать blocker/required approval. |
| Mobile PWA | Один active task; native select для opportunity/stage, затем выбранный review и evidence disclosure. Список/дополнительный контекст открываются явно. Сохранить return position и keyboard semantics, touch 44px. | Mobile layouts — inference на основе repository responsive/accessibility constraints. Прототипы responsive web, без service worker/offline-write реализации. |

## Восемь конкретных принципов

1. **Отклик держит контекст:** переключение resume/match/history не сбрасывает selected vacancy и source revision. J1/J3/R1/W1.
2. **Статус объясняет следующее действие:** `Prepare` сопровождается «review one change», а `Apply` — отдельной ручной фиксацией. J1/J10/O5.
3. **Неизвестное и неподдержанное различаются:** неизвестная зарплата требует вопроса; отсутствующий факт запрещает карьерное утверждение. J7/AI1 + ADR-0009.
4. **Approval рядом с exact content:** действие видно вместе с wording, reason, risk и source, а не в отдельном chatbot. AI3 + current draft contract.
5. **Один review не означает отправку:** accept → exact content approval → отдельный human external action. AI3/J8/J9 + PROJECT.md.
6. **Показывать связанные объекты по задаче:** на diff — facts, на interview — история/commitments, на Today — события и blockers. R1/R4/O4.
7. **Employer Memory выводит противоречие раньше chronology:** chronology помогает проверить его, но не заменяет блокирующее решение. R1/R2 + consistency invariant.
8. **Density меняет composition, не размер шрифта:** compact rows на desktop; active task первым на mobile; secondary data раскрывается. W4/W3 + Design-Foundation.

## CURRENT → PROPOSED

Recommended IA proposal: **Today / Opportunities / Career / Employers / Settings**. Единственный центр работы — opportunity workspace. Today выбирает задачу; Career поддерживает evidence; Employers агрегирует подтверждённый контекст. Documents и Research имеют contextual входы и global library/search по мере роста, без обязательных top-level страниц на ранней стадии.

| CURRENT в brief | PROPOSED | Причина и UX benefit | Tradeoff |
|---|---|---|---|
| Dashboard | Today: urgent/review/waiting + linked events | Сразу видно действие и blocker. | Требуется честная, объяснимая prioritization; никакой выдуманной AI urgency. |
| Applications + Vacancies | Opportunities; saved/analyzing/preparing/applied как views | Vacancy до отклика и application после него сохраняют одну identity/context. | Нужна ясная терминология; не выдавать «saved» за «applied». Data model здесь не меняется. |
| Companies | Employers с подтверждёнными associations | Context across applications: commitments, documents и conversations. | Company text недостаточно; неподтверждённая связь остаётся unknown. |
| Career | Career: tracks → experience stories → facts/usages + review queue | Карьерный капитал виден в контексте использования. | Создание фактов сложнее админ-формы; доступ к exact record должен остаться быстрым. |
| Documents | Resume/Cover в opportunity + optional version library | Адаптация всегда связана с требованиями и employer. | Global base resume требует отдельного удобного входа через Career/library. |
| Research | Evidence/research внутри opportunity/employer + searchable library | Вывод и источник рядом с решением; меньше context switching. | Cross-company market research потребует отдельного library view позже. |
| Settings | Settings / integrations / privacy; Diagnostics — отдельный admin scope | User decisions отделены от operations. | Ошибки, влияющие на draft, всё равно нужны inline в workspace. |

Actual runtime composition → first candidate composition: **CareerWorkspace containing VacancyWorkspace → persistent opportunity selection + contextual Career evidence + draft review**. Это направление будущей задачи после выбора, не разрешение реорганизовать production сейчас.

## Отношения и переходы

```
Career source → pending fact → human confirmation → confirmed fact
confirmed fact → Claim → proposed text → accept/edit/reject → exact content approval
Vacancy snapshot → normalized requirements ↔ confirmed career evidence
Opportunity → Application → package versions / events / outcomes
Confirmed employer association → shared commitments → contradiction check
```

Визуализация relations: A — выбранная queue row и inspector; B — record context и inline provenance; C — bounded evidence path без бесконечной network hairball; D — сохранённый context между этапами. Никакой graph не превращает imported data в подтверждённую истину.

## Проверяемая гипотеза после выбора

Три сценария: (1) за 5 секунд определить next action и blocker; (2) открыть supporting fact и одобрить точную правку без неподтверждённой новой компетенции; (3) найти и объяснить employer contradiction до подготовки сообщения. Измерить время/ошибки/понимание на пользователе, сравнить с текущим UI. Этот эксперимент не выполнен в текущем bounded exploration.
