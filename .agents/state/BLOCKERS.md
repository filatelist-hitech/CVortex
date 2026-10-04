# Blockers

- Live vacancy analysis after the authorized Horizon egress repair reaches OpenAI but all three normal attempts return category `RATE_LIMITED` (HTTP 429); final status `FAILED` / `PROVIDER_ERROR`, no analysis/matching result. Configured project billing/quota/rate-limit cause remains unverified; stored metadata cannot distinguish these causes. Operator verification is required before another authorized retry. Preview/M2 authority is unchanged.

- Preview 0.1 has no recorded real-user end-to-end validation or observed feedback in the repository. The mandatory M1.4 acceptance criteria remain unverified, so Preview 0.1 is not PASS and the M2 planning task is not ready to start until that evidence is recorded.
- The known Compose frontend build with `NODE_ENV=development` fails while prerendering `/_global-error`; `NODE_ENV=production` passes. It is tracked by `frontend-build-environment-follow-up` as a separate environment issue and does not block production-mode Preview validation.

### ChatGPT plan bounded task — 2026-10-04

No remaining blocker for this local-account task: actual plan OAuth/inference and
embedded vacancy streaming/history/draft persistence were verified; external
three-tool discovery and a nonempty draft write were confirmed by user evidence.
An initial stale tunnel condition was resolved externally. API-key quota failure
above is independent and remains unresolved. Preview/M2 gates are unchanged.
Other-account/deployment eligibility and exact Figma parity were not verified.

### ChatGPT plan pre-merge review — 2026-10-04

`PLAN-MODEL-01` and `PG-EVIDENCE-01` passed independent re-review. The current-head findings for common-word and numeric-only Career Fact selection, exact-context preview, approval/Career Fact serialization and context snapshot/signature consistency, plus stream cancellation and cancellation on chat unmount, were fixed and their five review threads were resolved. OAuth consent now discloses the controlled draft write, separately gated by `mcp:draft:write`, and setup instructions match the three-tool contract; those two threads were also replied to and resolved. Two additional findings—generic vacancy terms selecting unrelated facts and duplicate source excerpts inheriting one heading—were fixed in `79b3681`; regressions and targeted tests passed, and both threads were replied to and resolved. All required checks passed on `79b3681`, including backend/frontend CI tests and the production build. `CHAT-ARCH-01` and `CHAT-CTX-01` remain non-blocking follow-ups. No merge was performed.
