# Blockers

- Preview 0.1 has no recorded real-user end-to-end validation or observed feedback in the repository. The mandatory M1.4 acceptance criteria remain unverified, so Preview 0.1 is not PASS and the M2 planning task is not ready to start until that evidence is recorded.
- The known Compose frontend build with `NODE_ENV=development` fails while prerendering `/_global-error`; `NODE_ENV=production` passes. It is tracked by `frontend-build-environment-follow-up` as a separate environment issue and does not block production-mode Preview validation.
