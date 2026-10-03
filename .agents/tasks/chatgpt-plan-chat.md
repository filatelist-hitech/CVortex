# ChatGPT plan chat — bounded implementation packet

execution.workflow: IMPLEMENT

## Authority and scope

User request on 2026-10-04: chat inside CVortex using owned vacancy/career context and saved results, without relying on API-project credits. Proposed architecture: [ADR-0023](../../docs/03-ADR/ADR-0023-chatgpt-plan-chat.md). This packet does not assert implementation PASS or accepted ADR status. Preview/M2 gates remain unchanged.

## First bounded block: connection capability proof

- Review/accept the architecture proposal through the repository decision workflow.
- Inspect local deployment and licensing/eligibility against official plan-usage documentation.
- Add a disabled-by-default, owner-bound outbound ChatGPT connection with documented OAuth registration, state/nonce/S256, validated OIDC identity, granted-scope checks, protected credential storage, refresh/disconnect.
- Provide first-party Continue with ChatGPT UX without replacing CVortex login or reusing inbound MCP authorization.
- Fetch current account model choices and complete one explicitly user-triggered streamed text request using plan permission, not OPENAI_API_KEY.
- Record live success, denial, limits or unsupported account as actual evidence. Human must perform authentication/consent; do not request tokens in chat.
- STOP on unavailable eligibility/permission; do not build a misleading working chat shell.

## Subsequent block after live capability proof

- Owned conversation/message persistence and vacancy-scoped UI; safe Markdown rendering and bounded history.
- Source context from current owned snapshot plus confirmed facts even when API-based vacancy analysis failed.
- Deliberate transmission, streaming/cancel/incomplete states, reconnect/errors and no silent billed fallback.
- Save analysis as a validated proposal: source-backed requirements, deterministic matching, provenance, snapshot fencing, idempotency and human review.
- Inspect existing Truth Guard provider dependencies before promising a fully credit-independent draft approval path.

## Required validation

OAuth bad state/nonce/audience/signature/expiry/scope and cross-user denial; token rotation/revocation; no secret logging; live permission and inference completion. Then source/context ownership, injection/output validation, stale snapshot/career signature rejection, duplicate writes, incomplete stream persistence, SSR/hydration and browser chat/save checks. Run relevant tests/lint and PostgreSQL RLS/concurrency harnesses required by touched domains. Mocked inference does not replace live account proof.

## Exclusions

No MCP write tool, generic mutation, career fact auto-confirmation, application submission, private ChatGPT endpoint, credential scraping, public tunnel/proxy, key change, account purchase, automatic phase transition or publication.
