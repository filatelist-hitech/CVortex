# Blockers

B01 **ACCEPTED** under owner-approved ADR-0025. On 2026-10-11 the owner also accepted the «Орбита решения» visual direction and its React implementation at the documented scope. This does not claim review of every state or viewport. B02 functional implementation and visual remediation are validated; NEXT remains the mandatory manual/accessibility release gate. B03 is not authorized.

Exact canonical B01 React port is implemented and technically/visually validated;
owner visual acceptance is recorded in `docs/07-Design/validation/application-port-2026-10-11/README.md`.
97 visual cases plus 142 scroll pairs and fresh frontend/browser suites do not close
the manual release gate. Live authenticated Laravel/AI validation is not claimed.

**Production release BLOCKED** by mandatory `b01-manual-accessibility-release-gate` (OPEN): full manual state/viewport and interactions, K1 keyboard/focus, Z1 native 200% zoom/reflow, A1 human screen-reader assessment. Unperformed checks remain **NOT TESTED**. Owner visual/product acceptance does not close this gate.

- Live vacancy analysis after the authorized Horizon egress repair reaches OpenAI but all three normal attempts return category `RATE_LIMITED` (HTTP 429); final status `FAILED` / `PROVIDER_ERROR`, no analysis/matching result. Configured project billing/quota/rate-limit cause remains unverified; stored metadata cannot distinguish these causes. Operator verification is required before another authorized retry. Preview/M2 authority is unchanged.

- Preview 0.1 has no recorded real-user end-to-end validation or observed feedback in the repository. The mandatory M1.4 acceptance criteria remain unverified, so Preview 0.1 is not PASS and the M2 planning task is not ready to start until that evidence is recorded.

- The known Compose frontend build with `NODE_ENV=development` fails while prerendering `/_global-error`; `NODE_ENV=production` passes. It is tracked by `frontend-build-environment-follow-up` as a separate environment issue and does not block production-mode Preview validation.
