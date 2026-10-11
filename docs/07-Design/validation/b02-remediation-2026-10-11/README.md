---
title: B02 visual parity remediation
status: owner-accepted
owner: project
created: 2026-10-11
updated: 2026-10-11
tags: [design, frontend, validation]
---

# B02 visual parity remediation

Explicit owner IMPLEMENT override of NEXT; existing B02 branch and dirty work
preserved. No commit/push/PR. B01 lifecycle and release gate stay unchanged.

## Before audit (rendered before production edits)

Fresh Chrome screenshots at 1440/768/390/320, synthetic API fixtures:
[`before/`](before/), measured computed styles: [metrics](before/metrics.json).
Agent inspected desktop canonical, desktop production and mobile production.
Different product content is not a parity defect.

| Severity | Observed deviation | Canonical → production mapping / remediation |
| --- | --- | --- |
| CRITICAL | Horizontal oversized navigation inside header; mobile wall of buttons | `.global-menu/.global-nav` → WorkspaceShell separate desktop sidebar, tablet row, mobile disclosure |
| CRITICAL | Framed giant header; identity and logout consume another row | `.site-header/.brand/.account-trigger` → compact WorkspaceShell brand/context/profile, authenticated account dialog |
| CRITICAL | Career h1 up to 64px; inflated supporting tagline | `.type-page/.type-section/.type-support` → CareerWorkspace 24/20/14px canonical token roles |
| MAJOR | English primary controls; inappropriate Career slogan | Russian shell, access and Career copy, factual description |
| MAJOR | Decorative background gradients, large margins/cards and custom focus colors | Canvas/default surfaces, canonical spacing/radii and cyan focus |
| MAJOR | Weak form border, arbitrary sizes | Canonical default border and 44px controls, 16px input text |
| MINOR | Brand font falls back; production asset shipping not authorized by B02 | Keep brand stack; disclose Latin Space Grotesk difference, no font acquisition |

Career forms map to reference `.field`, buttons and `.review-card` surfaces;
pending/confirmed provenance remains real Career data. Reference vacancy panes,
demo state selector, art and role switcher do not map to Career and are not copied.
Existing vacancy ingestion gets primary Russian labels; deeper vacancy/draft,
chat and Diagnostics modules retain their existing product behavior/copy.

## Validation / remaining differences

Fresh checks: TypeScript PASS; ESLint PASS, zero warnings; Vitest **94/94 PASS**;
production build PASS (`NODE_ENV=production`). Exact commands/results and logs:
[summary](validation-summary.json), [typecheck](typecheck.log), [lint](lint.log),
[Vitest](vitest.log), [build](build.log). Existing duplicate vacancy fixture key
warnings remain; no assertions were removed or weakened.

[Navigation/auth browser matrix](browser-results.json): **155 checks / 20 captures**
on 320/390/768/1024/1440. [Career/profile regression](career-results.json): **41 checks /
6 captures** covering native modal/Escape/focus return, required fields, manual entry,
extraction into pending candidates, confirm/edit-confirm/reject, inert injected
markup, safe error handling, failed logout/session retention, admin utility routing.
All Career operations hit ephemeral fixtures, not Laravel or persisted facts.

Chrome 148.0.7778.179 on macOS; ru-RU / Europe-Moscow / reduced motion; existing local dev
runtime at <http://127.0.0.1:8080/> renders actual production React components.
Optimized production build is checked separately. Canonical preview:
<http://127.0.0.1:8768/>. No live authenticated Laravel, native zoom, human screen
reader, cross-OS or owner acceptance is claimed. Main matrix: no unexpected
console/page/hydration errors or external requests; Career page exceptions: zero.

Agent inspected fresh BEFORE desktop/mobile, AFTER desktop/mobile and open menu,
all four comparison sheets, desktop/mobile native profile, pending/confirmed
Career states. Compared shared shell/component contracts, not different screen
content. Repeat captures followed typography/form tuning. Profile captures use
viewport screenshots; filled Career captures scroll to top before full-page capture
so sticky placement is not misrepresented. Screenshot sources are not painted over.

| Viewport | Canonical / before / after contact sheet |
| --- | --- |
| 1440 | [Desktop comparison](1440-comparison.png) |
| 768 | [Tablet comparison](768-comparison.png) |
| 390 | [Mobile comparison](390-comparison.png) |
| 320 | [Compact comparison](320-comparison.png) |

Raw [BEFORE desktop](before/1440-production.png), [AFTER desktop](after/1440-production.png),
[canonical desktop](before/1440-canonical.png), [BEFORE mobile](before/390-production.png),
[AFTER mobile](after/390-production.png), [canonical mobile](before/390-canonical.png).
[Computed comparison](style-comparison.json): 24 exact matches for h1/font/color,
textarea/font/surface/radius across four widths. Header is 90px vs canonical 91px:
**MINOR**, reference has a demo-state selector absent from authenticated production.

Critical shared-shell composition/navigation/hierarchy deviations are removed in
agent rendered review. Remaining differences: Latin brand falls back to Arial
because production font shipping is outside B02; legacy deep vacancy/draft/chat/
Diagnostics copy remains English. Career has its own forms and states; the canonical
vacancy panes, demo role menu, demo scenario selector and artwork are not copied.
This B02 remediation report predates the separately authorized exact B01 React
Application Workspace port documented in
`../application-port-2026-10-11/README.md`. That bounded presentation port is now
implemented and accepted; deferred domain capabilities, persistence and later
roadmap tasks remain unimplemented or unauthorized here. No universal pixel
equality claim.

Intermediate failures were corrected: translation accidentally touched Retry helper
names (restored; vacancy behavior diff checked); text assertions were localized
without changing endpoint/payload/retry/security checks; hidden mobile navigation
required a visible main landmark after auth; form busy state required waiting for
completion. Initial FAIL evidence remains in this directory and is superseded by
the dated PASS reports, not by weaker assertions.

Session integrity is recorded in [integrity](integrity-results.json). B01 source,
tokens, backend/API/DB/dependencies, historical evidence and unrelated dirty work
stay unchanged from session start. Canonical derivation and whitespace checks PASS.

At the time this report was written, owner visual review was pending. On 2026-10-11
the owner accepted the «Орбита решения» direction and consolidated React
implementation for the scope in the linked application-port report. This does not
claim personal review of every state or viewport. B01 manual/accessibility release
gate remains OPEN; no manual screen-reader, full native 200% zoom or live
authenticated Laravel E2E PASS is claimed. Git delivery is authorized separately;
this report does not authorize production release. CLOUD_REVIEW_REQUESTED: NO.

## Changed files in this remediation block

- `.agents/state/BLOCKERS.md`
- `.agents/state/STATUS.md`
- `.agents/tasks/README.md`
- `.agents/tasks/b02-global-shell-navigation.md`
- `apps/frontend/src/app/__tests__/diagnostics.test.tsx`
- `apps/frontend/src/app/__tests__/page.test.tsx`
- `apps/frontend/src/app/__tests__/workspace-shell.test.tsx`
- `apps/frontend/src/app/access-shell.tsx`
- `apps/frontend/src/app/career-workspace.tsx`
- `apps/frontend/src/app/globals.css`
- `apps/frontend/src/app/layout.tsx`
- `apps/frontend/src/app/vacancy-workspace.tsx`
- `apps/frontend/src/app/workspace-shell.tsx`

New evidence is confined to this directory. All 1232 other initial files matched
the session-start SHA-256 snapshot, including existing dirty B01 work.
