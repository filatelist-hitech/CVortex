# Blockers

- PR #27 code remediation is complete on `a85512b`; all nine actionable review threads are replied to and resolved, required checks pass, and GitHub reports `CLEAN`. Merge-ready authorization remains contingent on the separate state commit passing its own mandatory checks and fresh review/merge-state gate.
- M1.4 remains blocked until PR #27 is actually merged into `stage`.
- The pre-existing Compose frontend build with `NODE_ENV=development` fails while prerendering `/_global-error`; `NODE_ENV=production` passes. It is tracked by `frontend-build-environment-follow-up` and does not block PR #27 merge readiness.
