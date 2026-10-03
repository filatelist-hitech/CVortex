# Blockers

- Preview 0.1 has no recorded real-user end-to-end validation or observed feedback in the repository. The mandatory M1.4 acceptance criteria remain unverified, so Preview 0.1 is not PASS and the M2 planning task is not ready to start until that evidence is recorded.
- The current local Compose `.env` selects a PostgreSQL database that is absent from the existing named volume. `/api/v1/health/ready` returns 503 and `make migrate` fails during runtime-role provisioning, before Laravel migrations. Identify the intended database/volume and reconcile the local configuration before Preview validation; do not remove volumes or guess which existing database contains the intended data.
- The known Compose frontend build with `NODE_ENV=development` fails while prerendering `/_global-error`; `NODE_ENV=production` passes. It is tracked by `frontend-build-environment-follow-up` as a separate environment issue and does not block production-mode Preview validation.
