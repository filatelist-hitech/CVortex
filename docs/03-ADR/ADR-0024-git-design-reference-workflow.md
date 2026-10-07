---
title: ADR-0024 — Git-reviewed Design References for Application Workspace
status: accepted
decision_nature: DERIVED_ARCHITECTURAL_DECISION
owner: project
created: 2026-10-07
updated: 2026-10-08
tags: [architecture, design, tokens, accessibility, local-first]
related: [ADR-0013, ADR-0014, ADR-0009, ADR-0012]
---

# ADR-0024 — Git-reviewed Design References for Application Workspace

## Context and authority

The owner selected **Concept B / Application Workspace** and explicitly requested reconciliation of the mandatory Figma workflow. Limited demo-plan access is a confirmed owner constraint. The historical Figma handoff records an unreviewed canvas; having a file does not establish an approved visual baseline.

This bounded task authorizes selecting the simplest justified replacement and recording a superseding ADR. Acceptance covers the mental model and design workflow. It does not assert approval of every prototype pixel, a completed production redesign, Preview acceptance or permission to start M2.

## Decision drivers

Local-first review, Git history/rollback, one token authority, executable responsive states, human visual review, accessibility and low maintenance cost on the existing Next.js/React/TypeScript stack. No proprietary service, paid seat or live Figma connection may be a required design/build gate.

## Options and comparison

| Criterion | A: Markdown + tokens + HTML references + production components + visual regression | B: Markdown + tokens + Storybook + production components + visual regression | C: Markdown + tokens + production fixture views + visual regression only |
| --- | --- | --- | --- |
| Local-first / Git | Local files, reviews and snapshots | Local stories/configuration; hosted review optional | Local app/fixtures and snapshots |
| Developer workflow | Small plain HTML reference before implementation; handoff into existing components | Rich component isolation; additional builder/configuration | Reuses Next.js; requires components before rendered design review |
| Accessibility | Native reference states plus manual keyboard/AT review; production checks remain required | Add-on/integration support; manual assessment remains required | Production checks directly exercise shipped semantics |
| Visual / responsive review | Runnable HTML now, shipped-component fixtures later; explicit state/viewport manifest | Component stories and viewport states; full-workflow checks still needed | Real rendering; less convenient before a component exists |
| Maintenance | Temporary HTML duplication, controlled by retirement per implemented pattern | Story authoring, mocks and tool upgrades alongside app upgrades | Lowest extra tooling; fixture isolation must be maintained |
| Dependency cost | No new reference framework; a bounded visual-test dependency may be introduced later | Storybook/framework/builder plus test tooling | Test tooling only, but design review waits on implementation |
| Authority ambiguity | Low only with a single active reference per pattern | Low if stories import production components and tokens | Low; production output still cannot override documented intent automatically |

These costs are project assessments, not measured benchmarks. Official Storybook documentation supports isolated Next.js component development, but exact compatibility with this repository has not been executed. Playwright supports screenshot comparison; consistent capture environments and manually reviewed baseline changes remain necessary.

## Decision

Select **A with a staged handoff into C**. Use Git-held Markdown for behavior/IA, the existing `brand/tokens/cvortex.tokens.json` for machine values, reviewed runnable references for visual states and recorded human review plus visual regression for drift detection.

For each pattern, exactly one executable visual reference is active:

1. Before production implementation: a reviewed local HTML reference identified by file/revision, state and viewport. Raw Concept B remains exploratory; it is not automatically this reference.
2. Once implemented and reviewed: a deterministic fixture view importing the **actual production component and theme** replaces that HTML reference. Archive the replaced HTML mapping and retain links/history; do not keep two independently maintained component implementations as equal authority.
3. Screenshots are derived comparison evidence. Neither an existing production screen nor an automatically updated golden image approves a design change. An intentional change updates the behavior/specification, tokens if needed, reference manifest and human review record in the same bounded change.

`Design-Foundation.md`, `Application-Workspace.md` and `Design-Handoff.md` define one design system. No second token set, redesign theme or proprietary master is introduced. Global IA is **Today / Opportunities / Career / Employers / Settings**; capability availability follows real domain/API state. Opportunity is a UI projection, not a new persisted domain entity.

Figma becomes **optional auxiliary tooling**, with no required bootstrap, canvas-parity or approval gate. Any optional Figma representation derives from this Git baseline and cannot change canonical tokens or behavior. Storybook is deferred until repeated component-state isolation/mocking needs justify its maintenance cost; reconsider in a separate bounded decision, without changing design authority.

## Relationship to existing decisions

- Supersede ADR-0013's mandatory Figma visual authority and bootstrap/review gate.
- Amend only the Figma-role references in ADR-0014. Retain its accepted Git-held DTCG authority, explicit derivation, drift detection and deterministic-build guarantees.
- Keep ADR-0004/0007/0009/0010/0016/0017 domain, API, ownership, Truth Guard, human approval, rendering and untrusted-input boundaries.

## Consequences

Visual review can proceed offline and before component implementation. Git diffs, source revisions and synthetic fixtures make changes reviewable. Human review is still required; browser inspection by an agent is not a real-user usability study or screen-reader acceptance.

Temporary reference duplication requires a retirement record. Snapshot drift is sensitive to fonts/browser/OS; pin capture conditions, distinguish platforms and inspect diffs. Visual regression and token derivation are **planned**, not installed or implemented by this ADR. Reversibility: replace the reference host through a bounded review; restore mandatory Figma only through a new owner-approved ADR.

## Security and validation impact

Only synthetic fixtures enter shareable design references and screenshots. No account credentials, private career sources, recruiter messages or production incidents. Local preview is loopback-bound and isolated from the application API. No automatic baseline update, cloud upload, live AI retry or employer action.

Validation must combine rendered review, responsive/reflow checks, keyboard/focus/return, contrast, manual assistive-technology assessment and state/provenance assertions. A screenshot alone does not prove ownership, factual support or approval semantics.

## References

- [Reconciliation inventory and evidence](../07-Design/Concept-B-Reconciliation.md).
- [Design handoff contract](../07-Design/Design-Handoff.md).
- [Application Workspace](../07-Design/Application-Workspace.md).
- [Storybook Next.js/Vite documentation](https://storybook.js.org/docs/get-started/frameworks/nextjs-vite), accessed 2026-10-07; integration compatibility remains untested.
- [Playwright visual comparisons](https://playwright.dev/docs/test-snapshots), accessed 2026-10-07.
- [Playwright accessibility testing](https://playwright.dev/docs/accessibility-testing), accessed 2026-10-07; automated checks do not replace manual assessment.

## Supersedes

[ADR-0013](ADR-0013-figma-visual-source.md). Amends the Figma role in [ADR-0014](ADR-0014-git-design-tokens.md); does not supersede its token decision.

## Superseded By

None.
