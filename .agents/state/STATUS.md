# CVortex Project State

## Last Completed Foundation Phase

`07-design-foundation` — **completed / PASS**.

Completed foundation sequence:

- `00-ai-system-bootstrap`
- `01-project-knowledge-bootstrap`
- `02-research-plan`
- `03-technical-research`
- `04-product-integrations-hiring-research`
- `05-architecture-decision-freeze`
- `06-product-data-ai-security-design`
- `07-design-foundation`

## Roadmap reset

The former horizontal Phase 08–12 implementation order is superseded by the accepted value-driven roadmap in `docs/01-Product/Roadmap.md`.

Accepted architecture is unchanged. The reset changes sequencing/task size, not Laravel/PostgreSQL/Redis/API-first/Truth Guard/provider-independence/ownership/design/document-rendering decisions.

Old Phase 08–12 task specs are preserved under `.agents/tasks/legacy/` as requirement inventory and are not execution authority.

Hardened pre-reset references preserved exactly:

- Phase 08 final hardened spec from commit `6ca25b09550b8315913f06382dff28f8d326aa06`;
- Phase 09 final hardened spec from commit `9267f15545cca70a245798a58179742789cf2737`.

Their necessary M0/First-Value decisions were extracted into the new bounded specs.

## Active implementation roadmap

```text
M0 Runnable Core
→ M1.1 Access Core
→ M1.2 Career Core
→ M1.3 Vacancy Core
→ M1.4 Application Draft
→ Preview 0.1
→ M2 Real Application Package
→ M3 Imports & Integrations
→ M4 Employer Journey
→ M5 Outcomes & Analytics
→ M6 Distribution & Hardening
```

Only M0 and M1 slices have execution specs now. M2+ detail is intentionally created just-in-time after real product validation.

## Current repository state

- M0 Runnable Core is completed / PASS;
- a real Laravel 13 API and Next.js 16 technical shell run through loopback-bound Nginx;
- Docker Compose provides Nginx, frontend, backend/PHP-FPM, Horizon, PostgreSQL and Redis with only Nginx host-exposed;
- root Make/configuration workflows, health probes, request correlation, persistent PostgreSQL/private storage and disposable Redis are implemented;
- deterministic backend/frontend checks and the minimal GitHub Actions quality workflow are implemented;
- M1.2 adds the first product migration/domain slice for owner-scoped Career sources, facts, Claims, evidence and LLM-run metadata;
- M1.3 adds owner-scoped Vacancy snapshots, semantic requirements, seven-dimensional Career matching, explainable gaps and should-I-apply recommendations;
- runtime AI contains the versioned `career.fact-extraction@1.0.0` and `vacancy.requirement-extraction@1.0.0` Skills, prompts, schemas and synthetic adversarial eval fixtures;
- the M0 technical shell consumes a maintainable semantic subset of Phase 07 design tokens;
- Figma live file exists but canvas review remains limited by previously recorded MCP Starter-plan capability/rate limitation.

## M1.1 implementation outcome

M1.1 Access Core is completed / PASS and was squash-merged into `stage` as `c7c5d970624cfe731f62d55389f302603ec55c34` from PR #23. The implementation adds per-user auth-generation invalidation for in-flight session replay after disable/re-enable, forwards login limiter and Argon settings through Compose, preserves invitation fragments across React Strict Mode replay, equalizes unknown-account password work by invoking the active hasher with current parameters, supports staged password-driver migration with login rehashing, translates concurrent duplicate-email unique conflicts into validation errors, blocks forwarded destructive audit-builder operations including `forceDelete`, preserves all accepted password bytes, isolates `make test` from the persistent Compose PostgreSQL/session/cache runtime, makes the same-origin harness clean up only its uniquely identified test records, and fixes the concurrency harness's isolated disable database setup. Final validation before merge: backend 40 tests / 180 assertions and frontend 2 / 2; Pint, Larastan, PostgreSQL concurrency, same-origin auth, frontend lint/typecheck, production frontend build, Compose config validation and `git diff --check` pass. The ordinary Compose development build still reproduces the known baseline `/_global-error` prerender failure; production build passes.

## M1.2 implementation outcome

M1.2 Career Core is completed / PASS. The remediation enforces the same-owner User/Profile/Source/Fact/Evidence/Claim chain in application code and PostgreSQL, rejects deterministic semantic upgrades through the executable extraction path, exposes a confirmed/provenanced/Truth-Guard-PASS matching boundary, makes ModelPolicy select configured provider/model, reconciles already-applied legacy Career schemas non-destructively, redacts Career failures, preserves supersession history, and makes all three Truth Guard outcomes operational. LLM runs retain safe success/failure metadata without raw Career content or credentials.

Final validation on 2026-09-19: full backend 68 tests / 428 assertions; Career on isolated PostgreSQL 28 / 244; frontend 6 / 6; Pint 87 files; Larastan 0 errors; ESLint, TypeScript, production frontend build, Compose config, fresh PostgreSQL migration/rollback/re-up, representative legacy-schema forward upgrade, and `git diff --check` pass. The canonical local HTTP First Value harness and an interactive browser run both passed authenticated extraction, evidence, Confirm, Edit and Confirm, Reject, Leave Pending, confirmed facts, PASS Claims and manual entry with AI unavailable. Synthetic runtime records and temporary provider infrastructure were cleaned; the local backend was restored to `AI_PROVIDER=none`. PR #26 is open against `stage`; its required GitHub checks and current PR contract pass, and it awaits independent review/merge. No version tag or release is associated with this slice.

### M1.2 supersession concurrency revalidation — 2026-09-19

The remaining CareerFact supersession race is closed. The service locks and rechecks the original fact inside its transaction; PostgreSQL enforces one confirmed replacement per superseded fact through a partial unique index. Two independent Laravel processes were released from a PostgreSQL-backed barrier against the same confirmed original while a database trigger held the first insert open. Exactly one replacement committed, and the other received the controlled conflict. Verification confirmed direct duplicate rejection by the index, preserved review/history fields, old Claim `BLOCK`, replacement Claim `PASS`, one `TrustedCareerQuery` result and valid A → B → C history. PostgreSQL legacy upgrade plus rollback/re-up passed; full backend validation passed with 68 tests / 428 assertions; Pint and Larastan passed.

## M1.3 review and remediation state

The original M1.3 independent review verdict is `CHANGES REQUIRED`. M1.3R remediation implementation is complete locally and awaits a fresh independent review; M1.4 remains blocked.

The remediation separates the non-superuser/non-BYPASSRLS `cvortex_app` runtime role from the migration role, forces owner-scoped RLS across all Vacancy tables, derives and clears owner context at HTTP/service/job boundaries, serializes same-owner/same-URL import through PostgreSQL advisory and row locks, enforces logical-identity and snapshot-version uniqueness, rejects instruction-directed provider output before matching, and makes VacancySnapshot creation-only in Eloquent and PostgreSQL.

The previous local validation record was incomplete: it recorded migration rollback/re-up without re-running the PostgreSQL security suite against preserved test data. M1.3-R05 is corrected on 2026-09-20. `bash scripts/test-vacancy-postgres-revalidation.sh` used one newly created disposable PostgreSQL database: fresh migration, first `VacancyPostgresSecurityTest` (44 assertions), rollback only `2026_09_19_000009_remediate_vacancy_core_review_findings`, migrate up, and a second security-suite run (44 assertions), with no database-wide cleanup. The first suite's four `users` rows survived rollback/re-up; the second suite created four new fixture users without violating `users_email_unique`. Fixtures now append a per-test ULID suffix, and the harness refuses to drop a database unless its own `CREATE DATABASE` succeeded. The suite still emits three non-failing `file_get_contents` warnings because the disposable Compose backend has no `apps/backend/.env`; they are not suppressed and do not affect its 44 assertions.

R01 and R04 were re-exercised by both PostgreSQL security-suite runs; R02 passed once through `bash scripts/test-vacancy-postgres-concurrency.sh` with independent workers converging on one Vacancy and snapshot versions 1/2; R03 remains covered by `make test`. Also passed: `bash scripts/check-agent-contract.sh review .agents/tasks/m1-3-vacancy-core.md`, `docker compose --env-file .env.example config --quiet`, `make test` (backend 87 tests / 515 assertions; frontend 7 / 7), and `make lint` (Pint 120 files, Larastan 0 errors, ESLint and TypeScript). M1.3 now awaits the independent remediation re-review; M1.4 remains blocked. The known Compose development `NODE_ENV=development` frontend production-build failure remains tracked separately and is not attributed to M1.3R.

### M1.3R3 remaining PR #27 findings — 2026-09-20

The six remaining technical findings are remediated locally and await independent PR re-review. Backend and Horizon receive only the non-superuser/non-BYPASSRLS `cvortex_app` runtime credentials; an explicit Compose tools-profile migration service receives the separate administrative connection, and `scripts/check-runtime-db-credentials.sh` guards the resolved configuration without displaying passwords. Deterministic Vacancy validation now rejects the prior-prompt ignore/disregard/override family, derives persisted dimensions from source evidence rather than provider enums, uses boundary-aware skill terms, parses bounded duration/experience subject grammar conservatively, and selects analyses by exact current Career signature before deterministic `created_at DESC, id DESC` stale fallback.

Local validation PASS: `bash scripts/check-agent-contract.sh review .agents/tasks/m1-3-vacancy-core.md`; `docker compose --env-file .env.example config --quiet`; `bash scripts/check-runtime-db-credentials.sh`; targeted `VacancyCoreTest` (22 tests / 116 assertions); `make test` (backend 97 tests / 560 assertions, frontend 7 / 7); `make lint` (Pint 120 files, Larastan 0 errors, ESLint, TypeScript); `bash scripts/test-vacancy-postgres-revalidation.sh` (two `VacancyPostgresSecurityTest` runs / 44 assertions each, rollback/re-up); `bash scripts/test-vacancy-postgres-concurrency.sh`; and `git diff --check`. M1.4 remains blocked pending the independent PR #27 remediation re-review.

### M1.3R4 follow-up PR #27 findings — 2026-09-20

Follow-up remediation derives explicit `MANDATORY` importance from source wording rather than trusting a provider downgrade to `PREFERRED` or `UNCERTAIN`, while preserving source-derived preferred cues. The source-level prompt-injection boundary additionally recognizes equivalent prior-instruction wording such as `Disregard earlier directions`. Regression covers a missing source-required Kubernetes requirement alongside a matched Laravel requirement, which correctly yields `MAYBE`, plus the equivalent suppression directive. Local validation PASS: targeted `VacancyCoreTest` (23 tests / 119 assertions), `make test` (backend 98 tests / 563 assertions; frontend 7 / 7), `make lint`, and `git diff --check`. M1.4 remains blocked pending independent PR #27 remediation re-review.

### M1.3R5 follow-up PR #27 findings — 2026-09-20

Follow-up remediation derives duration-based `EXPERIENCE` from the source excerpt even when the provider label contains only the skill, binds salary values to a single matching currency/amount expression, permits exact confirmed evidence for unquantified experience, and excludes negated source wording from persisted requirements. Local validation PASS: targeted `VacancyCoreTest` (26 tests / 124 assertions), `make test` (backend 101 tests / 568 assertions; frontend 7 / 7), `make lint`, and `git diff --check`. M1.4 remains blocked pending independent PR #27 remediation re-review.

### M1.3R6 follow-up PR #27 findings — 2026-09-20

Follow-up remediation requires a distinct runtime database password: `make init` upgrades an existing missing or administrative-equivalent local runtime password, while Compose and PostgreSQL role initialization reject an unsafe configuration. Resolved Compose checks verify backend and Horizon use `cvortex_app` without the migration credential. Matching now compares locations canonically, requires explicit language proficiency rather than incidental language mentions, and derives commercial/professional/production experience from source evidence rather than a provider-supplied technical enum. Local validation PASS: targeted `VacancyCoreTest` (28 tests / 131 assertions); resolved Compose credential check; runtime-role rejection of an administrative-equivalent password; `make test`; `make lint`; and `git diff --check`. M1.4 remains blocked pending independent PR #27 remediation re-review.

### M1.3R7 follow-up PR #27 findings — 2026-09-21

Structured matching now requires experience amounts and explicit units tied to an experience subject, converts years/months only when both sides carry units, compares salary intervals only when currency and pay period are comparable, requires language proficiency evidence tied to the named language, and classifies work format only from arrangement context. Ambiguous multi-duration, salary, and incidental technology/language evidence stays unknown. Regression coverage also rejects comma-grouped salary amounts when their meaning is ambiguous. Local validation PASS: targeted `VacancyCoreTest` (33 warnings / 207 assertions); `make test` (backend 108 tests / 651 assertions, 4 PostgreSQL-only skips; frontend 7 / 7); `make lint` (Pint 120 files, Larastan 0 errors, ESLint, TypeScript); `bash scripts/check-agent-contract.sh review .agents/tasks/m1-3-vacancy-core.md`; `docker compose --env-file .env.example config --quiet`; PostgreSQL revalidation (two `VacancyPostgresSecurityTest` runs / 44 assertions each, rollback/re-up); PostgreSQL concurrency (independent workers converged on one Vacancy); and `git diff --check`. PostgreSQL checks ran in a separately named disposable Compose project because the existing Compose runtime/admin password configuration fails the harness preflight; the existing project and database were left untouched. The known `.env` test warnings remain unsuppressed. PR #27 awaits independent re-review; M1.4 remains blocked.

### M1.3R8 follow-up PR #27 findings — 2026-09-21

Follow-up remediation makes `make init` append a generated runtime password when upgrading a legacy `.env` that lacks the key. Vacancy validation rejects generic technical/domain labels that omit the source-derived subject, rejects suppression directives that tell the extractor not to consider the vacancy and return zero/no output, canonicalizes supported Cyrillic language names for matching, and excludes requirement sentence framing from duration subjects. Local validation PASS: targeted `VacancyCoreTest` (213 assertions); legacy-runtime-password append regression; resolved Compose config; `make test` (backend 110 tests / 657 assertions, 4 PostgreSQL-only skips; frontend 7 / 7); `make lint` (Pint 120 files, Larastan 0 errors, ESLint, TypeScript); `bash scripts/check-runtime-db-credentials.sh`; and `git diff --check`. PR #27 awaits independent re-review; M1.4 remains blocked.

### M1.3R9 follow-up PR #27 findings — 2026-09-21

Follow-up remediation fails extraction closed for direct instructions not to extract, parse or list requirements; binds mandatory/preferred cues to the labeled requirement's source clause, treating an inseparable mixed-cue clause as `UNCERTAIN`; and exposes a retry action when a failed Vacancy analysis has no analysis payload. The README now accurately records that M1.3 remediation awaits independent PR #27 re-review and that M1.4 is blocked. Local validation PASS: targeted `VacancyCoreTest` (218 assertions); frontend vacancy retry regression (8 / 8); resolved Compose config; `make test` (backend 111 tests / 662 assertions, 4 PostgreSQL-only skips; frontend 8 / 8); `make lint` (Pint 120 files, Larastan 0 errors, ESLint, TypeScript); production frontend build; and `git diff --check`. PR #27 awaits independent re-review; M1.4 remains blocked.

### M1.3R10 follow-up PR #27 findings — 2026-09-21

Follow-up remediation makes a Vacancy status claim verify snapshot currency in the same atomic update, so a superseded job cannot change a newer snapshot from `PENDING` to `RUNNING`. Deterministic validation now rejects generic technical/domain category labels such as `technology`, evaluates negation against the labeled subject rather than an unrelated clause, and accepts unambiguous space-grouped salary thousands in both validation and matching. Local validation PASS: targeted `VacancyCoreTest` (227 assertions); resolved Compose config; `make test` (backend 113 tests / 671 assertions, 4 PostgreSQL-only skips; frontend 8 / 8); `make lint` (Pint 120 files, Larastan 0 errors, ESLint, TypeScript); PostgreSQL revalidation (two `VacancyPostgresSecurityTest` runs / 44 assertions each, rollback/re-up, preserved users); PostgreSQL concurrency (independent workers converged on one aggregate); and `git diff --check`. The default local Compose configuration retains an equal runtime/admin password and was not changed; PostgreSQL validation ran in a separately named disposable Compose project with distinct test passwords. PR #27 awaits independent re-review; M1.4 remains blocked.

### M1.3R11 final PR #27 findings — 2026-09-21

F01 now counts adjacent mandatory evidence as an unsupported mandatory gap and adjacent preferred evidence as a preferred gap; recommendation thresholds remain unchanged. Observable regressions cover exact Laravel match, adjacent React evidence with Laravel/Vue candidate evidence (`MAYBE` when mandatory; `APPLY` when preferred), and three mandatory gaps (`LOW_PRIORITY`). F02 deterministically recognizes bounded language proficiency forms including `English at B2 level`, `English level: B2`, `B2 English`, `B2-level English`, `English proficiency B2/at B2`, and `English upper-intermediate`; incidental parser evidence remains a gap, matching B2 evidence matches, and no qualification equivalence is inferred. F03 deduplicates only against the latest snapshot of a URL-backed aggregate; A→A deduplicates, A→B creates v2, and A→B→A creates distinct v3 with the historical content hash, preserved immutable history, and analysis queued/stored against v3. The owner-wide content-hash uniqueness constraint was removed while `(vacancy_id, version)` remains unique; migration `000010` upgrades existing PostgreSQL schemas.

Local validation PASS: targeted remediation regressions; `bash scripts/check-agent-contract.sh review .agents/tasks/m1-3-vacancy-core.md`; `docker compose --env-file .env.example config --quiet`; `make test` (backend 116 tests / 720 assertions, 4 PostgreSQL-only skips; frontend 8 / 8); `make lint` (Pint 121 files, Larastan 0 errors, ESLint and TypeScript); `bash scripts/check-runtime-db-credentials.sh`; PostgreSQL revalidation (two security-suite runs / 44 assertions each, migration rollback/re-up and preserved users); PostgreSQL concurrency (one aggregate, versions 1/2); PostgreSQL A→B→A regression (8 assertions); and `git diff --check`. Disposable Compose validation used distinct ephemeral admin/runtime credentials; runtime role and forced RLS/owner isolation assertions passed. The PostgreSQL suites emitted the known non-failing `.env` file warnings from disposable backend containers. The user authorized the bounded implementation override because `NEXT.md` still points to the read-only review task. PR #27 awaits independent re-review; M1.4 remains blocked and frontend `NODE_ENV=development` remains separately tracked.

### M1.3R12 follow-up PR #27 findings — 2026-09-22

Follow-up remediation excludes negated Career evidence from structured duration matching, classifies qualified arbitrary language names from source evidence, binds technical/domain labels to the source subject carrying the requirement cue, and fails closed for skip/omit extraction directives. Unquantified experience removes source-only requirement-strength framing before exact evidence comparison. Import refresh now selects and loads the returned Vacancy ID rather than a stale pre-import selection. PostgreSQL revalidation rolls back both current Vacancy remediation migrations before re-applying them, so the RLS migration is actually exercised. Local validation PASS: targeted `VacancyCoreTest` (44 warnings / 293 assertions); frontend vacancy workspace regression (9 / 9); `NODE_ENV=production` frontend build; Compose config; `make test` (backend 119 tests / 737 assertions, 4 PostgreSQL-only skips; frontend 9 / 9); `make lint` (Pint 121 files, Larastan 0 errors, ESLint and TypeScript); PostgreSQL revalidation (two security-suite runs / 44 assertions each, preserved users) and concurrency (independent workers converge on one aggregate); and `git diff --check`. The ordinary development frontend build retains the separately tracked `NODE_ENV` / `/_global-error` failure; production build passes. PR #27 awaits independent re-review; M1.4 remains blocked.

### M1.3R13 follow-up PR #27 findings — 2026-09-22

Follow-up remediation rejects explicit inability evidence (`cannot`, `can not`, `unable to`) before structured work-format matching, binds active `requires` clauses to their required subject and mandatory importance, and validates arbitrary-language normalized levels only when the same source language carries the qualification. Migration `000010` rollback now restores the actual preceding schema: no owner-wide snapshot-content unique constraint is introduced. PostgreSQL revalidation explicitly performs a one-step `000010` rollback and asserts that this absent constraint remains absent before the existing two-migration rollback/re-up security validation. Local validation PASS: targeted `VacancyCoreTest` (46 warnings / 298 assertions); Compose config; `make test` (backend 121 tests / 742 assertions, 4 PostgreSQL-only skips; frontend 9 / 9); `make lint` (Pint 121 files, Larastan 0 errors, ESLint and TypeScript); `NODE_ENV=production` frontend build; PostgreSQL revalidation (one-step migration boundary plus two 44-assertion security-suite runs with preserved users); PostgreSQL concurrency; and `git diff --check`. PR #27 awaits independent re-review; M1.4 remains blocked.

### M1.3R14 follow-up PR #27 findings — 2026-09-23

Follow-up remediation strips terminal sentence punctuation from structured location values, rejects `lack`/`lacking` candidate statements as negative evidence for both direct and structured matching, and preserves active requirements in company-marketing phrasing such as `Our company requires Kubernetes.` Local validation PASS: targeted `VacancyCoreTest` (48 warnings / 304 assertions); Compose config; `make test` (backend 123 tests / 748 assertions, 4 PostgreSQL-only skips; frontend 9 / 9); `make lint` (Pint 121 files, Larastan 0 errors, ESLint and TypeScript); and `git diff --check`. The production frontend and PostgreSQL harnesses were unchanged by this backend-only slice and remain covered by the immediately preceding validation. PR #27 awaits independent re-review; M1.4 remains blocked.

## Current authorized task

`m1-3r-vacancy-core-remediation-review` (read-only independent review).

Authority is `.agents/state/NEXT.md` + blockers + `.agents/tasks/m1-3r-vacancy-core-remediation-review.md`.

## M0 validation

Result: **PASS**.

Reproducible command results are recorded in [M0 Runnable Core Validation Evidence](../evidence/m0-runnable-core-validation.md).

Validated from tracked contents on the real Docker runtime and a genuinely
fresh worktree: bootstrap/build/start, Nginx/browser shell, health behavior,
PostgreSQL/Redis failure and recovery, PostgreSQL/private-storage persistence,
Redis disposal, host exposure, backend/frontend quality checks and production
frontend build.
