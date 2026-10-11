---
title: ADR-0025 — B01 reference acceptance and mandatory release validation
status: accepted
decision_nature: OWNER_CONSTRAINT
owner: project
created: 2026-10-11
updated: 2026-10-11
tags: [governance, design, accessibility, release]
related: [ADR-0024]
---

# ADR-0025 — B01 reference acceptance and mandatory release validation

## Context and authority

The CVortex owner explicitly requested final acceptance of B01 «Орбита решения»,
a minimal governance amendment, and B02 execution after valid closure on
2026-10-11. This is an owner-approved amendment through the repository architecture
decision workflow and ADR index; it is effective locally without implying Git delivery.
ADR-0024 and the Handoff previously required all manual validation before reference
closure. That conflict is resolved only for B01 by this decision, not silently waived.

## Options

1. Keep B01 blocked until every manual check: strongest pre-implementation evidence,
   but prevents the owner's requested production implementation.
2. Accept the visual/product reference now and retain mandatory manual checks as a
   production release gate: allows bounded implementation with explicit residual risk.
3. Waive manual accessibility checks: rejected; removes required evidence.

## Decision

Choose option 2. B01 may be **ACCEPTED** with lifecycle `reviewed` as an
implementation visual baseline only when all four conditions are recorded:

- Explicit owner visual/product acceptance for the current canonical reference.
- Successful automatic baseline, with source/token/report integrity verified.
- Documented residual risks and exact untested scope; no invented manual PASS.
- An open mandatory follow-up task with named evidence requirements, blocking
  production release until its checks pass and blocking findings are resolved.

The owner reviewer identity is “владелец продукта CVortex”; no personal name is
invented. Acceptance approves the reference, not every state/viewport or accessibility.
All existing manual matrix rows retain their outcomes, including **NOT TESTED**.
The follow-up is [B01 manual/accessibility release validation](../../.agents/tasks/b01-manual-accessibility-release-gate.md).
It includes the full existing state/viewport and interaction matrix, K1 keyboard/focus,
Z1 native 200% zoom/reflow and A1 human screen-reader reading/announcements.
Production components need equivalent applicable checks; reference checks alone
cannot approve shipped accessibility. Neither owner risk acceptance nor agent/DOM/AX
checks can close that task. No production release while it is open or has FAIL findings.

B02's separately explicit owner authorization becomes executable after this closure.
This decision authorizes no B03, release, Preview 0.1 acceptance, M2, data changes,
commit, push, PR, automatic snapshot update, or employer communication.

## Relationship to existing decisions

Amend only ADR-0024's timing of B01 manual validation and Handoff/B01 closure rules.
Keep its single reference, token authority, synthetic fixtures and production-fixture
retirement rules. Retain every accessibility obligation in Design Foundation and
Handoff. All other references retain the existing closure process.

## Consequences and rollback

Known risks remain: unverified human readability across states/viewports, complete
keyboard/focus behavior, zoom clipping/overlap/control access, and audible AT behavior.
These are accepted for implementation, not release. Automatic checks are unchanged.
Historical reports and acceptance records retain dates and exact outcomes.
A future decision can restore pre-implementation gates; it must preserve this record
and the open follow-up. No backend/schema/dependency rollback is required.
