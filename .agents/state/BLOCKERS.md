# Blockers

Design reconciliation has no remaining blocking decision. ADR-0024 resolves the mandatory Figma source-of-truth conflict; Figma is optional. The next bounded B01 reference-foundation task can proceed without live AI, Preview acceptance or M2 implementation. Human visual/state review is B01 acceptance and must be recorded before that task is PASS. The existing blockers below retain their Preview/provider/environment scope; none is marked resolved by the design decision.

- Live vacancy analysis after the authorized Horizon egress repair reaches OpenAI but all three normal attempts return category `RATE_LIMITED` (HTTP 429); final status `FAILED` / `PROVIDER_ERROR`, no analysis/matching result. Configured project billing/quota/rate-limit cause remains unverified; stored metadata cannot distinguish these causes. Operator verification is required before another authorized retry. Preview/M2 authority is unchanged.

- Preview 0.1 has no recorded real-user end-to-end validation or observed feedback in the repository. The mandatory M1.4 acceptance criteria remain unverified, so Preview 0.1 is not PASS and the M2 planning task is not ready to start until that evidence is recorded.

- The known Compose frontend build with `NODE_ENV=development` fails while prerendering `/_global-error`; `NODE_ENV=production` passes. It is tracked by `frontend-build-environment-follow-up` as a separate environment issue and does not block production-mode Preview validation.
