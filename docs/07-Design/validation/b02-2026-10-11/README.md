---
title: B02 implementation evidence
status: completed-local
owner: project
created: 2026-10-11
updated: 2026-10-11
tags: [frontend, navigation, validation]
---

# B02 — completed locally

B01 is ACCEPTED under [ADR-0025](../../../03-ADR/ADR-0025-b01-reference-acceptance-release-gate.md).
B02 adds one shared authenticated shell around existing Career and admin Diagnostics.
Five text-labeled destinations: Career `/`, Opportunities `/#vacancy-title`, and
Today/Employers/Settings disabled with “Not available yet”. No new domain capability.
Account identity, admin utility and sign-out are separate. Skip link, current-location
semantics, visible focus and wrapping desktop/mobile navigation use existing CSS roles.
No compact modal/menu, trap, font/artwork, token values or dependencies were added.

Production files changed:
- `apps/frontend/src/app/workspace-shell.tsx` — new shared shell/navigation.
- `apps/frontend/src/app/access-shell.tsx` — shared mount after existing auth/role gate.
- `apps/frontend/src/app/career-workspace.tsx` — account utilities moved to shell,
  focusable main landmark added; data behavior unchanged.
- `apps/frontend/src/app/globals.css` — shell styles from existing semantic roles.
- `apps/frontend/src/app/__tests__/workspace-shell.test.tsx` — 9 focused regressions.

[Validation summary](validation-summary.json), [browser results](browser-results.json),
[live unauthenticated HTTP smoke](live-unauthenticated-http.json).
Frontend: TypeScript PASS, ESLint exit 0 (2 native navigation warnings), Vitest 94/94,
production build PASS with `NODE_ENV=production`. Existing vacancy fixture duplicate-key
warnings are recorded; no vacancy behavior was changed. B01 derivation 2/2 and check PASS.
135 rendered browser checks / 15 screenshots at 320/390/768/1024/1440 CSS px.
Browser exceptions, unexpected console/hydration errors and external requests: zero.
Screenshots were inspected by the agent for shell composition, wrapping and selected
states; they are not human accessibility acceptance or a new golden baseline.

Native navigation is deliberate: Next Link's client fragment transition did not notify
the hash store; `onNavigate` uses native navigation for Career/Opportunities, retaining
normal modifier/new-tab behavior and browser history. The ESLint warnings are retained
and disclosed, not disabled. Career may reload the document rather than transition
client-side. Invitation fragment consumption and auth contracts remain unchanged.

[Initial harness failure](browser-failure.json) is retained: injecting a new invitation
fragment into an already mounted `/register` form did not re-run existing one-time
fragment consumption. The corrected harness enters a fresh document, verifies fragment
removal, refresh with no recovered token, then registration. No auth code change.
Python Playwright/browser bundle executables were unavailable; existing Node Playwright
and installed Chrome were used, with no installation.

Signed-in browser checks use synthetic API fixtures only, never persisted candidate
records or live login credentials. Authentication integration/authorization also have
existing Vitest coverage. Live read-only smoke confirms Nginx/Next/Laravel reachability
and unauthenticated API denial. A real authenticated Laravel end-to-end session is
not claimed. Manual screen-reader and full human keyboard/zoom/state matrix remain
NOT TESTED in the mandatory B01 release gate; production release remains blocked.

Screenshots (файлы оптимизированы в PR #51): `1440-career.png` (desktop), `390-career.png` (mobile),
`320-career.png` (320px), `1440-admin-diagnostics.png` (admin),
`390-diagnostics-denied.png` (denied), `390-public-register.png` (registration).
No private preview content is present.

Start the existing application from repository root: `make up`.
Local app: http://127.0.0.1:8080/ (existing Nginx API routing).
No second frontend or new runtime service is needed.

Reproduce browser evidence with existing runtime paths:
```sh
CVORTEX_PLAYWRIGHT_MODULE=/path/to/playwright \
CVORTEX_CHROMIUM_PATH=/path/to/installed/chrome \
node docs/07-Design/validation/b02-2026-10-11/validate-browser.cjs
```

Rollback only B02 production files/tests and its docs/state updates. No data migration.
This report records the earlier B02 functional shell block. The subsequent owner
accepted the canonical B01 React Application Workspace port documented in
`../application-port-2026-10-11/README.md`; it does not assert new domain capability
or completion of later roadmap tasks. NEXT remains
`b01-manual-accessibility-release-gate`; manual release evidence remains open.
