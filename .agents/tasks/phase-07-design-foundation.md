---
title: CVortex Phase 07 — Design Foundation
status: completed
phase: 07-design-foundation
owner: project
created: 2026-09-12
updated: 2026-09-12
tags:
  - task
  - design
  - figma
  - accessibility
related:
  - ../../PROJECT.md
  - ../state/STATUS.md
  - ../../docs/03-ADR/INDEX.md
---

# CVortex — Phase 07: Design Foundation

## Goal

Создать design foundation CVortex, который станет visual/interaction baseline для последующего Repository Bootstrap и UI implementation, не реализуя business features и не создавая весь frontend application.

Результат Phase 07 должен определить:

- canonical design tokens;
- typography;
- spacing;
- radii;
- shadows/elevation;
- motion principles;
- breakpoints;
- semantic colors;
- component taxonomy;
- accessibility rules;
- Figma file/page structure;
- token-to-Figma/frontend handoff strategy;
- deterministic fallback, если Figma write capability недоступна.

## Execution mode

Следуй `/AGENTS.md`, `.agents/policies/resource-usage.md` и current project state.

Используй один агент и task-relevant context. Не перечитывай все design/research artifacts целиком. Открывай только документы и accepted ADR, непосредственно влияющие на design foundation.

Если Figma integration/tool доступен, сначала проверь реальные capabilities. Не придумывай write/import/sync возможности Figma.

## Minimum context

Прочитай:

1. `/PROJECT.md`.
2. `/.agents/state/STATUS.md` и `NEXT.md`.
3. результаты Phase 06, прежде всего Product, Architecture и UX/design-relevant requirements.
4. accepted ADR, относящиеся к Figma, design tokens, frontend boundaries и documentation.
5. существующие brand/design artifacts и documentation map.
6. applicable scoped `AGENTS.md` только для изменяемых каталогов.

Используй indexes и direct links. Не загружай весь repository.

## Brand invariants

Использовать утверждённый бренд:

```text
CVortex
Your career, in context.
```

Visual direction:

- dark-first;
- deep navy / black;
- cyan / blue / violet accents;
- Space Grotesk;
- clean technical UI;
- accessibility-first.

Не менять product name, slogan или fundamental visual direction без отдельного decision process.

## Source of truth

Следуй repository instruction priority.

Figma является reviewed visual source of truth в рамках accepted ADR. Machine-readable design tokens должны соответствовать accepted token ADR и repository architecture.

Если Phase 07 обнаруживает реальный конфликт между accepted design ADR, Phase 06 requirements и существующим brand material:

- зафиксируй конфликт;
- не выбирай молча новый визуальный курс;
- создай/amend/supersede ADR только если material architectural/design-system decision действительно меняется;
- не использовать временное удобство tooling как основание для изменения brand architecture.

# Scope

Разрешено:

- design documentation;
- token definitions в canonical machine-readable location, если location уже принята architecture или должна быть определена в рамках accepted ADR;
- brand/design assets metadata и structure;
- Figma structure/bootstrap instructions;
- component taxonomy/specifications;
- accessibility baseline;
- responsive/breakpoint strategy;
- interaction/motion guidance;
- state documentation updates;
- минимальные validation scripts/config только если они прямо нужны для deterministic validation design tokens и разрешены current phase.

Если создание executable tooling выходит за Phase 07 boundary, вместо него документируй exact deterministic instructions для Phase 08.

# Non-goals

На этой фазе НЕ:

- создавать полноценный Next.js application;
- реализовывать product pages;
- писать business features;
- реализовывать backend;
- создавать authentication UI;
- реализовывать Career/Vacancy/Application functionality;
- создавать migrations;
- устанавливать произвольные frontend dependencies только ради прототипа;
- делать production CSS/component library;
- начинать Phase 08;
- менять product architecture;
- создавать десятки speculative components;
- придумывать новые brand colors/fonts без необходимости.

# Design Principles

Зафиксируй principles, достаточные для будущего implementation:

- information density without visual noise;
- dark-first, но не «всё чёрное»;
- semantic state clarity;
- progressive disclosure;
- strong distinction between fact, recommendation, warning and blocking error;
- provenance visibility where user decisions depend on evidence;
- human approval states visually explicit;
- accessibility and keyboard usability first-class;
- responsive PWA-first behavior;
- consistency over novelty;
- deterministic token-driven styling.

Избегай декоративной complexity, которая затрудняет scanning job-search data.

# Design Tokens

Подготовь canonical token model в соответствии с accepted design-token ADR.

Минимально определить token groups:

## Color

Разделить primitive и semantic tokens.

Primitive examples могут покрывать:

- navy/black surfaces;
- neutral scale;
- cyan scale;
- blue scale;
- violet scale;
- success/warning/error/info primitives.

Semantic tokens должны выражать роль, а не конкретный цвет:

```text
surface.canvas
surface.default
surface.elevated
surface.interactive
text.primary
text.secondary
text.muted
border.default
border.focus
accent.primary
state.success
state.warning
state.danger
state.info
state.blocked
```

Не хардкодить semantic usage на literal colors в design documentation.

Проверить contrast requirements для text, controls, focus и disabled states.

## Typography

Space Grotesk является primary brand typography согласно project direction.

Определи:

- font families/fallbacks;
- type scale;
- font weights;
- line heights;
- letter spacing where justified;
- display/title/body/label/code roles;
- minimum readable sizes;
- responsive behavior.

Не предполагай наличие font file licensing/distribution rights. Если self-hosting требует отдельной проверки, зафиксируй это как implementation concern.

## Spacing

Определи небольшой coherent spacing scale.

Требования:

- tokenized;
- predictable rhythm;
- supports dense desktop workflows;
- usable on mobile;
- no arbitrary one-off spacing values без причины.

## Radii

Определи minimal radius scale и semantic usage для cards, inputs, modals, pills/badges.

Не превращать интерфейс в коллекцию разных случайных скруглений.

## Shadows / Elevation

Dark UI требует осторожной elevation model.

Определи:

- surface hierarchy;
- border vs shadow usage;
- overlay/modal elevation;
- focus ring не заменяется shadow.

## Motion

Определи motion principles:

- duration tokens;
- easing categories;
- permitted transition types;
- reduced-motion behavior;
- no motion that blocks workflows;
- loading/progress feedback rules.

`prefers-reduced-motion` должен учитываться в future implementation.

## Breakpoints

Определи responsive breakpoints based on layout needs, а не конкретные устройства.

Учитывай:

- mobile PWA;
- tablet/narrow desktop;
- standard desktop;
- wide workspace.

Не создавать excessive breakpoint taxonomy.

## Z-index / Layers

Если необходим, определи small semantic layering model для base content, sticky navigation, dropdown/popover, modal, toast.

# Semantic UI States

Спроектируй consistent visual semantics минимум для:

- neutral/default;
- hover;
- active;
- selected;
- focus-visible;
- disabled;
- loading;
- success;
- warning;
- error;
- blocked;
- pending human confirmation;
- confirmed;
- rejected/deprecated;
- AI-generated/recommended;
- provenance/evidence available.

Truth-first states должны быть визуально различимы. `PENDING` Career Fact не должен выглядеть как `CONFIRMED`.

# Component Taxonomy

Определи taxonomy, а не production implementation.

Минимально рассмотри:

## Primitives

- Button;
- IconButton;
- Link;
- Input;
- Textarea;
- Select/Combobox;
- Checkbox;
- Radio;
- Switch;
- Badge/Status;
- Tooltip;
- Divider;
- Progress/Spinner/Skeleton.

## Composition / Navigation

- App shell;
- top/sidebar navigation;
- breadcrumbs where useful;
- tabs;
- command/search surface if justified;
- responsive navigation.

## Data / Review

- Table/DataGrid pattern;
- list/card pattern;
- filter/search controls;
- diff / Before-After pattern;
- evidence/provenance disclosure;
- recommendation card;
- confidence/status indicator without fake scoring;
- timeline/history;
- activity/audit view.

## Feedback

- inline validation;
- alert/banner;
- toast;
- confirmation dialog;
- blocking error;
- empty state;
- error state;
- permission denied;
- network/offline state.

## Domain patterns

Определи patterns, но не business implementation, для:

- Career Fact confirmation;
- Vacancy analysis;
- Match dimensions;
- Resume recommendation review;
- Cover letter review;
- Employer consistency conflict;
- Truth Guard block;
- Application status timeline.

Не создавать component только потому, что название домена существует.

# Accessibility Foundation

Design foundation должен учитывать минимум:

- WCAG-oriented contrast;
- keyboard navigation;
- visible focus;
- semantic HTML expectations;
- labels and descriptions;
- error identification;
- reduced motion;
- touch target size;
- screen-reader status/live-region needs;
- no color-only communication;
- zoom/reflow;
- logical tab order;
- modal focus management expectations.

Зафиксируй accessibility acceptance rules для future components.

# Content and Information Hierarchy

Определи principles:

- concise labels;
- explicit action verbs;
- evidence before persuasion;
- destructive/blocking actions visually distinct;
- AI suggestion clearly distinguishable from user-confirmed fact;
- no dark patterns around approval;
- user always sees when final action is manual.

# Figma Structure

Спроектируй canonical Figma pages:

```text
00 Cover
01 Foundations
02 Tokens
03 Components
04 Patterns
05 Desktop
06 Mobile
07 Prototypes
08 Archive
```

Для каждой page укажи purpose и ownership rules.

## 00 Cover

- project identity;
- version/status;
- useful links;
- reviewed baseline indicator.

## 01 Foundations

- principles;
- typography;
- accessibility;
- layout/grid;
- iconography guidance;
- content guidance.

## 02 Tokens

- visual representation of canonical tokens;
- semantic mapping;
- do not make Figma the only machine-readable token storage if ADR says Git tokens are canonical machine source.

## 03 Components

- component set taxonomy;
- states/variants;
- accessibility annotations;
- status: draft/reviewed/deprecated.

## 04 Patterns

- approval flows;
- data review;
- recommendation review;
- errors/blocks;
- forms;
- tables/filters.

## 05 Desktop / 06 Mobile

Only representative foundational layouts/screens required to validate system behavior. Не проектируй весь продукт.

## 07 Prototypes

Use only for key interaction validation.

## 08 Archive

Deprecated/superseded design artifacts, clearly separated from reviewed truth.

# Figma Integration

Если Figma MCP/connected tooling доступен:

1. проверь current supported capabilities;
2. используй только capabilities, которые реально доступны;
3. не утверждай write/sync/import operation, если она не выполнена;
4. не копируй secrets/tokens в docs;
5. сохраняй traceability между Git token source и Figma representation.

Если write capability отсутствует или unreliable:

создай deterministic manual/bootstrap instructions, включающие:

- exact page structure;
- token import/mapping steps;
- component naming convention;
- variable collections/modes, если поддерживаются target Figma workflow;
- review checklist;
- expected outputs;
- how to confirm Figma matches Git source.

Не блокируй Phase 07 только потому, что Figma нельзя программно изменить, если reproducible handoff достаточно для следующей фазы.

# Token Handoff

Определи handoff flow conceptually:

```text
canonical Git tokens
→ validation
→ Figma representation
→ reviewed visual source
→ frontend consumption/transformation in later phase
```

Зафиксируй ownership и drift detection expectations.

Не реализовывай speculative custom synchronizer, если accepted architecture не требует его сейчас.

# Naming Conventions

Определи naming rules для:

- design tokens;
- Figma variables/styles;
- components;
- variants;
- patterns;
- frames/pages;
- statuses.

Имена должны быть semantic, stable и friendly to future automation.

# Documentation

Создай/актуализируй design docs в существующей repository structure.

Минимально documentation должна покрывать:

- Design Foundation overview;
- token model/source of truth;
- typography/colors/spacing/motion/responsive rules;
- component taxonomy;
- accessibility baseline;
- Figma structure/handoff;
- implementation notes for Phase 08.

Не создавай кладбище микрофайлов. Предпочитай coherent authoritative docs.

# Research

Не проводи broad design-system research заново.

Targeted research разрешён только если:

- accepted ADR требует current capability verification;
- Figma integration capability изменилась;
- font/license/accessibility/standard detail materially влияет на решение;
- existing source of truth содержит gap.

Используй official/primary sources first.

# Security / Privacy

Design artifacts не должны содержать реальные private Career Facts, recruiter messages, secrets или production data.

Для examples используй synthetic fixtures.

Не помещай API keys, tokens или credentials в Figma/documentation.

Design patterns должны поддерживать безопасное поведение для:

- permission denied;
- sensitive data display;
- secret masking;
- cross-user isolation errors;
- Truth Guard blocks;
- manual approval.

# Validation

Phase 07 считается design/documentation phase.

Выполни минимально достаточную validation:

## Consistency

- brand name/slogan unchanged;
- tokens согласуются с accepted ADR;
- Figma/Git source-of-truth roles не противоречат ADR;
- semantic tokens map to defined primitives;
- component taxonomy использует defined states/tokens;
- desktop/mobile principles не противоречат responsive PWA direction;
- Truth-first states visually distinct;
- no product architecture changed silently.

## Accessibility

Проверь documented contrast targets and state communication. Если есть token tooling/validator, запусти его. Если нет, не заявляй автоматическую проверку.

## Documentation

- links/navigation updated;
- no broken internal links where repository validation exists;
- frontmatter and naming follow policy;
- Figma manual/bootstrap instructions deterministic if needed.

# Completion Criteria

Phase 07 PASS только если:

## Foundation

- canonical token source confirmed/documented;
- color primitives and semantic colors defined;
- typography system defined;
- spacing/radius/elevation scales defined;
- motion/reduced-motion rules defined;
- breakpoints/responsive strategy defined;
- layering rules defined if needed.

## Components

- component taxonomy exists;
- interaction states documented;
- domain review patterns identified without implementing business features;
- Truth-first status patterns documented.

## Accessibility

- accessibility baseline exists;
- focus/keyboard/contrast/non-color communication expectations documented;
- responsive/reflow principles documented.

## Figma

- canonical Figma page structure defined;
- actual Figma capability checked if integration used;
- write operations performed only if supported;
- deterministic manual/bootstrap instructions exist otherwise;
- Git/Figma handoff and drift expectations documented.

## Scope

- business features not implemented;
- full frontend app not built;
- no backend/migrations/infrastructure created;
- Phase 08 not started.

## State

- docs/map updated as needed;
- STATUS updated;
- NEXT points only to canonical Phase 08;
- BLOCKERS contains only real blockers.

# State update

После успешного завершения:

- `STATUS.md`: Phase 07 completed, artifacts/validation/limitations;
- `NEXT.md`: expected `08-repository-bootstrap` or canonical repository name;
- `BLOCKERS.md`: only real blockers.

Не добавляй backlog в NEXT.

# Final Report

Кратко выведи:

1. Result: PASS/PARTIAL/BLOCKED.
2. Files created/changed.
3. Token/Figma source-of-truth outcome.
4. Figma capability actually used or manual fallback created.
5. Validation actually executed.
6. Blockers/limitations.
7. Exact next phase.

Не пересказывай design docs целиком.

# STOP

После Phase 07 остановись.

Не начинай Repository Bootstrap.
Не создавай Next.js/Laravel applications.
Не устанавливай dependencies.
Не реализовывай UI business features.
Не начинай Phase 08 в этой session.
