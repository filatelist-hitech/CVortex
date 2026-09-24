# Blockers

- PR #31 is not merge-ready while the independent remediation is uncommitted
  and unpushed. Recheck mandatory CI and fresh paginated GitHub review state on
  the pushed head before considering merge.
- The pre-existing Compose frontend build with `NODE_ENV=development` fails while prerendering `/_global-error`; `NODE_ENV=production` passes. It is tracked by `frontend-build-environment-follow-up` and does not block M1.4 production validation or PR #31 readiness.
