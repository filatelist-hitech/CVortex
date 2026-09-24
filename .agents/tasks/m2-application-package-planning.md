# M2 — Define bounded application-package implementation from Preview 0.1 findings

Status: ready when Preview 0.1 feedback is available. This task is planning only.

## Objective

Use the validated Preview 0.1 outcome and observed user/product gaps to define
exactly one bounded M2 implementation task for an application package that
builds on the approved, truthful M1.4 draft.

## Scope

- Review Preview 0.1 evidence and feedback, the M1.4 contract and implementation,
  the active roadmap, and relevant legacy Phase 12 material as reference only.
- Specify the package's user-visible outcome, artifact/content contract, and
  deterministic readiness rules only where Preview evidence supports them.
- Define provenance and dependency revalidation requirements, API/frontend/data
  boundaries if needed, security/authorization constraints, tests, documentation,
  and measurable acceptance criteria for one implementation slice.
- Route any durable architectural or unresolved business decision through the
  repository's decision workflow; do not fabricate user research or silently
  override accepted ADRs.

## Explicitly out of scope

- Implementing the package, generating or exporting DOCX/PDF, or changing code,
  schemas, dependencies, or runtime behavior.
- Employer Memory, a general ConsistencyCheck feature, or marking an
  application as applied.
- Any automatic or manual employer submission capability.
- Defining all of M2–M6 or producing a multi-milestone implementation plan.

## Acceptance criteria

- The task spec cites the actual Preview 0.1 findings that justify its scope.
- It defines one observable outcome and a bounded implementation boundary.
- Truth-first provenance, confirmed-fact/Claim eligibility, owner isolation,
  untrusted-input handling, stale-dependency behavior, and human approval are
  addressed where applicable.
- Required validation, including real PostgreSQL evidence if persisted
  owner-scoped data is introduced, is explicit.
- Any necessary architecture/business decisions are resolved through the
  accepted repository workflow; no unsupported requirement is invented.
- `.agents/state/NEXT.md` points only to this planning task until it is complete.
- No implementation work is performed by this task.
