# Четыре направления и сравнение

2026-10-07. Expert design inference. **Awaiting user design selection**. [Evidence sources](sources.md), [UX/IA](ux-model-and-ia.md), [browser validation](../validation/browser-results.json).

## A — Career Command Center

Core idea: центр оперативного внимания. Mental model: «какие решения и события требуют меня сегодня?» Primary screen: Today, next interview, pipeline, selected decision, employer events. Hierarchy: urgent action → relevant queue → selected record → supporting history. Navigation: Today и anchor-входы к queue/events/interviews; filters и command search. Relationships: queue row → inspector → evidence/employer. AI integration: review decisions в очереди, объяснение selected action; бот не занимает основную область. Density: **High**.

Strengths: быстрый обзор нескольких opportunities, видно urgency/conflict/approval одновременно, filters пригодны для power user. Weaknesses: сложный draft требует отдельной work surface; operational panel может отвлекать от глубокого review. Best workflows: ежедневный triage, интервью и follow-up preparation. Risks: ложная urgency, перегрузка при >50 applications, priority without reason. Desktop: dense table, selected inspector и связанная history; default показывает три требующих решения, All active раскрывает семь. Mobile: primary event и selected decision раньше queue, table превращается в labelled rows; secondary anchors убраны. Прототип не реализует полноценный interview management.

## B — Application Workspace

Core idea: один контекст конкретной opportunity. Mental model: «я работаю над этим откликом, всё нужное рядом». Primary screen: Northstar resume suggestion с Before/After, Reason/Risk и inspector. Hierarchy: company/status → decision rationale → active task → related evidence/employer. Navigation: persistent opportunity list, local section selectors и contextual search. Relationships: opportunity → resume revision → Claim → Career Fact; opportunity → employer commitments/history. AI integration: inline diff и accept/edit/reject; content approval отдельный. Density: **Medium–High**.

Strengths: лучшее сохранение контекста, provenance рядом с wording, удобный глубокий review; модель подходит существующим Career/Vacancy/draft entities. Weaknesses: overview всей кампании слабее A; три области давят на tablet, нужны строгие приоритеты inspector. Best workflows: should-I-apply, resume adaptation, cover review, employer consistency. Risks: перегрузить local tabs и inspector; неоправданно показать ещё не реализованные employer/history capabilities. Desktop: list + task + inspector, local tab не сбрасывает company. Mobile: native opportunity selector и active task; extra context ниже с disclosures. Prototype edit допускает только две exact synthetic wordings; unsupported text остаётся BLOCK. Это демонстрация fail-closed review, не production Truth Guard.

## C — Career Intelligence

Core idea: раскрыть доказанный карьерный капитал и его applicability. Mental model: «что о моей карьере подтверждено и где пригодится?» Primary screen: experience → capability/evidence → linked opportunities, selected story и pending review. Hierarchy: track → supporting facts → relevant roles → actionable gap. Navigation: career tracks, capability selection, linked opportunity inspect. Relationships: bounded three-column path и selected provenance story, без BI charts. AI integration: suggested reuse и отдельная проверка pending fact. Density: **Medium**.

Strengths: наглядно объясняет происхождение match, помогает onboarding и осмысленно искать пробелы. Weaknesses: дольше добираться до ежедневного application action, возможна иллюзия «полной карты рынка» по нескольким saved roles. Best workflows: Career Fact Base review, career-track exploration, interview stories и reusable evidence. Risks: inferred skills стать псевдо-фактами; факт после confirmation ошибочно превратится в approved content. Preview сохраняет границу: confirmation не создаёт новый Claim/готовый text. Desktop: направленный evidence path. Mobile: capabilities и selected story раньше source history; opportunities и review ниже; профиль — поздний supporting context. Market здесь — исключительно saved fixtures, не live market analytics.

## D — Career Flow

Core idea: объяснимое движение с gates. Mental model: «где я, что готово, что мешает и как продолжить?» Primary screen: Prepare, exact wording review и readiness checklist. Hierarchy: active stage → prerequisite → next human action → downstream context. Navigation: Discover/Analyze/Prepare/Apply/Communicate/Interview/Outcome, stage queue. Relationships: stages несут ту же vacancy, evidence и employer commitments. AI integration: bounded suggestion внутри этапа, progress не заменяет approval. Density: **Medium**.

Strengths: хорошая обучаемость, ясные human boundaries, простая mobile task surface. Weaknesses: реальный поиск нелинеен: parallel rounds, returns, multiple conversations; stage не должен запирать пользователя. Best workflows: первый отклик, подготовка пакета, понятные manual confirmation checkpoints. Risks: progress theatre и переход stage без реального действия. Preview разделяет navigation, exact content approval и ручной application record; contradiction оставляет continuation disabled. Desktop: семь стадий + queue/task/context. Mobile: current-stage native selector, active task первым, queue после него. Full interview/outcome edits вне bounded preview.

## Comparison

Шкала **1–5**: 1 — заметное ограничение, 3 — пригодно с tradeoffs, 5 — strongest conceptual fit. Это относительная экспертная оценка **этих prototypes**, не usability score, не performance benchmark и не объективная точность AI. Суммарный рейтинг не вычисляется: критерии имеют разный вес.

| Criterion | A | B | C | D | Обоснование |
|---|---:|---:|---:|---:|---|
| Information clarity | 4 | 5 | 4 | 5 | A яснее campaign status; B связывает decision/detail; C требует понимания evidence path; D наиболее явно показывает state/gate. |
| Daily workflow speed | 5 | 5 | 3 | 4 | A быстро выбирает task, B выполняет review без page hopping; C начинает с карьерного контекста, D добавляет stage framing. |
| Context preservation | 4 | 5 | 4 | 4 | B держит selection и inspector постоянно; A раскрывает краткий context, C — выбранный capability, D — stage context. |
| Learnability | 3 | 4 | 3 | 5 | A требует освоить dense queue; B знаком как record workspace; C более абстрактен; D объясняет путь. |
| Power-user efficiency | 5 | 5 | 3 | 3 | A/B дают search, filters и быстрый detail; C/D требуют дополнительных steps при частом переключении opportunities. |
| AI explainability | 4 | 5 | 5 | 4 | B: exact diff/evidence/approval; C: source/evidence/opportunity. A summarises; D объясняет prerequisite, но может спрятать nuance за checklist. |
| Employer context | 4 | 5 | 3 | 4 | B сохраняет commitments рядом с текстом. A alert/timeline, D context carry; C организован вокруг кандидата. |
| Mobile adaptability | 3 | 4 | 3 | 5 | D даёт одну активную задачу; B native selection; A/C требуют сильнее сворачивать overview/path. |
| Scalability | 4 | 5 | 3 | 3 | B tolerates new linked artifacts через contextual sections. A требует careful triage; C — graph complexity; D — branching stages. Не load benchmark. |
| Brand fit | 5 | 5 | 5 | 4 | A/B/C сохраняют technical evidence identity. D сильнее напоминает guided workflow, при этом brand family сохранена. |

## Recommendation

**Recommended overall: B — Application Workspace.** Главная проблема CVortex — связанный карьерный контекст в момент решения/правки. B удерживает одну opportunity и обеспечивает краткий путь от wording к подтверждённым sources, сохраняет место в списке и делает human approval видимым. Это один понятный mental model.

**Recommended for current stage: также B, с ограниченной capability surface.** Будущее production предложение следует начинать с существующих Vacancy/Match/Career evidence/Resume recommendations/Cover drafts. Employer Memory, documents package/export, conversations, interview/outcome не включать как доступные actions до соответствующего подтверждённого backend/product slice. Сначала закончить текущую Preview acceptance; выбор направления не разрешает автоматически M2. В текущих exploration screens будущие возможности явно являются synthetic fixtures.

**Valuable borrowed patterns:** из A — компактный Today/review inbox, который открывает ту же opportunity; из C — on-demand evidence story для выбранного claim; из D — локальный readiness checklist внутри workspace. Не добавлять одновременно command dashboard, career graph и seven-step wizard как три равноправные глобальные системы. Центр остаётся opportunity; заимствования помогают выбрать, объяснить или завершить её task.

## Оставшиеся ограничения

- Пользователь ещё не выбрал направление; никаких final design decisions.
- Нет real-user study, authenticated competitor sessions, assistive-technology review или production browser E2E.
- Отдельный approved logo asset не найден в targeted repository inventory; сохранён существующий wordmark/CSS mark.
- Prototype actions и validation — local simulations, без AI/backend; unsupported arbitrary edits не валидируются.
- Full-page screenshots сделаны из viewport 1440×1000; вертикальная длина различается. Это не четыре фиксированных screenshot полотна одинаковой высоты.
- Accessibility pass ограничен browser keyboard/focus/modal, text contrast, targets/reflow/reduced motion; не является WCAG certification.
- `DESIGN_SOURCE_OF_TRUTH_CONFLICT` сохранён как unresolved decision для следующего этапа.

## После явного выбора пользователя

Обязательная отдельная задача: selected concept → прочитать все relevant design sources → для каждого решения **KEEP / MODIFY / DEPRECATE / SUPERSEDE / CONFLICT** → owner-approved source strategy → final design system → отдельно разрешённая implementation.

| Actual repository source | Что пересмотреть после выбора |
|---|---|
| `docs/07-Design/Design-Foundation.md` | KEEP invariants/accessibility/brand; оценить MODIFY navigation, density, composition, taxonomy, responsive hierarchy. |
| `docs/07-Design/Figma-Handoff.md` | CONFLICT workflow/source authority и доступность real Figma tooling; не переписывать historical evidence. |
| `docs/07-Design/Error-Center-UX.md` | KEEP decision-first diagnostics; проверить совместимость navigation/inspector/spacing с selected direction, retain admin boundaries. |
| `docs/01-Product/Phase-06-Product-Design.md` | KEEP Truth-first/human approval; согласовать описание user flows и entity context при материальных product changes. |
| `docs/01-Product/Vision.md`, `Principles.md`, `Scope.md`, `Glossary.md` | Проверить brand, product intent и терминологию; не расширять scope через визуальный выбор. |
| `docs/03-ADR/ADR-0013-figma-visual-source.md` | Если authority меняется — owner-approved superseding ADR; существующий accepted ADR не редактировать молча. |
| `docs/03-ADR/ADR-0014-git-design-tokens.md` | KEEP one machine-token authority; согласовать derivation и visual reference strategy; supersede только если реальный conflict. |
| `docs/03-ADR/ADR-0009-strict-truth-guard.md` | KEEP invariant; проверить новый review UX против lifecycle/blocking contract. |
| `PROJECT.md`, `docs/02-Architecture/Architecture-Baseline.md` | Согласовать source strategy, если принято новое архитектурно значимое решение. |
| `docs/04-Data/Phase-06-Data-Design.md`, `docs/06-AI/Phase-06-AI-Design.md` | В следующей задаче читать непосредственно relevant lifecycle/provenance/review sections, чтобы UX не придумал отсутствующие состояния. Сейчас не загружались целиком. |
| `brand/tokens/cvortex.tokens.json` | Actual machine-readable source, не `packages/design-tokens/`. Не .md; сохранить Git authority, решить изменения после direction. |
| `apps/frontend/AGENTS.md`, `src/app/globals.css`, existing workspace components | Read-only implementation conventions и actual baseline для будущего reconciliation. |

По targeted filename/map search отдельные `Tokens*.md`, `Components*.md`, `Brand*.md`, `Frontend*.md`, `Accessibility*.md`, `Patterns*.md` не обнаружены. Их relevant contracts объединены в Design-Foundation; точных выдуманных файлов нет. Production style/layout здесь не обновлялся.

Figma reconciliation должна сравнить existing reviewed-Figma strategy с **Design Markdown + versioned Git tokens + HTML reference prototypes/Storybook-equivalent + production components + visual regression**. Последний вариант — кандидат для оценки, не принятое решение и не установленный Storybook.
