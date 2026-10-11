---
title: Exact canonical B01 React port — final local validation
status: owner-accepted
created: 2026-10-11
---

# Exact B01 React port: local technical PASS

Interrupted work was resumed from the local checkpoint at `89f30e3`; the consolidated delivery is recorded on `feature/b02-global-shell-navigation`.
Native IMPLEMENT preflight with `--user-override` passed; base/target `stage`.
Explicit owner authorization overrides NEXT only for this bounded port. The owner
accepted the «Орбита решения» visual direction and its React implementation on
2026-10-11. This records acceptance of the correspondence and visual language, not
inspection of every automated state or viewport. NEXT remains
`b01-manual-accessibility-release-gate`.

## Owner visual acceptance — 2026-10-11

The owner accepts the React port against canonical `docs/07-Design/reference/` for
visual direction, canonical correspondence, desktop/tablet/mobile layout, navigation,
typography, Application Workspace composition and component visual language.
The acceptance does not claim a personal review of every state or viewport and does
not convert automation into manual validation. Manual screen-reader verification,
full native 200% zoom assessment, real authenticated Laravel E2E and the production
release accessibility gate remain open/not tested as applicable. ADR-0025 and the
mandatory release gate remain in force.

This Git delivery consolidates the accepted design and implementation. It does not
authorize production release, B03, Career Fact confirmation, application submission,
employer communication, or provider-backed actions.

## Visual parity

[Machine summary](comparison-summary.json): **97 state/viewport cases + 142 scroll
pairs = 239 comparisons / 717 raw canonical, React and difference PNGs**. Each
scroll tile is independently gated, including below-the-fold edit/approval UI.
Geometry deviations beyond 1 CSS px: **0**. Computed-style differences: **0** for
measured font family/size/weight/leading/tracking, colors, padding, gap and radius.
[Derivation](derivation-results.json): **260 checks**, 252 canonical CSS rules,
exact token CSS, icon sprite and six licensed/local assets.

Pinned Chromium **145.0.7632.6**, macOS arm64, ru-RU, Europe/Moscow, DPR 1,
100% zoom, reduced motion; identical synthetic data, selection/section/scenario.
Fixed thresholds retained: per-channel 16/255, core ≤0.5%, whole capture ≤2%,
geometry ≤1 CSS px. No golden updates or screenshot masks.

| Viewport | States | Worst core, including scroll | Worst whole capture, including scroll | Result |
| --- | ---: | ---: | ---: | --- |
| 1440 × 900 | 24 | 0.0186% | 0.0416% | PASS |
| 768 × 1024 | 25 | 0.0061% | 0.0125% | PASS |
| 390 × 844 | 24 | 0.1330% | 0.1057% | PASS |
| 320 × 640 | 24 | 0.2422% | 0.1699% | PASS |

Account exception is local, never an entire state: real identity/logout/role-gated
Diagnostics replace demo profile/role switching. Every raw pixel and raw ratio is
retained; `exclusion` records the union of both content rectangles plus 56 CSS px
for canonical shadow (16px offset + 40px blur). The rest of each image retains the
same gates. Shared dialog x/width/top anchor (desktop/tablet), bottom anchor (mobile)
and computed typography/material are checked; height varies with actual content.
Raw account ratios reach 23.13% because its content differs; comparable outside
region ratios are independently reported. No identical account-content claim.
Small unavailable-navigation wording differences remain within the whole-page gate.

Capture corrections: full-page rasterization synthesizes a taller viewport and
triggers canonical resize behavior, closing mobile menus/drawers. Final captures
use the actual viewport plus scroll tiles, without stitching or hidden content.
Pointer is neutral on both pages; queued focus settles before resetting scroll.
Earlier in-session failures were diagnostic runs, not accepted baselines. Historical
`career-failure.json`/`workspace-failure.json` remain FAIL records; current PASS files
are separate. `probe.json` is historical and is not final evidence.

## Functional validation

| Check actually executed | Result / evidence |
| --- | --- |
| Docker frontend `npm run typecheck` | PASS; fresh consolidated result in [validation report](delivery-validation.json) |
| Docker frontend `npm run lint` | PASS; fresh consolidated result in [validation report](delivery-validation.json) |
| Docker frontend `npm test` | **108/108**, 9 files; fresh consolidated result in [validation report](delivery-validation.json) |
| `NODE_ENV=production npm run build` in an isolated container copy | PASS; routes /, /career, /diagnostics, /register; [validation report](delivery-validation.json) |
| `node compare.cjs` | **97 cases + 142 scroll pairs PASS**, [comparison summary](comparison-summary.json) |
| `node validate-workspace.cjs` | **117 browser checks**, [report](workspace-results.json) |
| `node validate-career.cjs` with installed runtime paths | **41 browser checks**, six captures, [report](career-results.json) |
| `node validate-motion.cjs` | **50 browser checks**, canonical and React normal/reduced motion, [report](motion-results.json) |
| `node apps/frontend/scripts/check-application-reference.cjs` | **260 derivation checks PASS** |
| `node docs/07-Design/reference/build-reference.cjs --write`, then `--check`; `node --test .../build-reference.test.cjs` | PASS; 2/2 Node tests; canonical token values unchanged |
| `python3 check-integrity.py` | Historical PASS against the resumed dirty-session baseline; see [report](integrity-results.json). It predates this Git consolidation. |
| Live unauthenticated HTTP | Four pages 200; `/api/v1/me` 401; [validation report](delivery-validation.json) |
| Node syntax / `git diff --check` | PASS; [validation report](delivery-validation.json) |

Browser APIs are intercepted with ephemeral synthetic responses. No authenticated
Laravel session, real provider request, persisted Career Fact or backend write is
claimed. Browser exceptions/external requests: zero; expected HTTP 401 diagnostics
are retained in the workspace report. Production build ran in an isolated container
copy with copied dependencies and Vitest setup/config, keeping the running dev
server and its .next directory intact. Initial isolated-copy attempts failed on an
out-of-root dependency symlink and missing matcher declarations; final exact config
build passed after copying the complete inputs, without changing app configuration.

## Apple Design interaction audit

Both installed skills were read: `apple-design` and `frontend-design`.
[Component mapping, applied principles and rejected redesign suggestions](component-mapping.md).
Canonical critical-damping response 0.30s, velocity-preserving interruption and
reduced-motion settle are ported; React retains logical visibility/state ownership.

| Required interaction | Current evidence |
| --- | --- |
| Account opening/closing | Native modal, exact motion, real role, Escape, pointer interruption, focus return |
| Vacancy list opening/closing | Tablet modal and focus trap; mobile returns to selected vacancy |
| Vacancy switching | Cedar conflict blocks; previous approval/pinned evidence resets |
| Workspace tabs | Five sections, selected state, URL and unsaved live-edit guard |
| Evidence opening | Scoped evidence, focus, modal inert background where applicable |
| Evidence closing | Escape, forward/backward focus trap, trigger focus return |
| Accept recommendation | Acceptance remains separate from exact approval |
| Reject recommendation | Rejected text cannot be accepted or approved |
| Edit recommendation | Supported edit preserves exact wording; unsupported text blocked |
| Approve exact text | Explicit existing POST; never submission/employer communication |
| Keyboard | Modal/list/evidence traps, native controls, navigation Escape |
| Focus return | Account, list, evidence; mobile selected-vacancy target |
| Escape | Panels close predictably; native dialog exit remains interruptible |
| Reduced motion | Static/immediate equivalent; live preference change settles animation |
| Browser Back/Forward | Section and evidence level; evidence deep-link refresh restored |

Career regression covers manual entry, extraction, pending confirmation/edit/reject,
untrusted evidence rendered as text, safe error copy, logout failure/retry and
admin/user access. Login/register/logout and invitation-fragment removal remain
covered. Existing vacancy import/analysis/chat are retained in the Overview
expander; no live AI action was exercised.

## Representative visual evidence

| Viewport | Canonical | React | Difference |
| --- | --- | --- | --- |
| 1440 desktop | [PNG](1440-normal-canonical.png) | [PNG](1440-normal-production.png) | [PNG](1440-normal-difference.png) |
| 390 mobile | [PNG](390-normal-canonical.png) | [PNG](390-normal-production.png) | [PNG](390-normal-difference.png) |

The full 717-image capture corpus remains in the local validation directory and is
not part of this PR. The committed machine summary preserves comparison counts,
thresholds and per-viewport results; additional states and scroll pairs can be
regenerated with the documented validators.

All eight scenarios, five sections, Cedar conflict, accepted/approved/rejected/edit,
evidence (normal/pending/Cedar), list/workspace, missing salary and mobile navigation
are covered by the machine results. Contact sheets and full-size state captures are
local inspection aids, not pixel-gate inputs.

## Changed files and remaining limits

Resume source changes: `application/{app-shell.tsx,use-workspace.tsx,model.ts}`;
new `application/motion.ts` and `__tests__/application-motion.test.ts`;
`vacancy-workspace.tsx` (distinct chat/preparation keys);
`scripts/check-application-reference.cjs` (native dynamic imports for lint).
Task specification, STATUS/BLOCKERS and this evidence directory are updated.
Pre-existing interrupted React components, auth/Career integration and dirty work
were preserved. No canonical/backend/token/ADR source changes in this session.
The integrity report lists all changed/new evidence paths against the start snapshot.

Owner acceptance applies to the visual direction and React correspondence, not each
state/viewport. Native 200% zoom and human screen-reader assessment are **NOT TESTED** here;
mandatory release gate stays OPEN. Automated keyboard/DOM checks and agent image
inspection do not close it. Other operating systems/browsers/fonts are unverified.
Live content uses existing contracts; absent salary/employer history/export support
stays explicitly unknown/unavailable. Exact synthetic parity does not establish
pixel identity for arbitrary real data or live authenticated backend correctness.

## Preview and replay

Real local CVortex implementation: [http://127.0.0.1:8080/](http://127.0.0.1:8080/).
It currently uses the existing **Next dev** Compose service; production-mode build
is verified separately. No production deployment or release readiness is claimed.
Canonical: [http://127.0.0.1:8768/](http://127.0.0.1:8768/).

Demo: [authenticated local B01 fixture](http://127.0.0.1:8080/?demo=b01&opportunity=northstar&level=work#materials).
Requires a real authenticated session, loopback host and explicit
`NEXT_PUBLIC_CVORTEX_WORKSPACE_DEMO=1` flag, currently set in the running local
frontend. It never grants authentication or demo privileges. Production URL without
flag/loopback ignores fixture mode; no synthetic persistence.

Canonical startup: `node docs/07-Design/reference/preview-server.cjs`.
Existing app startup: `docker compose up -d frontend nginx` (default app mode).
Fixture mode is an explicit local environment option; it is not added to compose.yaml.
For a standalone frontend dev process: `NEXT_PUBLIC_CVORTEX_WORKSPACE_DEMO=1 npm run dev`.
Run validators from repository root. Browser checks resolve Playwright through the
frontend package when available; otherwise set `CVORTEX_PLAYWRIGHT_MODULE` to the
installed module and optionally set `CVORTEX_CHROMIUM_PATH`. Pixel comparison can
use `CVORTEX_NODE_MODULES` to resolve Playwright and pngjs. No machine-specific path
is required. Python contact-sheet helper uses installed Pillow.

Exact next task: `b01-manual-accessibility-release-gate`. B03, Preview/M2 and release
remain out of scope. This authorized Git consolidation does not close the manual gate.
