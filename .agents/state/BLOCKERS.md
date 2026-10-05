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

The next live PR #42 head is `f985b06`; the prior two threads are resolved. One new unresolved P1 (`4178004380`) remains: approval must not promote a draft when the accepted vacancy source exceeds the 25,000-character chat context limit. This is fixed locally with regression; the user reported two new fixes, but live GitHub state exposed only this one current thread.

`PLAN-MODEL-01` and `PG-EVIDENCE-01` passed independent re-review. The current-head findings for common-word and numeric-only Career Fact selection, exact-context preview, approval/Career Fact serialization and context snapshot/signature consistency, plus stream cancellation and cancellation on chat unmount, were fixed and their five review threads were resolved. OAuth consent now discloses the controlled draft write, separately gated by `mcp:draft:write`, and setup instructions match the three-tool contract; those two threads were also replied to and resolved. Two additional findings—generic vacancy terms selecting unrelated facts and duplicate source excerpts inheriting one heading—were fixed in `79b3681`; regressions and targeted tests passed, and both threads were replied to and resolved. All required checks passed on `79b3681`, including backend/frontend CI tests and the production build. `CHAT-ARCH-01` and `CHAT-CTX-01` remain non-blocking follow-ups. No merge was performed.

Live PR #42 head is now `427678f`; two fresh unresolved threads are pending remote remediation: Russian generic vacancy vocabulary can select an unrelated confirmed fact, and the OpenAPI contract omits the context-preview endpoint/fingerprint required by chat send. Both are fixed locally with targeted regression and contract validation; push, fresh exact-head CI/review and thread resolution remain pending explicit push authorization.

### PR #42 latest two-comment remediation — 2026-10-04

Live intake on head `8a79258` found two current unresolved P2 threads: OAuth login under-disclosed the separate draft-write capability, and the OpenAPI contract omitted `POST /vacancies/{id}/chat/cancel`. Both are fixed locally and passed targeted regression, full lint, OpenAPI contract parsing and diff checks. The remaining delivery steps are the authorized push, fresh exact-head remote checks, and replies/resolution for only these two fixed threads.

### PR #42 latest Codex reviewer remediation — 2026-10-05

Live intake on head `d063352` found one new unresolved P2: the deprecation endpoint returned the original pre-lock Career Fact instance after persisting the transition. The transaction now returns its locked instance and a response regression passes locally. Remaining delivery steps are push, fresh exact-head checks, and reply/resolution for this fixed thread only.

### PR #42 latest reviewer remediation — 2026-10-05

Live intake on head `6ab01a6` found two still-open old fixed threads (`login` disclosure and chat cancel OpenAPI) and two new current findings: generic single-token Career Fact overlap and stale read-only MCP threat-model prose. The old fixes remain present; the new fixes pass local targeted tests and full lint. Reply/resolution for all four threads and the new exact-head remote checks remain delivery steps.

### PR #42 single-letter technology remediation — 2026-10-05

Live intake on head `f4e2f92` found one new unresolved P2: the tokenizer dropped single-letter `C` before the explicit technology allowlist could preserve it. The tokenization and regression are fixed locally; push, exact-head checks, and reply/resolution remain pending.

### PR #42 punctuation-normalization remediation — 2026-10-05

After the single-letter fix, live review found one follow-up unresolved P1: trailing punctuation was stripped after generic-term filtering, allowing sentence-final generic tokens through. The order and regression are fixed locally; commit, push, exact-head checks, and reply/resolution remain pending.

### PR #42 context-token and MCP-signature remediation — 2026-10-05

Live intake on head `76ec21f` found two unresolved threads: duplicate normalized lexical tokens could select an unrelated private fact, and MCP draft saves were not bound to the read-time Career signature. Both are fixed locally with regressions and pass isolated SQLite tests plus lint/static checks. The persistent local PostgreSQL test harness remains unavailable to RefreshDatabase under its current non-owner runtime role; CI/remote checks and thread delivery remain pending.
