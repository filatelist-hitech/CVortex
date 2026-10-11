---
title: Application Workspace — exact canonical B01 React port
status: owner-accepted
owner: project
created: 2026-10-11
execution:
  workflow: implementation
  orchestration: native
  agents: 1
  parallelism: false
git:
  write: true
  base: stage
  target: stage
  branch: feature/b02-global-shell-navigation
---

# Application Workspace reference port

## Authority

Explicit owner IMPLEMENT override, 2026-10-11: port canonical B01 to React,
including list, workspace sections, recommendation/evidence and approval controls.
Owner separately authorized the current dirty B02 branch in this chat. NEXT remains
the manual/accessibility release gate; this override permits implementation only.
This bounded task spans the visual surfaces planned in B03–B09 without renaming
those roadmap rows, adding missing domain capabilities or authorizing later work.

## Contract

Canonical `docs/07-Design/reference/` is read-only. Preserve exact composition,
CSS values, copy, icons and breakpoints. Split native DOM into React components;
no iframe, static HTML embedding or alternate design system. Keep auth, Career
Facts, Diagnostics, existing API actions and deep links. Synthetic Northstar/Cedar
fixtures are available only with an explicit local development/demo flag plus
demo URL, inside actual authenticated access. No backend/API/DB changes or fake
persistence. Existing contracts supply live data; absent salary, employer history
and recommendation support must remain honestly unknown/unavailable.

## Validation fixed before implementation

Paired Chromium captures: ru-RU, Europe/Moscow, DPR 1, zoom 100%, reduced motion,
same fixtures/selection/section/state. Viewports 1440×900, 768×1024, 390×844,
320×640. No masks or baseline updates. Geometry tolerance ≤1 CSS px; computed
font/line-height/tracking/color/padding/radius values must match exactly.
Pixel comparison uses per-channel threshold 16/255 (antialiasing), workspace/list
changed-pixel ratio ≤0.5%; full-page ratio ≤2% for intentional authenticated
chrome differences. Account menu has real identity/logout/role-gated Diagnostics,
no demo role switch: report its differing content separately, compare shared
geometry/typography and functional access instead of claiming identical content.
Thresholds must not be relaxed after results. Capture all eight scenarios, five
sections, Cedar conflict, accepted/approved/rejected/edit and list/evidence levels.
Save pairs, diff PNGs, geometry/style metrics and a comparison report.

Run TypeScript, ESLint, Vitest, production build; browser auth/logout/register,
Diagnostics denial/admin access, Career Facts, Back/Forward, focus/Escape/traps,
unsaved edits, reduced motion, no overflow/browser errors/external requests.
Integrity must prove unchanged canonical/backend/tokens and preservation of
pre-existing dirty files outside the explicitly edited frontend/state scope.

## Completion and STOP

Record component mapping, source/style derivation, functional and visual evidence,
startup command/URL, deviations and owner-review requirements. Update STATUS;
NEXT and manual accessibility gate remain unchanged. No commit/push/PR/release,
real AI request, fact confirmation or employer action. Stop at this port.


## Resumed implementation outcome — 2026-10-11

Local technical/visual PASS, awaiting owner review. Both installed `apple-design`
and `frontend-design` SKILL.md files were read; implementation application and
canonical-overrides are recorded in [component mapping](../../docs/07-Design/validation/application-port-2026-10-11/component-mapping.md).
Fresh evidence: **108 Vitest tests**, TypeScript/ESLint/production build PASS;
**117 workspace + 41 Career + 50 motion browser checks**; **97 visual cases +
142 scroll pairs / 717 PNGs**, geometry/style differences zero, maximum core
**0.2423% < 0.5%**. Account content exception is local/measured with raw diff
retained, not a whole-state exemption. **1318 protected files** retained from
actual interrupted dirty checkout; canonical/backend/tokens unchanged.

[Final report, metrics, gallery, limitations and replay](../../docs/07-Design/validation/application-port-2026-10-11/README.md).
Canonical reference and accepted ADRs are unchanged. NEXT/release accessibility
gate stay OPEN; owner review, native zoom/manual AT and live authenticated Laravel
are not claimed. No commit/push/PR/B03/release or provider/employer action. STOP.

## Owner acceptance and delivery authorization — 2026-10-11

The owner accepts the «Орбита решения» visual direction and React implementation at
the scope recorded in the linked validation report. This later decision supersedes
the earlier awaiting-owner-review status; it does not claim review of every state or
viewport. Manual screen-reader, native 200% zoom, real authenticated Laravel E2E and
the ADR-0025 production release gate remain open/not tested. The owner separately
authorized Git consolidation, commits, push of this feature branch and a PR to
`stage`; that Git authorization does not authorize merge or release.
