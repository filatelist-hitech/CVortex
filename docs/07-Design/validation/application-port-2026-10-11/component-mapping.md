---
title: Canonical B01 React component mapping
status: validated-local
created: 2026-10-11
---

# Canonical B01 → React mapping

Source: `docs/07-Design/reference/{index.html,style.css,app.js,motion.js,tokens.css}`.
Reference is read-only. This is the existing interrupted implementation, resumed
on `feature/b02-global-shell-navigation` with explicit IMPLEMENT override of NEXT.

| Canonical surface | React implementation | Data/behavior boundary |
| --- | --- | --- |
| Global sidebar / mobile disclosure | `application/app-shell.tsx` GlobalSidebar | Existing Career route; unavailable destinations stay labelled. |
| Header / account menu | `application/app-shell.tsx` WorkspaceHeader / AccountMenu | Real identity, native modal, real logout, admin-only Diagnostics; no role switch. |
| Vacancy list / selection / search | `application/vacancy-list.tsx` | Owner-scoped existing vacancy API; selection/version fence in provider. |
| Vacancy details / salary | `application/vacancy-header.tsx` | Existing live fields; missing salary stays unknown. |
| Five workspace sections / tabs | `application/panels.tsx` | Overview, requirements, materials, employer, history; native links/select; history state. |
| Recommendation / exact approval | `application/recommendation.tsx` | Demo acceptance and exact approval separate; live SavedRecommendationCard uses existing ApplicationDraftPanel PATCH/approve POST. |
| Evidence inspector | `application/evidence-panel.tsx` | Existing analysis evidence; draft claim chain retained in SavedRecommendationCard. |
| Workspace controller | `application/use-workspace.tsx`, `model.ts` | React state, stale request fences, unsaved-edit guard, Back/Forward/deep links. |
| Icons / font / brand assets | `application/icons.tsx`, `public/application-assets/` | Exact canonical sprite and local licensed files. |
| CSS / tokens | `application/application.css`, `tokens.css` | Canonical CSS rules scoped to ApplicationWorkspace; token aliases unchanged. |
| Spring controller | `application/motion.ts` | Exact critically damped equation/0.30 response from canonical motion.js; visual properties only, visibility owned by React/native dialog. |
| Existing Career / access / Diagnostics | `/career`, AccessShell, WorkspaceShell | Existing API/authentication/invitation/logout preserved; synthetic-only regressions. |

Design plan: preserve canonical palette, type roles, spacing, breakpoints and
composition verbatim. No aesthetic axis is open for redesign. `frontend-design`
is applied through reference fidelity, restraint and screenshot critique.

Design references: the installed `apple-design` and `frontend-design` skills.
Both installed files were read in this session.

Applied: immediate canonical pressed/focus feedback; spatially consistent panel
entry; native dialog focus containment/return and Escape; no delayed approval
commit; canonical spring velocity continuity when closing is interrupted;
reduced-motion preference changes settle active animation; animation/unmount
cleanup; inert background during modal drawers; truthful separate approval.

Rejected recommendations: new fonts/palette, translucent chrome/Liquid Glass,
new gestures/haptics, extra bounce or decoration. Canonical values and native
interaction paths win. No dependency or state-management library was added.

Resume fixes: canonical motion was missing from React; evidence context was lost
by URL parsing on Forward/refresh; legacy chat/preparation sibling keys collided.
Account Diagnostics navigation now shares the unsaved-edit guard.

Mobile list returns focus to the selected visible vacancy; mobile navigation closes
on workspace location changes, matching canonical render behavior.
