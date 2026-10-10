# CVortex UX/UI redesign exploration

> **Subsequent decision — 2026-10-08:** the owner selected **B / Application Workspace**; [design reconciliation](../../docs/07-Design/Concept-B-Reconciliation.md) is completed and [ADR-0024](../../docs/03-ADR/ADR-0024-git-design-reference-workflow.md) resolves the mandatory Figma conflict. The exploration/awaiting-selection statements below record the original 2026-10-07 research outcome. Original prototypes and validation evidence remain exploratory, not pixel-approved production specifications.

**2026-10-07 · completed bounded exploration · awaiting user design selection.**

Primary execution mode: RESEARCH; explicit user override of canonical NEXT, one agent, sequential work, no subagents. Base: `stage` at `a62ff0899ea9a2dd2266eb5a972d618f803c9952`; working branch: `docs/ux-redesign-concepts`. No commit, push, PR, merge, deployment, Figma or production changes.

## Открыть результат

[Галерея четырёх направлений](index.html). Каждый concept — plain HTML/CSS/JS, работает также при открытии `index.html` через `file://`; fonts/data/scripts локальные, никаких API-запросов.

Для browser preview:

```bash
node research/design-redesign/preview-server.cjs
```

Открыть `http://127.0.0.1:8767/`. Это loopback static research preview; если порт занят, задать `CVORTEX_PREVIEW_PORT=8768`. Не production server. Сервер завершается Ctrl+C; файлы продолжают работать offline.

| Concept | Mental model | Runnable preview | Desktop screenshot | Mobile screenshot |
|---|---|---|---|---|
| A · Career Command Center | Внимание и решения сегодня | [HTML](concept-a/index.html) | [PNG](concept-a/concept-a-preview.png) | [PNG](concept-a/concept-a-mobile.png) |
| B · Application Workspace | Один отклик со всем контекстом | [HTML](concept-b/index.html) | [PNG](concept-b/concept-b-preview.png) | [PNG](concept-b/concept-b-mobile.png) |
| C · Career Intelligence | Доказанный опыт и связанные возможности | [HTML](concept-c/index.html) | [PNG](concept-c/concept-c-preview.png) | [PNG](concept-c/concept-c-mobile.png) |
| D · Career Flow | Этап, prerequisites и следующий human action | [HTML](concept-d/index.html) | [PNG](concept-d/concept-d-preview.png) | [PNG](concept-d/concept-d-mobile.png) |

Desktop viewport: **1440×1000**; named preview PNGs — full-page captures, высота зависит от состава экрана. Для сравнения первого viewport: `concept-*/concept-*-viewport.png`. Mobile: **390×844**, full-page плюс `*-mobile-viewport.png`. Демо компании, карьерные факты, зарплаты и события полностью **synthetic**; не факты реального пользователя. `CONFIRMED` — только fixture status.

## Research → UX → IA → recommendation

- [Source register](research/sources.md): job trackers, CRM, productivity, observability, AI review; evidence отдельно от inference, publication/access dates, confidence и freshness.
- [UX diagnosis/model/IA](research/ux-model-and-ia.md): actual source-code baseline, ответы на все девять research questions, восемь principles, CURRENT → PROPOSED с tradeoffs.
- [Все четыре concept briefs, comparison, recommendation и reconciliation inventory](research/concepts-and-comparison.md).
- [Hard invariants, assumptions, brand/assets и Git preflight](research/design-plan.md).
- [Browser evidence](validation/browser-results.json) и [validation notes](validation/README.md).

Diagnosis: текущий Career → Vacancy → draft review организован длинным последовательным экраном; связанные evidence/decisions нужно собрать вокруг opportunity. Это source inspection, не измеренный live production usability result.

Recommended IA: **Today / Opportunities / Career / Employers / Settings**; Documents и Research — contextual sections и searchable library по мере зрелости. Brief menu не выдаётся за actual runtime menu.

Recommendation: **B** overall и для current stage с ограничением visible capabilities до реально реализованного Career/Vacancy/draft core. Borrowed patterns: небольшой review inbox A, evidence story C, локальные readiness checks D. Экспертная гипотеза; окончательное решение не принято за пользователя.

## Конфликт и STOP

**DESIGN_SOURCE_OF_TRUTH_CONFLICT: present.** PROJECT.md, ADR-0013, ADR-0014 и Design-Foundation закрепляют reviewed Figma plus Git tokens. Current user разрешил только HTML exploration и запретил Figma. Existing design authority не изменён, ADR не superseded. HTML/CSS — review proposals, не final design system.

Actual design sources для следующего reconciliation: `docs/07-Design/Design-Foundation.md`, `Figma-Handoff.md`, `Error-Center-UX.md`; `docs/01-Product/Phase-06-Product-Design.md`, `Vision.md`, `Principles.md`, `Scope.md`, `Glossary.md`; ADR-0009/0013/0014; PROJECT.md и relevant architecture/data/AI contracts; actual tokens — `brand/tokens/cvortex.tokens.json`. Подробный список и предмет пересмотра — в comparison report. Эти файлы не изменены.

После явного выбора A/B/C/D или конкретного hybrid: отдельная задача source reconciliation (**KEEP / MODIFY / DEPRECATE / SUPERSEDE / CONFLICT**) → approved design authority strategy → final design system → separately authorized implementation. Canonical Preview/M2 gate не изменён.

**STOP. Awaiting user design selection.**
