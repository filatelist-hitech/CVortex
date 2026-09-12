---
title: CVortex Design Foundation
status: completed
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [design, tokens, accessibility, responsive, phase-07]
related:
  - "[[../03-ADR/ADR-0013-figma-visual-source|ADR-0013]]"
  - "[[../03-ADR/ADR-0014-git-design-tokens|ADR-0014]]"
  - "[[../01-Product/Phase-06-Product-Design|Phase 06 Product Design]]"
  - "[[Figma-Handoff]]"
---

# CVortex Design Foundation

## Intent and authority

**CVortex — Your career, in context.** uses a dark-first, clean technical visual system: deep navy surfaces, cyan/blue/violet accents, and Space Grotesk. The aim is dense, calm review work—not a neon dashboard having an existential crisis.

Authority is deliberately split: [`brand/tokens/cvortex.tokens.json`](../../brand/tokens/cvortex.tokens.json) is the sole canonical machine-readable token source (DTCG 2025.10, ADR-0014); reviewed Figma is the visual/component authority (ADR-0013). Frontend outputs are derived later. A design disagreement is resolved by review and a Git-token update, never by silently editing a second token master.

This foundation is documentation and tokens only. It does not authorize a frontend, components in code, product screens, or Phase 08.

## Principles and content hierarchy

- Put evidence before persuasion: show source/provenance, state, and required action before any recommendation.
- Make a fact, recommendation, warning, and blocking error visually and textually distinct.
- Prefer progressive disclosure: a compact summary reveals details on demand without hiding a block or required approval.
- Use concise noun labels and explicit verb actions (`Confirm fact`, `Resolve conflict`, `Review evidence`); never imply an employer action happened automatically.
- AI output is labelled **Recommended** and never styled as user-confirmed truth. `PENDING` is amber with a text label; `CONFIRMED` is green with a text label. Neither relies on color alone.
- Destructive, blocking, and manual-approval actions require an explicit state and a clear next step. No preselected approval, no dark patterns.
- Prefer tables and structured comparisons for review data; preserve readable card/list fallback on compact layouts.

## Token model

The token file contains primitive colors and semantic aliases, then typography, space, radius, shadow, motion, breakpoint and layer scales. Product/UI documentation and future code consume semantic roles (`surface.default`, `text.primary`, `state.blocked`), never literal color values. Primitives are reserved for token definition and reviewed Figma mapping.

### Color and contrast

`surface.canvas/default/elevated/interactive` establish navy depth. Use borders first to differentiate adjacent dark surfaces; `shadow.raised` is for raised transient surfaces and `shadow.overlay` only for dialogs. A shadow never substitutes a border, focus indicator, state label, or contrast.

Normal text must meet WCAG AA contrast of **4.5:1** against its actual opaque background; large text (at least 24 px normal or 18.67 px bold) must meet **3:1**. Controls, visible focus indicators and meaningful graphical state indicators must meet **3:1**. `text.disabled` communicates unavailable state together with disabled semantics and label—not as an action target. Alpha overlay tokens require compositing before contrast assessment.

### Typography

`font.family.brand` is `Space Grotesk` with system fallbacks; no self-hosting or distribution right is assumed. Phase 08 must confirm acquisition, licence and loading strategy before shipping a font file.

`font.tracking` uses DTCG `dimension` values in `rem` (`-0.02rem`, `0rem`, `0.02rem`) and must be emitted directly as CSS `letter-spacing`; no implicit unit conversion is permitted.

| Role | Size / line height | Weight | Use |
| --- | --- | --- | --- |
| Display | 40 / tight | 700 | sparse page-level moments only |
| H1 | 32 / title | 600 | page heading |
| H2 | 24 / title | 600 | major section |
| H3 | 20 / title | 600 | panel or subsection |
| Body | 16 / body | 400 | minimum normal reading text |
| Body compact | 14 / body | 400–500 | dense tables and supporting text |
| Label | 12–14 / title | 600, label tracking | controls, status, metadata |
| Code | 12–14 / body | 400 | identifiers, immutable references |

Never use text below 12 px. Reduce display hierarchy on compact screens before reducing body text. Long evidence excerpts use body size, relaxed line height, wrapping and a deliberate disclosure—not tiny type.

### Layout, shape, motion and layers

- Space is the `space` scale (4 px base). Standard stacks are 8/12/16/24 px; 32+ is reserved for section separation. One-off values require a documented layout reason.
- `radius.sm` is small controls; `md` is inputs/cards; `lg` is modal/panel; `pill` is badges only. Do not use pills for primary data containers.
- Motion only supports feedback: opacity, transform, disclosure, progress and non-blocking skeletons. Use `motion.duration.fast`, `motion.duration.standard` and `motion.duration.slow`; do not animate layout shifts, critical state changes, or route progress as a delay.
- Under `prefers-reduced-motion: reduce`, remove non-essential transforms and looping effects; retain an immediate static state and textual progress. Loading longer than a brief transition needs a status message/skeleton, with a determinate progress value when known.
- Layers are semantic only: `base` content, `sticky` navigation, `popover` menus/tooltips, `modal` dialog plus overlay, `toast` temporary announcements. New z-index literals are prohibited.

### Responsive PWA strategy

Breakpoints express layout pressure, not named devices: **compact <480**, **tablet ≥768**, **desktop ≥1024**, **wide ≥1440**. Start from one-column, touch-safe mobile PWA composition. At tablet, permit two-column review summaries; at desktop, add persistent navigation and side-by-side evidence; at wide, cap reading measure and retain whitespace rather than stretching tables forever. Important state, approval controls and provenance remain present in every layout; secondary metadata may collapse into disclosure.

Target touch controls at least 44×44 CSS px where practical. Desktop density may use compact rows, but must preserve keyboard targets, readable text, and a non-overlapping focus ring. Support 200% zoom/reflow without two-dimensional page scrolling except intrinsically wide data tables, which require an explicit horizontal-scroll affordance and retained headers.

## Interaction and semantic states

Every interactive component documents default, hover, active, selected (where applicable), `focus-visible`, disabled, loading and error. Hover is enhancement only; keyboard and touch receive equivalent outcomes. Focus-visible uses `border.focus` plus a 2 px outside ring, never color-only selection.

| Semantic state | Required presentation and rule |
| --- | --- |
| Neutral / hover / active / selected | semantic surface/border change; selected has persistent text/icon cue |
| Loading | disabled only when action cannot safely repeat; show spinner/skeleton plus readable status |
| Success / warning / error | icon, label, text explanation and semantic color |
| Blocked | `state.blocked`, stop icon, explicit reason and resolution path; no approval escape hatch |
| Pending human confirmation | `state.pending`, `Pending confirmation` label, source/review action; cannot resemble confirmed |
| Confirmed | `state.confirmed`, `Confirmed` label and confirmation metadata where material |
| Rejected / deprecated | muted status plus explicit word; preserve history/provenance, never silently disappear |
| AI-generated / recommended | `state.recommended`, `AI recommendation` label, explanation and review action; not fact status |
| Provenance available | `state.provenance`, `Evidence available` label/link; opening it shows source context, not a false certainty score |

Truth Guard outcomes are not generic alerts: `BLOCK` uses the blocked pattern; `USER_RESOLUTION_REQUIRED` uses warning plus named conflict and resolution controls; `PASS` may be confirmed only after the governing human-approval state is separately satisfied.

## Component taxonomy and patterns

This is a taxonomy, not a production component backlog. All components inherit the accessibility/state contract above and use semantic tokens.

| Group | Foundation set | Requirements |
| --- | --- | --- |
| Primitives | Button, IconButton, Link, Input, Textarea, Select/Combobox, Checkbox, Radio, Switch, Badge/Status, Tooltip, Divider, Progress/Spinner/Skeleton | semantic native base where possible; labels, descriptions, errors, disabled/loading state, focus-visible |
| Composition / navigation | App shell, top/sidebar navigation, breadcrumbs, tabs, responsive navigation | current location announced; no navigation depends only on icon or hover; command/search surface is deferred until workflow need is confirmed |
| Data / review | Table/DataGrid, list/card, filter/search controls, diff/before-after, evidence disclosure, recommendation card, confidence/status without fake score, timeline/history, activity/audit | sortable/filterable controls expose state; tables have compact fallback; evidence and status retain labels |
| Feedback | inline validation, alert/banner, toast, confirmation dialog, blocking error, empty/error/permission-denied/network-offline state | errors identify field/action and recovery; toast is not the sole record of a critical event; modal manages focus |

Domain patterns compose those foundations: Career Fact review shows status, evidence and explicit confirm/reject; vacancy analysis separates raw source, normalized requirement and recommendation; match dimensions list confirmed evidence versus unknown/unconfirmed; resume/cover review uses diff plus provenance and approval; employer conflict and Truth Guard block expose contradiction/evidence and resolution; application timeline marks manual confirmation distinctly. No pattern may imply that an LLM, imported text, or confidence score confirmed a fact.

## Accessibility acceptance baseline

Future component work is acceptable only when it meets all applicable rules:

1. Use semantic HTML and native controls before ARIA; every control has a programmatic name, visible label unless context safely supplies it, and help/error text is associated.
2. Entire primary workflow is keyboard-operable with logical tab order, visible focus, no keyboard trap (except contained modal focus), and Escape/return-focus behavior for dialogs.
3. Errors are identified in text, linked to invalid fields, and announced when dynamically inserted; asynchronous status uses an appropriate live region without interrupting critical input.
4. Color, position, icon, and motion never carry the only meaning. Status has text and, where helpful, an icon/pattern.
5. Meet the contrast targets above in rendered context; test focus, disabled, error and selected states—not just default text.
6. Respect reduced motion, 200% zoom/reflow, touch targets, text wrapping, and screen-reader reading order. Do not use placeholder text as a label.
7. Dialogs announce title/purpose, constrain focus while open, restore trigger focus on close, and never bury a blocking explanation behind a tooltip.
8. Permission denial, masked sensitive fields, cross-user denial, and offline/error states reveal no private data and provide a safe recovery path.

Implementation must validate these with rendered UI and assistive-technology/browser checks; this phase only sets the baseline.
