# Blockers

- M1.4 is blocked until `m1-3r-vacancy-core-remediation-review` returns an independent `PASS` verdict.
- The pre-existing Compose frontend build with `NODE_ENV=development` fails while prerendering `/_global-error`; `NODE_ENV=production` passes. It is tracked by `frontend-build-environment-follow-up` and does not change the M1.3R remediation verdict.
