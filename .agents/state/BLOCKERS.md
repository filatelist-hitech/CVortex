# Blockers

- PR #27 merge readiness is suspended for three fresh review findings on `70d4b09` (one P1, two P2); reproduction and remediation are in progress.
- M1.4 remains blocked until PR #27 is actually merged into `stage`.
- The pre-existing Compose frontend build with `NODE_ENV=development` fails while prerendering `/_global-error`; `NODE_ENV=production` passes. It is tracked by `frontend-build-environment-follow-up` and does not block PR #27 merge readiness.
