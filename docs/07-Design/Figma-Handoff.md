---
title: CVortex Figma Bootstrap and Token Handoff
status: completed
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [design, figma, tokens, handoff, phase-07]
related:
  - "[[Design-Foundation]]"
  - "[[../03-ADR/ADR-0013-figma-visual-source|ADR-0013]]"
  - "[[../03-ADR/ADR-0014-git-design-tokens|ADR-0014]]"
---

# CVortex Figma Bootstrap and Token Handoff

## Capability outcome

No callable Figma MCP/canvas tool or connected Figma file is available in the current execution environment. No Figma write, import, sync, or review operation was attempted or claimed. This deterministic manual bootstrap is therefore the Phase 07 handoff; it is sufficient without blocking the phase.

Under ADR-0013, a human-reviewed Figma file becomes the visual/component source of truth. Under ADR-0014, [`brand/tokens/cvortex.tokens.json`](../../brand/tokens/cvortex.tokens.json) remains the only machine-readable authority. A live Figma connection is not a build dependency.

## Canonical file and pages

Create one Figma file named `CVortex Design System`. Add pages in this exact order; page identifiers are stable and must not be repurposed.

| Page | Purpose and ownership |
| --- | --- |
| `00 Cover` | project identity, `CVortex — Your career, in context.`, version/date, links to Git token file and review status; owner records review decision |
| `01 Foundations` | principles, typography, contrast/accessibility, grid/layout, icon and content guidance; changes require design review |
| `02 Tokens` | visual representation of Git tokens and semantic mappings; never an independent editable machine source |
| `03 Components` | approved component taxonomy, variants, states, annotations and `draft/reviewed/deprecated` status |
| `04 Patterns` | review, approval, evidence, error/block, form, filter/table patterns; synthetic data only |
| `05 Desktop` | representative foundational desktop layouts for density, evidence and navigation validation; not the whole product |
| `06 Mobile` | corresponding compact PWA layouts and responsive behavior; not a native-app spec |
| `07 Prototypes` | only key interaction validation flows, with no business logic claim |
| `08 Archive` | deprecated/superseded explorations, visibly non-authoritative and linked to replacement/reason |

## Deterministic bootstrap procedure

1. Create the file and pages above. In `00 Cover`, add the Git commit SHA or reviewed token version, date, responsible reviewer and a link/path to `brand/tokens/cvortex.tokens.json`.
2. In `02 Tokens`, create a `CVortex / Dark` variable collection if the target Figma plan supports Variables. Create a `Dark` mode only; do not invent a light mode. If Variables are unavailable, create read-only labelled token tables from the same JSON and record `Variables unavailable` on the Cover.
3. Map primitive color groups (`color/navy`, `neutral`, `cyan`, `blue`, `violet`, `green`, `amber`, `red`) first. Then map semantic names (`surface/*`, `text/*`, `border/*`, `accent/*`, `state/*`) to their Git aliases. Do not replace semantic aliases with detached literals.
4. Add typography, spacing, radii, elevation, motion, breakpoint and layer tables exactly from the token file. Figma's representation may be limited; retain the Git path as the reference in every table/style description.
5. In `01 Foundations`, place the contrast, focus, keyboard, reduced-motion, responsive, content and iconography rules from [[Design-Foundation]]. Use the specified Lucide source, 16/20/24 px size scale, 2 px stroke, semantic color inheritance, Figma naming and decorative/informative accessibility treatment. Do not represent `PENDING` and `CONFIRMED` with color alone.
6. In `03 Components`, create only the taxonomy candidates from [[Design-Foundation]], each with accessibility annotation and state matrix before review. Components remain `draft` until visually reviewed; no auto-publish claim.
7. Use synthetic fixtures only, for example `Candidate A`, `Example employer`, `Evidence ref-001`. Do not copy career data, recruiter messages, credentials, API tokens, or production screenshots.
8. Run the review checklist below. Mark the Cover as `Reviewed` only after a human reviewer records outcome and token version. Otherwise it remains `Draft / awaiting review`.

## Naming convention

| Object | Convention | Example |
| --- | --- | --- |
| Git token path | lowercase dot path | `state.pending` |
| Figma variable/style | slash-separated Git equivalent | `State/Pending` |
| Component | PascalCase noun | `StatusBadge` |
| Variant property/value | PascalCase property, semantic value | `Status=Pending`, `State=FocusVisible` |
| Pattern/frame | `Pattern / purpose / state` | `Pattern / Career Fact Review / Pending` |
| Layout frame | `Surface / breakpoint / purpose` | `Desktop / Review / Evidence panel` |
| Status | exact lower-case value | `draft`, `reviewed`, `deprecated` |

Use `AI recommendation`, `Pending confirmation`, `Confirmed`, `Blocked`, and `Evidence available` as user-facing text. No fake confidence percentage or ambiguous green/amber dot.

## Handoff, drift and review

```text
Git DTCG tokens
  → deterministic JSON/reference validation
  → mapped Figma variables/tables
  → human-reviewed visual/component baseline
  → later frontend transformation and rendered UI validation
```

Git token change starts a review: update the Figma representation, compare token paths/values/aliases, record the Git revision on `00 Cover`, and obtain visual review before treating the Figma state as current. A proposed Figma visual correction that needs a token change is returned to Git for a reviewed token edit; direct Figma changes never silently win machine-token authority. Future transformation/synchronizer choice is deferred to a Phase 08 spike; no custom synchronizer is created here.

Before review, verify:

- page order, names and purposes match this document;
- all semantic color paths resolve to primitive values in the Git token file;
- rendered text/control/focus contrast is assessed on actual dark surfaces;
- `PENDING`, `CONFIRMED`, `BLOCKED`, AI recommendation, and evidence states have distinct text, icon/pattern and recovery/action semantics;
- component annotations cover keyboard, focus, labels, errors, loading and disabled behavior;
- desktop and mobile samples preserve evidence and manual approval clarity;
- the Cover lists the Git revision, reviewer, date and `draft` or `reviewed` truthfully;
- no private data, secrets, credentials or unpublished personal material exists in the file.

## Phase 08 implementation note

Repository Bootstrap may consume the JSON only after Phase 07 passes independent review and Phase 08 is explicitly authorized. It must first select and validate a DTCG transformer against the token types actually used, verify Space Grotesk licensing/loading, generate frontend outputs deterministically, and add rendered contrast/keyboard/reflow/reduced-motion checks. It must not treat this documentation as a license to create product screens or change the approved design direction.
