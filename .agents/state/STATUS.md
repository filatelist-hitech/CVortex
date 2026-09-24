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

The original M1.3 review returned `CHANGES REQUIRED`. Later pushes triggered six actionable PR #27 findings; remediation is in progress and merge is blocked. M1.4 remains blocked until the eventual merge.

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

### M1.3R15 PR #27 remediation R01–R05 — 2026-09-23

Reanalysis now locks the Vacancy aggregate before selecting its current snapshot; import uses the same row lock. Repeated requests preserve an existing `RUNNING` state, and current-snapshot job claims and terminal transitions are atomic. Requirement validation binds label, dimension, normalized value, importance and negation to one supporting source clause and rejects ambiguous repeated labels. Matching normalizes straight/curly contractions and evaluates each relevant Career evidence clause locally, preserving positive evidence in mixed facts. Candidate-directed `requires`/`needs`/`must` phrasing is preserved while mission, product, architecture, business and stack requirements are excluded. Adjacent hardening excludes negated neighboring technology from adjacent evidence and recognizes duration tied directly to a skill.

Local validation PASS: targeted `VacancyCoreTest` (55 known missing-`.env` warnings / 373 assertions); `make test` (backend 130 tests / 817 assertions, four PostgreSQL-only skips; frontend 9 / 9); `make lint` (Pint 123 files, Larastan 0 errors, ESLint and TypeScript); production frontend build; agent contract; Compose config; PostgreSQL revalidation (two 44-assertion security suites, preserved users); and PostgreSQL concurrency (independent import/reanalysis races A and B, stale job and double reanalysis converged to `COMPLETED`). PostgreSQL validation used a separately named disposable Compose project with distinct ephemeral administrative/runtime credentials; its unique harness databases were dropped by the harnesses. The known disposable-container `.env` warnings remain unsuppressed. PR #27 awaits independent re-review; M1.4 remains blocked.

### M1.3R16 final semantic findings N01–N04 — 2026-09-23

Subject-local zero/none/numeric-zero/lack evidence cannot prove a matching technology; candidate-directed labels are checked against the bounded qualification subject after role/team/position/project framing; extraction suppression and empty-output directives fail closed while ordinary SQL-injection, N+1, API-array and data-extraction requirements survive; and structured location matching accepts explicit `Based in`, `Located in`, `Lives in` and related evidence without treating incidental Berlin mentions as a location. Extraction prompt/evals and the canonical Vacancy behavior contract now state these boundaries. Independent review threads were already marked resolved on GitHub before this remediation; their code concerns were still present at the expected PR head and are corrected in this commit.

Local validation PASS: targeted semantic regressions (74 assertions); `make test` (backend 133 tests / 879 assertions, four PostgreSQL-only skips; frontend 9 / 9); `make lint` (Pint 123 files, Larastan 0 errors, ESLint and TypeScript); `bash scripts/check-agent-contract.sh review .agents/tasks/m1-3-vacancy-core.md`; `docker compose --env-file .env.example config --quiet`; extraction-eval JSON parse; and `git diff --check`. PostgreSQL revalidation passed with two 44-assertion security-suite runs, migration rollback/re-up and preserved users; concurrency passed with independent import/reanalysis races and stale-job checks. Those final harness runs used a fresh named Compose project with distinct ephemeral admin/runtime credentials and PostgreSQL plus Redis; harness databases and the isolated project were removed. Known non-failing `.env` access warnings from disposable Laravel containers remain visible. Earlier harness attempts failed before executing their full suites because readiness/Redis dependencies were not provided; the final isolated rerun passed both harnesses. PR #27 awaits independent re-review; M1.4 remains blocked.

### M1.3R17 final PR #27 findings P01–P03 — 2026-09-23

P01 now derives cue subjects independently, removes qualification modifiers from subject identity, and requires source-supported qualification labels for TECHNICAL, DOMAIN, EXPERIENCE and LANGUAGE. `Strong`/`Strong skills` cannot stand for Kubernetes; duration-only `3 years` cannot stand for PHP/backend experience; and `Advanced` cannot stand for English B2. PostgreSQL, React, REST API, English B2 and reordered Laravel labels remain covered. A confirmed `Strong communicator` does not match a Kubernetes requirement. P02 treats empty-output tokens as injection only with requirement-extraction/output directive context; the exact API `return []` control survives while empty requirement output and avoid/prevent/ignore/suppress directives still fail closed, including an empty provider response. P03 gives list and detail requests monotonic freshness checks, reads the current selection after the list await, preserves a just-imported explicit ID against an older poll, and applies first-item fallback when the current selection has disappeared. Deferred-promise UI regressions cover user selection during a poll, overlapping refresh completion order, import during a poll, and removed-selection fallback. Same-family audit found no additional changes beyond these reproducible cases.

Local validation PASS: targeted backend semantic regressions (104 assertions); `make test` (backend 135 tests / 905 assertions, four PostgreSQL-only skips; frontend 12 / 12); `make lint` (Pint 123 files, Larastan 0 errors, ESLint and TypeScript); production frontend build; review agent contract; Compose config; and `git diff --check`. PostgreSQL/RLS and import/reanalysis concurrency files were unchanged from the validated `2c7e389` head. Disposable backend test containers emitted the known visible missing-`.env` warnings. `bash scripts/check-pr-contract.sh 27` was attempted but could not authenticate because GitHub CLI has no active login; live PR metadata/checkpoints were inspected through the GitHub connector. At the pre-change GitHub snapshot all 72 threads were marked resolved, including the three newly posted P01–P03 findings, contrary to the handoff count. Their code findings were still present and have been fixed; replies are pending the pushed remediation head. Awaiting push CI and independent re-review. M1.4 remains blocked; no milestone transition is made.

### M1.3R18 PR #27 final merge gate — 2026-09-23

Final remediation corrected five live review findings: modifier-aware subject negation, source-bound residency and industry classification, quoted prompt-injection examples, and contextual no-requirements detection. A clean full-diff review also found and fixed a stale import-response selection race; regression review and a second clean review found and fixed adjacent domain-context and residency-classification false positives. P01/P02/P03 from the prior handoff were checked against code and executable regressions. No P0/P1/P2 findings remain in the reviewed diff.

Live GitHub intake covered 2 conversation comments, 100 submitted reviews, 162 inline comments and all 77 review threads with pagination. The five actionable open threads received specific replies on commit `2483a8d` and were resolved; GraphQL reports zero unresolved threads. At that head, PR #27 targets `stage`, GitHub reports `mergeStateStatus=CLEAN`, and `Roadmap metadata`, `PR contract`, and `m0-quality` checks passed. This state records review readiness, not a merge.

Validation: targeted regressions first failed before fixes and passed after them; `VacancyCoreTest` passed (480 assertions before the final adjacent fixes); final `make test` passed (backend 143 tests / 928 assertions, four PostgreSQL-only skips; frontend 13/13); `make lint` passed (Pint 123 files, Larastan 0 errors, ESLint, TypeScript); production frontend build, PHP syntax for both changed services, agent contract, Compose config, runtime credential check, PR contract, and `git diff --check` passed. Isolated PostgreSQL revalidation passed fresh migration, two runtime-role security suites of 44 assertions each, one-step `000010` rollback, two-migration rollback/re-up with preserved users; the independent-process import/reanalysis concurrency harness passed. PostgreSQL validation used an isolated Compose project with distinct ephemeral admin/runtime passwords. Disposable backend containers retained the known non-failing missing-`.env` warnings. The first PostgreSQL attempt started before PostgreSQL readiness and executed no suite; the healthy isolated rerun passed. The separate development-mode frontend build issue remains tracked.

### M1.3R19 follow-up review findings — 2026-09-23

After the state commit `daca403`, GitHub added four unresolved threads: connector words in concrete subjects (P1), full residency labels (P1), ordered CEFR matching (P2), and `resident of` candidate evidence (P2). All four were reproduced by tests before changes. A fresh code review found an adjacent language-subject collision when a source mentions another language; it was also reproduced. Local `make test` now passes (backend 148 tests / 938 assertions, four PostgreSQL-only skips; frontend 13/13), and `make lint` passes. Merge readiness is suspended until the fixes are committed, pushed, replied to, resolved, and validated on the new GitHub head.

After `be4d22d`, GitHub added two more P1 threads: unordered compound candidate terms and contracted source negation. Both were reproduced before fixes and corrected with targeted regression tests. `make test` passed again (backend 150 tests / 947 assertions, four PostgreSQL-only skips; frontend 13/13). The four earlier threads received specific replies; all six remain unresolved pending the new head and CI. Merge remains blocked.

## Current authorized task

`m1-3r-vacancy-core-remediation-review` (new PR #27 findings remediation).

Authority is `.agents/state/NEXT.md` + blockers. M1.4 follows only after the merge succeeds.

## M0 validation

Result: **PASS**.

Reproducible command results are recorded in [M0 Runnable Core Validation Evidence](../evidence/m0-runnable-core-validation.md).

Validated from tracked contents on the real Docker runtime and a genuinely
fresh worktree: bootstrap/build/start, Nginx/browser shell, health behavior,
PostgreSQL/Redis failure and recovery, PostgreSQL/private-storage persistence,
Redis disposal, host exposure, backend/frontend quality checks and production
frontend build.

### M1.3R20 final PR #27 merge gate — 2026-09-24

M1.3 implementation and PR #27 remediation are complete. The failed `m0-quality` on `c6a48fe` was the frontend regression `ignores an older polling list response when refreshes overlap`: the assertion raced the delayed optional poll, so `listCalls` was still 1 instead of 3. Test synchronization was corrected in `d3faf30`; `m0-quality` passed on subsequent pushed heads, including final code head `06d3994c035b37b997944611d65b61241843b932`.

The final remediation added fail-closed handling for direct do-not-output suppression, source-bound recognition and evidence matching for unqualified catalogued languages, and label-bound classification when location and work-format cues share a clause. Regression tests cover the suppression phrase, Italian-language evidence, distinct Remote work/Berlin subjects, and comma-separated city/country evidence. The comma-separated location finding was not reproducible: normalization already preserves both place tokens for structured comparison; the exact scenario now has end-to-end regression coverage. Independent review of the final remediation diff found P0=0, P1=0, P2=0.

Validation on `06d3994`: targeted regressions PASS; `make test` PASS (backend 167 tests / 988 assertions, 4 PostgreSQL-only skips; frontend 13/13); `make lint` PASS (Pint 124 files, Larastan 0 errors, ESLint and TypeScript); `bash scripts/check-pr-contract.sh 27` PASS; `bash scripts/check-agent-contract.sh implementation .agents/tasks/m1-3r-vacancy-core-remediation-review.md --write --user-override` PASS; PHP syntax for changed backend files PASS; and `git diff --check` PASS. PostgreSQL revalidation was not repeated because this remediation changed no schema, RLS, database ownership, or concurrency behavior; the immediately preceding isolated PostgreSQL validation remains recorded above.

Fresh paginated GitHub intake: 132 submitted reviews, 210 inline comments, 2 conversation comments, and 101 review threads across two GraphQL pages. All actionable findings received specific replies; GraphQL reports zero unresolved threads. `PR contract`, `Roadmap metadata`, and `m0-quality` all PASS on `06d3994`; local HEAD, origin branch HEAD, and PR HEAD match. GitHub reports `mergeStateStatus=CLEAN`. PR #27 is open and unmerged; M1.4 has not started. This records readiness for merge into `stage`, not a completed merge.

### M1.3R21 readiness suspended — 2026-09-24

The state commit `70d4b09` triggered a fresh review with three additional findings: P1 past remote-work evidence must not impose current work-format constraints; P2 `located in` classification must bind to the labeled place; and P2 duplicate-vacancy migration must reconcile aggregate status with the latest moved snapshot. Merge readiness is suspended while these findings are reproduced and remediated. The previous green checks and zero-thread snapshot apply to `06d3994`/`70d4b09` before this intake and do not authorize merge. M1.4 remains blocked.

### M1.3R22 PR #27 remediation gate — 2026-09-24

Remediated the historical work-format and residence false blockers, label-bound location classification, duplicate-snapshot chronology and aggregate `analysis_status` reconciliation, Native → Fluent language compatibility, and subject-bound passive negation. Each newly reported behavior was covered by a regression; historical residence and passive negation reproduced as failures before their fixes. The data reconciliation is a forward-only migration, preserves existing snapshots and analyses, and passed fresh migration, legacy duplicate upgrade, rollback/re-up, preserved-data, RLS/security and independent-process concurrency checks.

Independent review of the final remediation delta found P0=0, P1=0, P2=0. Local validation on code HEAD `f5b25387e3e271415a94ee8fba63b0dabfa87f7e`: `make test` PASS (backend 179 tests / 1027 assertions, four PostgreSQL-only skips; frontend 13/13); targeted VacancyCore suite PASS (583 assertions); `make lint`, PostgreSQL migration/revalidation/duplicate-upgrade/concurrency harnesses, PHP syntax, Compose/runtime credential checks, PR contract, agent contracts and `git diff --check` PASS. PostgreSQL security suites emitted the known non-failing missing-`.env` warnings in disposable backend containers. Frontend production build passed in `m0-quality`.

Fresh paginated GitHub intake after the last code push covered 149 submitted reviews, 235 inline comments, 2 conversation comments and all 114 review threads. All actionable findings have specific replies; GraphQL reports zero unresolved threads. `PR contract`, `Roadmap metadata` and `m0-quality` PASS on `f5b2538`; local, remote and PR HEADs match; `mergeStateStatus=CLEAN`. PR #27 remains open and unmerged. This state authorizes merge into `stage` after the required checks on this separate state commit also pass; M1.4 has not started.

### M1.3R23 readiness suspended — 2026-09-24

The fresh GitHub review on state commit `e1cd473` added two P1 findings: contraction normalization turns `wasn't` into tokens that bypass the passive-negation grammar, and ongoing location intervals such as `since 2018` are captured as part of the place. Merge-ready permission is revoked; remediation and validation are required before merge. The earlier green checks and zero-thread snapshot do not authorize merge on this head. M1.4 remains blocked.

### M1.3R24 readiness remains suspended — 2026-09-24

Live PR #27 intake on `f9a1df0` confirms the PR remains open against `stage`; local, remote and PR HEADs match, and `PR contract`, `Roadmap metadata`, and `m0-quality` pass on that SHA. Paginated GraphQL reports nine unresolved review threads, including seven new findings after the earlier two-finding intake: location conjunction capture, historical remote employee work-format evidence, stale RUNNING analysis recovery, postfix experience classification, banking/retail product subject classification, role-prefixed extraction suppression, and migration reconciliation that can clear an actual matching failure. GitHub reports `mergeStateStatus=BLOCKED`. Merge readiness remains suspended pending reproduction, repair, validation and fresh review. M1.4 remains blocked.

### M1.3R25 PR #27 final code gate — 2026-09-24

The nine live actionable findings were reproduced or verified against the current implementation, covered by regression tests, fixed, replied to individually and resolved. The repair adds bounded current-location parsing; excludes historical remote employment from current work-format evidence; exposes explicit recovery for stale RUNNING analyses; classifies postfix experience and domain/product subjects from source-bound semantics; rejects role-prefixed extraction suppression; and adds forward migration `2026_09_24_000012_recover_completed_extraction_without_analysis` to preserve retryable `FAILED / ANALYSIS_ERROR` state after completed extraction without persisted analysis. PostgreSQL rollback intentionally retains this data repair. Independent review of the repair delta found P0=0, P1=0, P2=0.

On code HEAD `a85512bcaeca5be2bb23d140cc842496b7b37c98`, `make test` passed (backend 182 tests / 1049 assertions, four PostgreSQL-only skips; frontend 14/14); `make lint` passed (Pint 126 files, Larastan 0 errors, ESLint and TypeScript); targeted regressions, PHP syntax, production frontend build, Compose config, runtime-credential check, PR contract, implementation/review agent contracts, and `git diff --check` passed. Isolated PostgreSQL fresh migration, legacy duplicate upgrade, rollback/re-up, preserved-data checks, runtime role/RLS security suites (44 assertions twice), Vacancy revalidation and independent-process concurrency harness passed. Disposable PostgreSQL resources and temporary credentials were removed.

Fresh paginated GitHub intake covered all 123 review threads. Each of the nine actionable findings received a specific `Fixed in a85512b` reply and was resolved; unresolved actionable threads are zero. `PR contract`, `Roadmap metadata`, and `m0-quality` pass on both the code head and the separate merge-ready state commit; the final post-state-commit intake found no new comments, unresolved actionable threads, or merge blockers, and GitHub reports `mergeStateStatus=CLEAN`. PR #27 is ready for merge into `stage`, remains open and unmerged, and M1.4 has not started.
### M1.3 completion — 2026-09-24

M1.3 Vacancy Core and its final remediation/review are complete. PR #27 was
squash-merged into `stage` at merge commit
`6a619258c25ee55a05ecbe7dbe8df8430841e9c1` on 2026-09-24T12:47:48Z.
The live post-merge state confirms PR #27 is merged and `stage` contains the
reviewed head `a52142e9613bf7f90f6d6462c77ef98fb73e63fd`. M1.4 has not started.

### M1.4 Application Draft — 2026-09-24

M1.4 is implemented on `feature/m1.4-application-draft`, commit `eb43e36`,
and delivered in open PR #31 targeting `stage`. The slice adds owner-scoped,
RLS-enforced saved application preparations, provenance-bound recommendations
and cover drafts, deterministic truth/schema validation around provider-neutral
generation and review, explicit human approval, and a saved frontend review
flow. No employer submission capability was added.

An independent review found one P1: manual edit review could PASS while only
listing a supported substring and omitting an unsupported clause. Commit
`3e4e5af` fixes this at the Truth Guard contract: review Skill v2 returns an
ordered FACTUAL/NON_FACTUAL segmentation, and the backend requires its exact
concatenation to equal the complete candidate content before accepting PASS.
The regression test returns PASS for only the supported substring of a mixed
supported/unsupported edit and verifies BLOCK. Independent re-review of the
full final diff returned P0=0, P1=0, P2=0.

Post-fix validation passed: targeted ApplicationDraft suite (8 tests / 74
assertions); `make test` (backend 191 tests / 1123 assertions; five
PostgreSQL-only tests skipped under SQLite; frontend 15/15); `make lint`
(Pint 138 files, Larastan 0 errors, ESLint and TypeScript); PHP syntax, runtime
Skill v2 JSON validation, Compose config, isolated PostgreSQL fresh
migration/rollback/re-up/data-preservation and owner/RLS suite (33 assertions
per run), `git diff --check`, and PR contract. Disposable Laravel/PostgreSQL
containers emitted the known non-failing warning because `.env` is absent.
The production frontend build passed before the backend/runtime-AI/docs/tests
remediation; required CI (`PR contract`, `Roadmap metadata`, `m0-quality`)
passed on fixed implementation head `e02b6b4`. The default development-mode
frontend build still fails prerendering `/_global-error`. PR #31 remains open
pending human review/merge; M1.4 is not merged.

### M1.4 independent review and remediation complete — 2026-09-25

The independent review invalidated the earlier zero-finding verdict, finding
two P1 and multiple P2 defects in strict OpenAI schema compatibility, the
Truth Guard's authority boundary, edited-draft recovery, provider errors,
output validation, revision history, UI stale actions, and generated
recommendation rationale. Commit `dedd6c9` remediates these issues: PASS now
requires exact current Claim wording, semantic reviews are batched, unsupported
content fails closed, blocked content can be corrected, draft revisions are
append-only and approvals reference a revision, output is bounded, provider
errors are controlled, and visible unsaved edits disable state-changing UI
actions.

Independent re-review of `origin/stage...dedd6c9` found P0=0, P1=0, P2=0.
Local validation passed: `make test` (backend 200 tests / 1213 assertions,
six PostgreSQL-only skips; frontend 15/15), `make lint` (Pint 139 files,
PHPStan, ESLint, TypeScript), production frontend build, PHP syntax, runtime
Skill JSON, Compose config, both agent contracts, shell syntax and
`git diff --check`. The isolated PostgreSQL gate passed fresh migration,
runtime-role/RLS and cross-owner checks (45 assertions), rollback/re-up with a
second 45-assertion run, parent-data preservation (users=4, facts=4,
vacancies=4), and stale-approval interleaving. Disposable backend tests emit
the known non-failing missing-`.env` warning.

On `dedd6c9`, required checks `PR contract`, `Roadmap metadata`, and
`m0-quality` passed. Fresh paginated GitHub intake found two historical
checkpoint conversation comments, zero submitted reviews, zero inline comments,
zero review threads and zero actionable unresolved threads. Local, upstream,
remote branch and PR heads match; GitHub reports `mergeStateStatus=CLEAN`.
PR #31 remains OPEN and unmerged. M1.4 implementation and remediation review
are complete; PR #31 is ready for human merge after the separate state commit's
required checks and fresh review gate pass. M2 has not started.

### M1.4 merge completion — 2026-09-25

PR #31 was squash-merged into `stage` at 2026-09-24T21:24:30Z. The reviewed
PR head was `7b6cb8971264cec00b0f211645673d1bb6196aa8`; merge/result SHA is
`8e08f45270d1adf7f6412db09c34e0c94d15772a`. The independent remediation
re-review of code at `dedd6c9` reported P0=0, P1=0 and P2=0; the commits after
that review changed only `.agents/state/` files. Immediately before merge,
`PR contract`, `Roadmap metadata` and `m0-quality` passed on the PR head,
GitHub reported `mergeStateStatus=CLEAN`, and paginated feedback intake found
zero submitted reviews, inline comments, review threads or actionable
unresolved threads (two existing conversation checkpoint comments remain).

After merge, local `stage` was fast-forwarded to `origin/stage` at the merge
SHA. The working tree was clean; the agent-contract smoke check and
`git diff --check` passed; the M1.4 migration and application/Truth Guard
revision code are present; no conflict markers were found. M1.4 is complete /
PASS. M2 implementation has not started; `NEXT.md` points to the bounded M2
planning task. The known development-mode frontend build issue remains tracked
separately.
