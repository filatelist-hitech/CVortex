# Blockers

- PR #27 merge readiness is suspended for two fresh P1 review findings on `e1cd473`: contracted passive negation is missed, and current-location parsing retains ongoing temporal qualifiers. Reproduction and remediation are in progress.
- M1.4 remains blocked until PR #27 is actually merged into `stage`.
- The pre-existing Compose frontend build with `NODE_ENV=development` fails while prerendering `/_global-error`; `NODE_ENV=production` passes. It is tracked by `frontend-build-environment-follow-up` and does not block PR #27 merge readiness.
