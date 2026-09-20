# Blockers

- M1.4 remains blocked pending PR #27 independent re-review and merge; do not start it before the review returns `PASS`.
- The pre-existing Compose frontend build with `NODE_ENV=development` fails while prerendering `/_global-error`; `NODE_ENV=production` passes. It is tracked by `frontend-build-environment-follow-up` and does not change the M1.3R remediation verdict.
