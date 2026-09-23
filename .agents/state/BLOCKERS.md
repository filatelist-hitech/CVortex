# Blockers

- PR #27 merge is blocked by four new actionable review threads on the pushed `daca403` head (two P1, two P2); remediation and a fresh gate are in progress.
- M1.4 remains blocked until PR #27 is actually merged into `stage`.
- The pre-existing Compose frontend build with `NODE_ENV=development` fails while prerendering `/_global-error`; `NODE_ENV=production` passes. It is tracked by `frontend-build-environment-follow-up` and does not block PR #27 merge readiness.
