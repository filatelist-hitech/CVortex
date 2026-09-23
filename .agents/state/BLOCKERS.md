# Blockers

- M1.4 remains blocked until PR #27 is actually merged into `stage`; the PR review gate has passed, but merge has not occurred.
- The pre-existing Compose frontend build with `NODE_ENV=development` fails while prerendering `/_global-error`; `NODE_ENV=production` passes. It is tracked by `frontend-build-environment-follow-up` and does not block PR #27 merge readiness.
