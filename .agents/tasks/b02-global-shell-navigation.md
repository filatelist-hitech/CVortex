---
title: B02 — Global shell and navigation
status: owner-accepted
owner: project
created: 2026-10-10
updated: 2026-10-10
tags: [task, frontend, navigation, design]
related:
  - docs/07-Design/Concept-B-Reconciliation.md
  - docs/07-Design/Design-Handoff.md
  - docs/07-Design/Application-Workspace.md
  - docs/03-ADR/ADR-0024-git-design-reference-workflow.md
execution:
  workflow: implementation
  orchestration: native
  agents: 1
  parallelism: false
git:
  write: true
  base: stage
  target: stage
  branch: auto
---

# B02 — Global shell and navigation

## Authority and gate

This is an executable implementation contract only after a separate explicit
implementation authorization and closure of B01's canonical reference review.
It is currently prepared, is not the `.agents/state/NEXT.md` target, and does not
authorize code changes. The owner approval recorded for the separate «Орбита
решения» candidate and two-vacancy preview does not close the canonical B01 review
or replace its active mapping.

Roadmap source: [B02 — Global shell/navigation](../../docs/07-Design/Concept-B-Reconciliation.md#bounded-frontend-roadmap).

## Observable outcome

Authenticated users see a responsive, keyboard-operable global shell that retains
the five canonical directions — **Today / Opportunities / Career / Employers /
Settings** — and clearly states which destinations the current frontend supports.
Existing access flows and `/diagnostics` continue to work at their current URLs.
The shell adds no fake data, route, domain capability, or backend operation.

## Verified frontend baseline

- `apps/frontend/src/app/layout.tsx` is the root layout; it currently renders
  `children` without global navigation.
- `apps/frontend/src/app/page.tsx` renders `AccessShell` at `/`.
- `apps/frontend/src/app/access-shell.tsx` checks `/api/v1/me`, owns sign-in and
  registration mode, renders `CareerWorkspace` after login, performs logout with
  `POST /api/v1/auth/logout`, and guards diagnostics to administrators.
- `apps/frontend/src/app/register/page.tsx` serves `/register`; the invitation
  token is read from the URL fragment by `AccessShell` and removed from browser
  history after hydration. Preserve this behavior.
- `apps/frontend/src/app/diagnostics/page.tsx` serves `/diagnostics`; retain its
  admin-only access check and existing diagnostics behavior.
- `apps/frontend/src/app/career-workspace.tsx` renders the Career surface and
  embeds `VacancyWorkspace`.
- `apps/frontend/src/app/vacancy-workspace.tsx` renders the saved-vacancy list
  and analysis beneath the existing `#vacancy-title` heading. This is the only
  current Opportunities destination; do not create another list or record model.
- `apps/frontend/src/app/globals.css` defines the current semantic CSS variables
  consumed by the frontend. `apps/frontend/AGENTS.md` requires ESLint,
  TypeScript, Vitest and production build validation after frontend changes.

## Bounded implementation

1. Add one small shared authenticated shell/navigation component and mount it for
   the existing signed-in Career and Diagnostics surfaces. Keep the login and
   invitation-registration screens intact and outside the authenticated shell.
2. Render the five canonical destinations as text-labeled controls. Map **Career**
   to `/` and **Opportunities** to `/#vacancy-title`; preserve normal browser
   history/deep links. Mark the current destination semantically (including
   `aria-current` where applicable).
3. Keep **Today**, **Employers**, and **Settings** visible with concise
   unavailable-state copy because no corresponding production routes/data
   capability is present in this frontend. They must not masquerade as working
   links, show fabricated records, or imply Employer association/Settings actions.
4. Keep account utilities separate from the five destinations. Preserve the
   existing signed-in identity, role-gated `/diagnostics` link and Sign out
   behavior. Preserve `/`, `/register`, `/diagnostics`, login/register forms,
   invitation token handling and the existing same-origin API contract.
5. Style the shell to follow the approved «Орбита решения» direction and
   `brand/tokens/cvortex.tokens.json` through existing semantic frontend CSS
   variables. Do not add literal token values, change canonical tokens, ship the
   candidate artwork/font, or build a second workspace theme.

Expected touched files are limited to:

- `apps/frontend/src/app/access-shell.tsx`
- `apps/frontend/src/app/career-workspace.tsx` only if required to expose the
  existing landmark/anchor without changing its data behavior
- `apps/frontend/src/app/globals.css`
- one new `apps/frontend/src/app/workspace-shell.tsx` component
- focused tests under `apps/frontend/src/app/__tests__/`
- this task's execution evidence and required state/doc updates

Do not edit vacancy data behavior, APIs, schemas, authentication semantics,
`/diagnostics` behavior, dependencies, or unrelated screens. If the existing
landmark mapping proves insufficient, stop and revise this bounded task before
expanding the route model.

## Explicitly out of scope

- Backend/API/database/migration/dependency/lockfile/deployment changes.
- New Today dashboard, Employers directory, Settings page, or fabricated content.
- Opportunity persistence/model changes, vacancy list redesign, or workspace
  pane migration (later B03+ work).
- Career Facts creation/confirmation, draft approval, application submission,
  employer communication, AI/provider calls, or candidate data transmission.
- Active visual-reference replacement, token-value changes, or accepted ADR edits.
- Production font/artwork acquisition or visual-regression dependency installation.

## Acceptance criteria

- [x] Signed-in Career and admin Diagnostics surfaces share the global shell;
      public login and invitation registration remain unchanged and usable.
- [x] All five canonical directions are present as labeled controls. Career and
      Opportunities reach only the existing surfaces; Today, Employers and
      Settings disclose that their production capability is unavailable.
- [x] `aria-current`/equivalent identifies the active available destination;
      navigation uses semantic keyboard-operable controls, visible focus, and
      does not rely on icons or hover.
- [x] Direct navigation, refresh, Back and Forward work for `/`,
      `/#vacancy-title`, `/register#token` and `/diagnostics` without losing or
      exposing the invitation token, removing a route, or bypassing admin access.
- [x] Responsive navigation remains readable and usable at 320, 390, 768,
      1024 and 1440 CSS px. Any compact menu has a name, keyboard operation,
      visible focus and Escape/close behavior with focus return.
- [x] Existing login, registration, logout and Diagnostics tests remain green;
      focused regressions cover destination availability, current location,
      keyboard behavior and diagnostics access.
- [x] Frontend ESLint, TypeScript, Vitest and production build pass. Record actual
      command results; no claim of browser/AT/zoom proof without running them.
- [x] No API calls are added for unavailable destinations; network behavior
      remains same-origin and existing auth endpoints/methods are unchanged.
- [x] No production/backend/API/database/dependency/token/ADR scope expansion;
      `git diff --check` passes and docs/state reflect actual validation.

## Dependencies and rollback

Dependencies: B01 canonical reference review completed and recorded; this task
separately authorized by the project owner; existing frontend stack and current
auth/vacancy/diagnostics routes described above. No dependency installation is
expected.

Rollback: revert only the B02 shell component, its mount/style changes, focused
tests and this task's state/evidence updates. Keep existing routes and handlers;
no data migration or backend rollback is involved.

## STOP

After the shell/navigation outcome and its acceptance checks, update STATUS and
BLOCKERS, advance NEXT only under the then-current explicit authority, and stop.
Preparation of this specification does not authorize implementation.

## Current authority — 2026-10-11

B01 is ACCEPTED under ADR-0025. The owner explicitly authorized B02 after valid closure; that condition is met. Earlier prepared/blocked language records the preparation baseline. B01 manual/accessibility follow-up remains open and blocks production release, not B02 execution. B03 is not authorized.

## Execution outcome — 2026-10-11

**COMPLETED locally** after B01 governance closure. All B02 acceptance criteria
are met within automated/test and synthetic browser evidence scope. TypeScript,
ESLint (exit 0, two disclosed native navigation warnings), Vitest 94/94 and production
build PASS. Browser 135 checks/15 screenshots on 320/390/768/1024/1440; history,
refresh, deep links, keyboard/focus, public auth flows and admin/user separation checked.
No live authenticated Laravel or human AT/zoom assessment is claimed.
Evidence: `docs/07-Design/validation/b02-2026-10-11/README.md`.
B01 mandatory manual/accessibility release gate remains OPEN. B03 not authorized.
STOP; no commit/push/PR.


## Visual remediation — current owner override, 2026-10-11

Owner rejected the previous production visual composition and explicitly authorized
bounded IMPLEMENT remediation on the existing B02 branch, overriding NEXT for this
block. Historical functional completion above does not imply visual acceptance.
Canonical `docs/07-Design/reference/` remains unchanged and authoritative; B01
lifecycle is unchanged.

Local remediation replaces the giant top navigation/header with the canonical
sidebar, compact brand/context/profile, responsive disclosure and authenticated
account dialog. Career/access/ingestion primary copy and safe API errors are Russian;
Career Facts/API/auth/role/route behavior is preserved. No new Application Workspace,
backend, dependency or font asset was introduced.

Evidence: [remediation audit and screenshots](../../docs/07-Design/validation/b02-remediation-2026-10-11/README.md).
Visual result awaits owner review. The mandatory release gate remains OPEN;
NEXT remains `b01-manual-accessibility-release-gate`. Application Workspace is
separate planned roadmap B03 (Opportunity list), B04 (Workspace shell), B05+
(context/review); none is authorized or started. STOP; no commit/push/PR/release.

## Owner visual acceptance and Git delivery authorization — 2026-10-11

The owner accepts the «Орбита решения» visual direction and consolidated React
implementation at the scope in the application-port evidence. This supersedes the
earlier pending visual review for this bounded delivery; it does not claim review of
every state or viewport. Native 200% zoom, manual screen-reader checks, real
authenticated Laravel E2E and the ADR-0025 release gate remain open/not tested.
Git consolidation, feature-branch push and a PR to `stage` are separately authorized;
merge, release, B03 and employer/provider actions are not.
