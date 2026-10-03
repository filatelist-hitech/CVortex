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
revision code are present; no conflict markers were found. The M1.4
implementation is merged and its independent remediation review is complete.
However, no real-user end-to-end Preview 0.1 validation or observed feedback is
recorded in the repository, so Preview 0.1 and the full M1.4 acceptance remain
unvalidated, not PASS. The M2 planning task has not started and remains blocked
until that evidence is available. The known development-mode frontend build
issue remains tracked separately.

### MCP Gateway Foundation — read-only implementation, 2026-09-27

**MCP READ-ONLY LOCAL E2E = PASS.** Final `make test`: backend 215 tests / 1419 assertions / 7 PostgreSQL-only skips; frontend 16/16. `make lint`: Pint 162 files, PHPStan 102 files / zero errors, ESLint and TypeScript. PostgreSQL owner/RLS validation: 3 tests / 55 assertions including MCP no-mutation checks.

**MCP CHATGPT E2E = BLOCKED_EXTERNAL (interactive access; entitlement unverified).** The current browser did not reach Developer Mode/app setup or Platform tunnel management; this is not classified as an entitlement denial.

The explicitly authorized `feature-mcp-gateway-foundation` branch now exposes exactly `vacancy_get` and `application_context_get` over Streamable HTTP, disabled by default. The MCP draft writer and MCP-only service method were removed; Application Draft, Truth Guard, Human Approval and normal first-party workflows remain. OAuth resource/issuer checks, strict redirect parsing, active-user and `mcp:use` enforcement, owner context/RLS, bounded context, safe client errors and read-only rate limiting protect the boundary. Inbound MCP does not call or require the outbound `LlmProvider`/`OPENAI_API_KEY` path. ADR-0021 records the decision; ADR-0020 is superseded. M2 has not started; `NEXT.md` now points to Preview 0.1 real-user end-to-end validation, which remains incomplete under its existing acceptance criteria.

Local validation is recorded in `docs/10-Operations/MCP-Gateway-Validation.md`: the current MCP Inspector CLI discovered exactly two read-only tools and successfully read a persisted owner vacancy whose analysis was `FAILED`, plus bounded incomplete context. PostgreSQL owner/RLS validation and before/after product-state checks passed; disabled/enabled route registration, OAuth resource/audience and redirect regression tests, and safe unauthenticated response checks passed. The live product-state hash was identical before and after both read calls. The stored OAuth session supported the live calls; fresh Inspector DCR/login/consent/token issuance was not repeated in this pass.

ChatGPT MCP E2E and Secure MCP Tunnel remain **BLOCKED_EXTERNAL**. The ChatGPT sidebar showed Plus, but Developer Mode settings/app creation were not reached and the official plan-specific documentation is ambiguous for Plus; entitlement is therefore unknown, not denied. Platform was at sign-in, so tunnel permission, tunnel ID and process credential were unavailable. No public inbound access was opened. Exact user action is recorded in `BLOCKERS.md`. No MCP write action is exposed; unsupported mutations have no corresponding MCP capability.

### Career review test isolation — 2026-09-27

The review-flow test assumed that an accepted asynchronous extraction request had already run its queued job. With `QUEUE_CONNECTION=null`, the unchanged test reproduced the reported missing `Confirmed source wording.` key; with the configured sync driver, the source completed and all four candidates persisted. The original failing run's queue configuration was not captured. The test now fakes the queue and explicitly runs extraction before human review. Its confirmation, edit, rejection, pending and provenance assertions remain unchanged. The exact method passed with both queue drivers; Career/Vacancy/MCP/Access tests passed (184 tests, 1 skip), and the full backend suite passed (210 tests, 6 PostgreSQL-only skips). Pint, Larastan and `git diff --check` passed. No Career production behavior changed; the former backend-suite blocker is resolved.

### MCP route-toggle package discovery — 2026-09-27

The MCP route-toggle smoke test now runs `php artisan package:discover` before listing routes. This refreshes Laravel's ignored generated package manifest when an existing Docker workspace has installed Composer packages but stale provider-discovery metadata. The observed stale manifest omitted `laravel/mcp` and `laravel/passport`; regenerating it restored the expected routes without changing `.env` or application behavior. Validation: `bash scripts/test-mcp-gateway-toggle.sh` verified 0 routes with `MCP_ENABLED=false` and 20 with `true`; `make test` passed (backend 215 tests / 1419 assertions / 7 PostgreSQL-only skips; frontend 16/16); `make lint` passed (Pint 162 files, PHPStan 102 files / 0 errors, ESLint and TypeScript).

### Observability, Logging & Error Center — local implementation, 2026-09-27

The current user explicitly authorized `.agents/tasks/observability-error-center.md` as a bounded override of `NEXT.md`. Branch `feature/observability-error-center` contains structured redacted JSON logs, HTTP/request/queue/LLM correlation, safe API errors and frontend recovery, grouped PostgreSQL incidents with bounded recent occurrences, authenticated admin Error Center and browser telemetry, retention/CLI fallback, tests, OpenAPI, ADR-0022, data/operations/security documentation. This is a validated implementation awaiting independent review and merge.

Validation: backend SQLite suite 226 tests / 1467 assertions / 8 PostgreSQL-only skips with `APP_ENV=local`; dedicated PostgreSQL runtime-role test 1 test / 7 assertions; isolated fresh migration and migration rollback/re-up; Pint 172 files, PHPStan 108 files / zero errors; frontend 20/20, ESLint, TypeScript and `NODE_ENV=production` build; Compose config; OpenAPI YAML parsing; MCP route-toggle 0 disabled / 20 enabled; `git diff --check`. Isolated HTTP/browser checks covered safe 500/422 references, admin-only lookup by request ID, linked request/job/LLM details, status transition, browser telemetry, final Horizon job failure, and safe CLI output. The existing development-mode frontend build failure at `/_global-error` reproduced; production build passes and the blocker remains separately tracked. Preview 0.1 and external MCP/ChatGPT validation remain unverified as recorded in `NEXT.md` and `BLOCKERS.md`.

### Error Center user and operator documentation — 2026-09-27

The explicitly authorized follow-up adds a Russian user guide for safe error recovery, admin search/status handling, example cases and privacy/retention limits; expands the operator workflow and log fallback; and links these entry points from the documentation map and local-development guide. `NEXT.md` and `BLOCKERS.md` remain unchanged because Preview 0.1 is still the next authorized product work and its evidence gap is unchanged. Documentation validation passed: frontmatter and local links in all four changed documentation files, and `git diff --check`. The implementation validation is recorded above; this follow-up changes no runtime code.

### PR #36 review remediation — 2026-09-27

Addressed the four original review findings on `feature/observability-error-center`: Truth Guard/provider failures are recorded once before controlled 503 responses with request, user, LLM and application correlation; application incidents persist `application_id`; authenticated browser components are restricted to a finite server allowlist, bounding open browser incident groups while occurrence retention remains capped; API/frontend retryability follows provider failure category, not HTTP 503 alone. Error Center loads all grouped incidents across severities and statuses by default, explains that IDs are optional, and provides `Show all incidents` to clear filters. Raw `DEBUG`/`INFO` logs remain in local container logs per ADR-0022. `NEXT.md` and `BLOCKERS.md` are unchanged.

Fresh Codex review on the pushed remediation head added two follow-up findings. Structured exception logs now retain at most twelve safe frames (basename, line and function; no args, absolute paths or exception messages), and a recorded Throwable is suppressed from a second global report when a synchronous queue failure bubbles into the API request. Regression coverage verifies safe stderr frames and exactly one sync-queue incident. The Error Center user guide and diagnostics operations/ADR documentation describe the log boundary and default unfiltered incident list.

Validation on the complete remediation working tree: focused `DiagnosticsTest.php` passed (14 tests / 87 assertions); `make test` passed (backend 232 tests / 1540 assertions / 8 PostgreSQL-only skips; frontend 23/23); `DiagnosticsPostgresTest` passed under the PostgreSQL runtime role (1 test / 7 assertions); `make lint` passed (Pint 173 files, PHPStan 108 files / 0 errors, ESLint, TypeScript); production frontend build passed; Redocly validated `docs/05-API/diagnostics.openapi.yaml` with 5 style warnings and no schema errors; `git diff --check` passed. `bash scripts/check-pr-contract.sh 36` and remote checks passed on the previous pushed head; follow-up remote checks must be refreshed after push. The attached Error Center screenshot's API failure was traced to the diagnostics migration still being pending locally; the migration was applied, backend services restarted and both diagnostics tables were verified. A post-migration visual browser reload could not be confirmed because the macOS session was locked.

PR #36 remains open against `stage`; no merge was performed. The four original findings and two follow-up findings are fixed in the branch. The implementation awaits a fresh exact-head CI and independent Codex review after the follow-up push.

### PR #36 fresh review follow-up — 2026-09-28

The next Codex review identified three additional diagnostics gaps. Generated application output rejected by server-side validation is now recorded as `LLM_OUTPUT_INVALID` with preparation, LLM run and user correlation, then returned as a safe non-retryable provider error rather than a user-input 422. Explicit HTTP 429 responses use `RATE_LIMITED` and `retryable=true`. Sanitized exception stacks now begin with the throw-site basename and line, followed by at most eleven safe caller frames. Regression tests cover both output validators, rate limiting, safe output errors and bounded throw-site traces. The Error Center continues to show all incident severities/statuses by default; identifiers are optional filters, while raw DEBUG/INFO remain available in container logs under ADR-0022.

Validation on this follow-up working tree passed: `ApplicationDraftTest.php` (213 assertions), `DiagnosticsTest.php` (98 assertions), `make test` (backend 233 tests / 1575 assertions / 8 PostgreSQL-only skips; frontend 23/23; MCP route toggle 0 disabled / 20 enabled), PostgreSQL `DiagnosticsPostgresTest.php` (1 test / 7 assertions), `make lint` (Pint 173 files, PHPStan 108 files / 0 errors, ESLint and TypeScript), production frontend build, Redocly OpenAPI validation (5 style warnings, no schema errors), and `git diff --check`. PR #36 remains open against `stage` and unmerged; exact-head remote checks and a new independent review request follow the bounded fix push.

### PR #36 current review remediation — 2026-09-28

At reviewed head `f4e831092b694bac79aa05c75b2de3e91318ba81`, fixed all six unresolved Codex threads: guarded exception reporting when no active HTTP request exists and preserved Laravel's original report on recorder failure; consistently mapped malformed provider output to `LLM_OUTPUT_INVALID`; kept safe `Allow`, `Retry-After` and rate-limit response headers; redacted escaped JSON secrets; returned `null` rather than a prior request ID when browser telemetry is throttled; and persisted/displayed queue and connection context. Added regression coverage across diagnostics, application preparation/Truth Guard, PostgreSQL occurrences and browser reporting, with matching API and operator documentation.

Validation on the complete follow-up tree: focused backend diagnostics/application tests passed (36 tests / 360 assertions), focused browser tests passed (8/8), `make test` passed (backend 237 tests / 1624 assertions / 8 PostgreSQL-only skips; frontend 24/24; MCP route toggle 0 disabled / 20 enabled), `make lint` passed (Pint 173 files, PHPStan 108 files / 0 errors, ESLint, TypeScript), production frontend build passed, Compose config passed, Redocly validated the diagnostics OpenAPI file with 5 style warnings and no schema errors, and `git diff --check` passed. PostgreSQL runtime-role diagnostics passed (1 test / 7 assertions); PHPUnit reported one environment warning because the one-off container has no `/var/www/html/.env`. A second attempt to mount `.env.example` was blocked by the Compose source bind mount before tests started; it did not affect workspace files or the completed PostgreSQL result.

This bounded remediation is explicitly authorized over the current `NEXT.md` pointer; `NEXT.md` and `BLOCKERS.md` remain unchanged. PR #36 remains open against `stage` and unmerged pending this same-branch push, fresh exact-head checks and a new Codex review request.

### PR #36 holistic-review remediation — 2026-09-28

The remediation closes the confirmed observability review gaps: secret redaction covers multiline/key-value/header/query/nested JSON paths; unknown HTTP failures retain their status without guessed retryability and are recorded from the API renderer even when Laravel skips normal reporting; console business-validation and missing-record responses remain understandable while unexpected command failures return a safe reference; MCP tool failures use the incident boundary; provider categories map consistently to API/incident/UI codes; both queue jobs honor bounded provider `Retry-After` delays and retain scheduled fallback backoff. Nginx now supplies request IDs independently of browser input. Incident metadata includes category-derived retryability, impact and recovery action; the Error Center surfaces those fields and keeps framework stack frames collapsed. Forward migration `2026_09_28_000014` adds recovery metadata because diagnostics migration `000013` was already applied in the existing local database. ADR-0022, diagnostics data/API/operations docs and regressions were updated. The work was committed as `9b2f0be` and pushed to the feature branch; the live PR head matched that SHA. `NEXT.md` and `BLOCKERS.md` remain unchanged.

Validation on this working tree: `make test` passed (backend 243 tests / 1743 assertions / 8 PostgreSQL-only skips; frontend 24/24; MCP route-toggle 0 disabled / 20 enabled); focused `DiagnosticsTest` passed (25 tests / 239 assertions); `make lint` passed (Pint 175 files, Larastan 109 files / 0 errors, ESLint and TypeScript); `NODE_ENV=production` frontend build passed. A unique disposable PostgreSQL database migrated through `000014`, then `DiagnosticsPostgresTest` passed under the runtime role (1 test / 7 assertions); the database was dropped after the test. Compose config, Nginx config, OpenAPI YAML and `git diff --check` passed. After reloading the local Nginx container, a read-only request with a spoofed `X-Request-ID` received a generated ID. The default Compose `NODE_ENV=local` build still reproduces the tracked `/_global-error` prerender failure; the production build passes.

PR #36 remains open and unmerged. Exact-head CI and independent review are still required before any readiness claim.

### PR #36 transient provider retry state — 2026-09-28

The Codex P2 on review commit `9b2f0be438` found that Career extraction and vacancy analysis were persisted as `FAILED` during a transient provider failure even while Horizon had scheduled another attempt. Retryable failures now restore the matching operation to `PENDING` before release; a final/nonretryable failure remains `FAILED`. Vacancy reset is scoped to the owner and current snapshot so an older job cannot rewrite newer work. Operations documentation describes the status behavior. `DiagnosticsTest.php` passed with 25 tests / 254 assertions; Pint passed for both queue jobs and the diagnostics feature test.

### PR #36 full observability review remediation — 2026-09-29

Addressed all nine findings from the supplied independent review: Nginx access logs now use a JSON allowlist with $uri and omit request targets, queries and headers; a successful raw diagnostic log marks the same Throwable before a failed database sink can trigger duplicate reporting; stack callable names strip embedded absolute paths while preserving basenames and line numbers; Retry-After is parsed for every retryable provider category and bounded to 24 hours; unexpected application-generation failures remain INTERNAL_ERROR; queued transient attempts emit correlated structured warnings without persistent retry incidents; browser occurrences retain an opaque error reference and finite route while fingerprint cardinality stays fixed; each configured Laravel output channel applies StructuredLogs; diagnostics OpenAPI covers observed 413/422 responses. Added the error_ref occurrence migration and updated ADR-0022, API, data, security, operator and Russian user documentation. NEXT.md and BLOCKERS.md remain unchanged.

Validation on this tree: make test passed (backend 251 tests / 1889 assertions / 8 PostgreSQL-only skips; frontend 25/25; MCP route toggle 0 disabled / 20 enabled). Focused diagnostics/provider retry tests passed (32 tests / 374 assertions); unexpected application-generation regression passed (1 / 11). A disposable PostgreSQL database migrated through 2026_09_29_000015; DiagnosticsPostgresTest passed under the runtime role (1 test / 7 assertions), and the temporary database was dropped. make lint passed (Pint 178 files, PHPStan 111 files / 0 errors, ESLint, TypeScript). Production frontend build, Compose config, Nginx config test, and Redocly OpenAPI validation passed; Redocly reported five style warnings and no schema errors. After recreating local Nginx with the new access format, make test-nginx-access-log-safety passed: the query canary was absent while HTTP status, safe URI path and request ID remained observable. git diff --check passed.

Local validation is distinct from exact-head GitHub checks and independent review state; those are verified against the pushed PR head. PR #36 remains open and unmerged. Nginx error logs and non-Laravel service logs remain outside the Laravel redactor and are documented as a separate operator boundary.

### PR #36 browser error event forwarding — 2026-09-29

A fresh P2 review thread found that the global browser `error` and `unhandledrejection` listeners dropped their event payloads, leaving telemetry deduplication without an opaque reference. The listeners now forward `ErrorEvent.error` and `PromiseRejectionEvent.reason`; Error values use the existing location-derived digest, while non-Error values receive a random opaque occurrence reference without hashing or transmitting their contents. Regression tests verify event forwarding and distinct safe references for non-Error reasons. Validation on this patch: frontend suite 27/27; ESLint, TypeScript, production build and `git diff --check` passed. Exact-head GitHub checks and independent review must be refreshed after the same-branch push. PR #36 remains open and unmerged.

### PR #36 final two P2 review fixes — 2026-09-29

The user explicitly authorized this bounded override of `NEXT.md` on the existing PR branch. Sync queue jobs now snapshot and restore the caller's shared logging context on success or exception, while asynchronous workers clear shared and resolved-channel context between jobs. Error Center retryable alerts retain the exact failed list request, incident detail GET or status PATCH and name the failed action. Regression tests cover real sync HTTP logs, worker job isolation, exception paths, exact frontend retry endpoints, retry success/failure and network errors. `NEXT.md` and `BLOCKERS.md` remain unchanged.

Local validation: focused `DiagnosticsTest` passed (33 tests / 388 assertions); focused frontend diagnostics passed (10/10); `make test` passed (backend 253 tests / 1933 assertions / 8 skips, frontend 30/30, MCP route toggle 0 disabled / 20 enabled). `make lint` passed after applying Pint to the two changed PHP files (Pint 178 files, PHPStan 111 files / 0 errors, ESLint, TypeScript). `NODE_ENV=production` frontend build passed. Exact-head GitHub checks and fresh review remain pending after the same-branch push. PR #36 remains open and unmerged.

### PR #36 audit-atomicity review follow-up — 2026-09-29

The fresh Codex P2 on `f39dfe17` found that diagnostic status could commit before its audit event. A failing regression reproduced `RESOLVED` without an audit record. The controller now locks the incident row and writes status plus audit event inside one database transaction; audit failure rolls both back. A separate PostgreSQL runtime-role regression verifies the same behavior. The `m0-quality` check on `f39dfe17` failed in a vacancy polling UI test whose timer mock also intercepted the test framework wait timer. That failure reproduced locally; the four polling tests now intercept only the application interval and delegate other timers to the real implementation. `NEXT.md` and `BLOCKERS.md` remain unchanged under this explicit PR follow-up.

Validation on this worktree: focused diagnostic tests 34 passed / 398 assertions; disposable PostgreSQL runtime-role diagnostics 2 passed / 12 assertions, including audit rollback, with the temporary database removed; frontend suite 30/30. `make test` passed (backend 255 tests / 1937 assertions / 9 skips, frontend 30/30, MCP route toggle 0 disabled / 20 enabled). `make lint` passed (Pint 178 files, PHPStan 111 files / 0 errors, ESLint, TypeScript); `NODE_ENV=production` frontend build passed. Exact-head CI and fresh review require verification after the same-branch push. PR #36 remains open and unmerged.

### PR #36 autonomous review cycle 1 — 2026-09-29

At head `7881623`, fresh intake found two current P2 findings. Career extraction and vacancy-analysis unique-job locks now cover both maximum 24-hour provider retry delays plus a 10-minute buffer; Error Center list state now accepts only the newest concurrent list response, including its error and loading state. Operator documentation records the queue lock window. Both findings were reproduced by code-path inspection and regression coverage was added. `NEXT.md` and `BLOCKERS.md` remain unchanged.

Validation: focused queue diagnostics passed (2 tests / 39 assertions); focused Error Center frontend passed (12/12); `make test` passed (backend 256 tests / 1941 assertions / 9 PostgreSQL-only skips; frontend 32/32; MCP route toggle 0 disabled / 20 enabled); `make lint` passed (Pint 178 files, PHPStan 111 files / 0 errors, ESLint and TypeScript); production frontend build and Compose config passed; PR contract and `git diff --check` passed. PostgreSQL diagnostics, Nginx config/canary and OpenAPI files were unchanged in this cycle; exact-head CI and review are still required after push. PR #36 remains open and unmerged.

### PR #36 autonomous review cycle 2 — 2026-09-30

The current review threads included stale incident-detail responses; fresh review on `5b9478c` added a pagination finding that paging used draft filters rather than the filters behind the visible list. Detail state now accepts only the latest requested incident, and paging/reload/status refresh use the filters from the latest successful list response. Regressions cover out-of-order detail responses and paging while a draft filter is unsubmitted. `NEXT.md` and `BLOCKERS.md` remain unchanged.

Validation: focused Error Center frontend passed (14/14); `make test` passed (backend 256 tests / 1941 assertions / 9 PostgreSQL-only skips; frontend 34/34; MCP route toggle 0 disabled / 20 enabled); `make lint` passed (Pint 178 files, PHPStan 111 files / 0 errors, ESLint and TypeScript); production frontend build and Compose config passed; PR contract and `git diff --check` passed. This cycle changes no backend/database, Nginx or OpenAPI behavior; PostgreSQL diagnostics, Nginx canary and OpenAPI validation were not rerun. PR #36 remains open and unmerged pending this cycle's push, exact-head checks and fresh review.

### PR #36 autonomous review cycle 3 — 2026-09-30

Fresh Codex review on `1d13d1be` found that multiple status PATCH requests could complete out of order and leave the open incident detail showing a status different from the final server state. Status lifecycle controls now remain disabled for the duration of a mutation, with an imperative guard against duplicate concurrent calls. A regression verifies that a second status change cannot start while the first PATCH is pending and that the returned status remains the one shown in detail. The finding is fixed; the thread reply, resolution, push, exact-head checks and fresh review are pending.

Validation on this change: focused Error Center frontend tests passed (15/15); full frontend suite passed (35/35); ESLint, TypeScript, production build, `bash scripts/check-pr-contract.sh 36` and `git diff --check` passed. No backend, database, OpenAPI, Nginx or runtime logging behavior changed. PR #36 remains open and unmerged.

### PR #36 autonomous review cycle 4 — 2026-09-30

Fresh review on `876c2a493de28aae80fbca2012dfb00c39024633` found two valid issues: free-form credential redaction stopped at comma, semicolon or ampersand and leaked the remaining suffix; PostgreSQL/Redis readiness incidents used `ERROR` despite the documented `CRITICAL` level for unavailable core dependencies. Redaction now consumes an unquoted secret through the next whitespace and consumes bearer credentials the same way. Both readiness probes record `CRITICAL`. Regression coverage covers all three delimiters, bearer values, and both dependency failures.

Local validation: the full `DiagnosticsTest` passed (37 tests / 419 assertions), direct PHP redaction canaries passed (4/4), PHP syntax checks and Pint passed for changed PHP files, and `git diff --check` passed. With the sibling checkout's vendor tree (same `composer.lock`) and an explicit current `APP_BASE_PATH`, the full backend suite ran: 248 passed, 9 PostgreSQL-only skipped, and one unrelated CSRF expectation failed (`AccessCoreTest` returned 201 instead of expected 419). PHPStan could not load because that vendor tree lacks `larastan/larastan/extension.neon`; Docker validation could not run because Docker Desktop returned `Error response from daemon: Docker Desktop is unable to start`. Exact-head remote CI remains required after push. PR #36 remains open and unmerged.

### Error Center UX optimization — 2026-09-29

The current user explicitly authorized a bounded Error Center UX redesign over the `NEXT.md` Preview 0.1 pointer. On `feature/error-center-ux`, the admin UI now has a dedicated `/diagnostics` route, search-first list, compact and advanced filters, deterministic cause and operation labels, priority ordering, decision-first detail, structured correlation, compact occurrences, and collapsed application/framework stack sections. Small list projections and server-side sort/time filters support the UI without changing incident persistence or failure semantics. The UX specification, user/operator guide and diagnostics OpenAPI were updated. `NEXT.md` and `BLOCKERS.md` are unchanged.

Local validation: frontend 29/29 tests, ESLint, TypeScript and production build passed; backend `DiagnosticsTest.php` passed with 26 non-failing disposable-container `.env` warnings and 261 assertions before the final list-projection assertions, then the two affected list tests passed with 17 assertions; Pint and PHPStan passed with zero errors; Redocly found a valid diagnostics OpenAPI document with five existing style warnings; `git diff --check` passed. Browser checks with synthetic incidents covered deterministic provider, rate-limit, malformed-output and unavailable-diagnostics states, plus desktop/tablet/mobile layout, collapsed stack and no horizontal page overflow. Desktop/mobile screenshots are under `docs/07-Design/evidence/`. Figma has a draft Error Center page, but its Starter-plan call limit prevented final canvas review. PR #36 remains an unmerged prerequisite; this branch is prepared as a separate stacked PR to `stage` and must not be merged before its base feature.

### Error Center UX independent-review remediation — 2026-09-29

The current user explicitly authorized this bounded remediation on `feature/error-center-ux` over the Preview 0.1 `NEXT.md` pointer. The eleven reported UX findings are addressed: exact provider filtering; action-specific list/detail/status retry; bounded occurrence `retry_after_seconds`; pending status lock and no-op audit suppression; combined search/filter empty state; honest browser event labels and unknown-cause copy; contextual detail loading and guarded heading focus; live result/status announcements; mobile occurrence date/timezone; scrollable mobile Quick/Advanced filters with sticky actions; and the documented 32 px H1. The diagnostics API, user/operator guide, UX specification and synthetic screenshots were updated. `NEXT.md` and `BLOCKERS.md` remain unchanged. Figma visual verification pending due tool/plan limitation.

Validation on this remediation tree: frontend 39/39 tests, ESLint, TypeScript and production build passed; focused SQLite diagnostics tests passed with 303 assertions and 30 non-failing missing-container-`.env` warnings; provider-header normalization tests passed with 20 assertions and two of the same environment warnings; Pint passed across 176 files; Larastan reported no errors. A separate PostgreSQL/Redis Compose project with distinct admin/runtime credentials migrated through `000015`, ran two diagnostics runtime-role tests with 15 assertions, rolled back only `000015`, reapplied it and passed the same two tests again. The runtime role was confirmed neither superuser nor BYPASSRLS. Redocly validated the diagnostics OpenAPI with five existing style warnings; `git diff --check` passed. A synthetic production-build browser run checked list, filters, detail, exact retry and status actions at 1440/1280/820/390 px, including heading focus, 32 px H1 and no horizontal overflow. Test infrastructure was removed after the run. This is local validation; exact-head CI and fresh independent UX review remain separate gates. PR #36 is still open and remains a merge prerequisite.

### PR #37 final UX concurrency and accessibility remediation — 2026-09-29

The current user authorized this bounded follow-up on `feature/error-center-ux` as an override of the Preview 0.1 `NEXT.md` pointer. List and detail requests now use monotonic generations so older responses, errors and loading completion cannot replace a newer request. Returning from detail invalidates its request; keyboard activation restores focus to the originating row after reload or to the list heading if the row is absent, while mouse return does not force focus. A late status PATCH cannot reopen stale detail, and a successful PATCH after returning refreshes the current list. Detail occurrences now order by `created_at DESC, id DESC`, matching list projections, provider filtering and occurrence retention. ADR-0022 browser fingerprint/privacy semantics are unchanged; its separate review thread remains open. The Error Center UX specification records keyboard return behavior. `NEXT.md` and `BLOCKERS.md` remain unchanged.

Local validation on this tree: frontend 52/52 tests, ESLint without warnings, TypeScript and production build passed; focused SQLite diagnostics 31 tests / 318 assertions passed; isolated PostgreSQL runtime-role diagnostics 3 tests / 27 assertions passed after admin migrations through `000015`, with the runtime role confirmed neither superuser nor BYPASSRLS; Pint passed across 176 files; Larastan reported no errors. The new regression tests cover overlapping reload/sort/filter/pagination, superseded success/error responses, detail selection/return, keyboard focus with row and fallback, mouse return, late status mutation, and identical-timestamp latest occurrence metadata. No OpenAPI validation was needed because the API contract did not change. Exact-head CI and fresh review are pending the PR update.

### PR #37 fresh Codex review follow-up — 2026-09-29

The current user authorized fixing the two new actionable P2 comments on `feature/error-center-ux`, overriding the Preview 0.1 `NEXT.md` pointer for this bounded block. The OpenAI Responses adapter now accepts a bounded numeric `Retry-After` for all retryable provider HTTP categories (429, 408, 425 and 5xx), so both queue jobs can honor the provider's delay; terminal categories ignore it. A validated temporary-provider delay is retained in the occurrence and shown in Error Center. Advanced filter input limits now match diagnostics API validation for provider, service, environment, error code and correlation IDs. User and operator guidance was aligned; the API schema and ADR-0022 did not change. The older browser-signature architecture thread remains open pending a separate decision. `NEXT.md` and `BLOCKERS.md` remain unchanged.

Regression tests first reproduced both findings. Validation on the corrected tree: frontend diagnostics 37/37 and full frontend 54/54 tests, ESLint without warnings, TypeScript and production build passed; focused OpenAI-provider and SQLite diagnostics 34 tests / 358 assertions passed; isolated PostgreSQL runtime-role diagnostics 3 tests / 29 assertions passed; Pint passed across 176 files; Larastan analyzed 109 files with zero errors. Exact-head CI and fresh review remain pending the PR update.

### PR #37 retry-lock, readiness severity and failed-detail focus — 2026-09-29

The current user authorized this bounded follow-up on `feature/error-center-ux`, overriding the Preview 0.1 `NEXT.md` pointer. All four current actionable Codex findings were validated and fixed: unique analysis/extraction locks now cover the maximum two provider retry delays plus a ten-minute buffer; stale `RUNNING` recovery remains tied to queue/worker timeouts; PostgreSQL/Redis readiness outages record `CRITICAL`; and keyboard users move to the detail-load alert on failure without forced mouse focus changes. Regression coverage was added and the UX/operations guidance was updated. ADR-0022 semantics and diagnostics API contract remain unchanged. Figma visual verification unavailable; synthetic committed screenshots were visually inspected at 1440, 1280, 820 and 390 px. `NEXT.md` and `BLOCKERS.md` remain unchanged.

Validation: `make test` passed (backend 256 tests / 1864 assertions / 10 PostgreSQL-only skips; frontend 54/54; MCP toggle disabled 0/enabled 20). `make lint` passed (Pint 176 files, PHPStan 109 files / 0 errors, ESLint and TypeScript); production frontend build, PR contract and `git diff --check` passed. A fresh isolated PostgreSQL database migrated through `000015`; `DiagnosticsPostgresTest` passed under the runtime role (3 tests / 29 assertions); only the task-created Compose project and test database were removed afterward. The focused first lint attempt identified a Pint formatting issue, which was corrected before the successful lint run. Fresh Codex review and CI for the next pushed head remain pending. PR #36 remains open and unmerged as a prerequisite.

### PR #37 selectable log-channel redaction remediation — 2026-09-30

The current user authorized this bounded review follow-up on `feature/error-center-ux`. A valid P2 found that selectable Laravel log channels outside single/daily/stderr could bypass `StructuredLogs`. Added the redaction tap to every selectable non-null channel and regression coverage for direct channels and configured stack members; operator logging guidance now states this invariant. `NEXT.md` and `BLOCKERS.md` remain unchanged. The previous reviewer request for `98ad644` is still pending; the new head will need its own fresh request and review.

Validation: focused channel-redaction regression passed (1 test / 10 assertions); `make test` passed (backend 257 tests / 1874 assertions / 10 PostgreSQL-only skips; frontend 54/54; MCP toggle disabled 0/enabled 20); `make lint` passed (Pint 176 files, PHPStan 109 files / 0 errors, ESLint and TypeScript). `git diff --check` passed. PR #36 remains open and unmerged as a prerequisite.

### PR #37 temporary-provider Retry-After API remediation — 2026-09-30

A Codex review for prior head `98ad644562` arrived after the subsequent log-channel push and reported that synchronous application-generation responses dropped bounded `Retry-After` values for retryable temporary provider failures. The finding remained applicable at `e89033b`: response headers were limited to `RATE_LIMITED`, although `TRANSPORT` and `TEMPORARY_UNAVAILABLE` are also retryable. `ErrorCatalog::responseHeaders()` now follows the catalog's category-derived retryability and retains the existing numeric 0–86400 bound. Regression coverage exercises the temporary API response and every provider category, including the absence of headers for terminal categories. No persistence or OpenAPI contract changed. `NEXT.md` and `BLOCKERS.md` remain unchanged.

Validation: focused `DiagnosticsTest.php` passed (35 tests / 362 assertions; test container emitted non-failing missing-`.env` warnings); `make test` passed (backend 257 tests / 1884 assertions / 10 PostgreSQL-only skips; frontend 54/54; MCP toggle disabled 0/enabled 20); `make lint` passed (Pint 176 files, PHPStan 109 files / 0 errors, ESLint and TypeScript); production frontend build passed. One initial build attempt reported a concurrent-build lock; no active build process was present and the immediate rerun passed. The PostgreSQL runtime-role suite was not rerun because this response-header-only change does not touch persistence, schema, or RLS; the preceding bounded tree had passed diagnostics PostgreSQL tests (3 tests / 29 assertions). Fresh exact-head CI and Codex review remain pending the push.

### PR #37 review remediation cycle 4/5 — 2026-09-30

At head `51d6b2648acbcddb334ce990321bf6e513c7b3e0`, live review intake confirmed three current actionable P2 findings: Laravel emergency fallback logging bypassed redaction; stale vacancy analysis could be redispatched while its unique lock remained; and status changes could leave the Error Center on an out-of-range page. All three are fixed with focused regression coverage. The fallback emergency logger now applies `StructuredLogs`; stale recovery releases only the corresponding `AnalyzeVacancy` unique lock before dispatch; list loading retries the last valid page when the requested page no longer exists. Browser-signature is resolved/outdated and is not an active blocker. `NEXT.md` and `BLOCKERS.md` remain unchanged under this bounded user override.

Local validation on the new working tree: backend 248 passed / 1890 assertions / 10 PostgreSQL-only skips; frontend 55/55; Pint, PHPStan (0 errors), ESLint, TypeScript and production build passed. Backend tests used the lockfile dependencies, local PHP 8.5.3, `APP_ENV=local` and an ephemeral test-only APP_KEY; PostgreSQL runtime-role validation was not available because Docker Desktop did not start. This is cycle 4 of the original maximum 5; one remediation cycle remains. Fresh exact-head CI and Codex review are pending the push. PR #36 remains open and unmerged; neither PR #36 nor #37 was merged.

### PR #37 review remediation cycle 5/5 — 2026-09-30

Fresh Codex review of exact head `34e39a31efadb758423cf49be1e8dce9efb97af9` found one current actionable P2: duplicate-import recovery of a stale `RUNNING` vacancy reset it to `PENDING` but left the corresponding `AnalyzeVacancy` unique lock in place, suppressing the replacement dispatch. The duplicate-import branch now releases only the matching owner/snapshot job lock before its after-commit dispatch. Its regression first acquires an orphaned lock, then verifies one replacement job is queued and the vacancy is pending. All other review threads were already resolved or outdated; the ADR-0022 browser-signature boundary remains unchanged. `NEXT.md` and `BLOCKERS.md` remain unchanged under this bounded user override.

Local validation: focused duplicate-import recovery passed (1 test / 5 assertions); full backend suite passed (248 tests / 1,892 assertions, 10 PostgreSQL-only skips); Pint and PHPStan passed (0 errors); `git diff --check` passed. PostgreSQL runtime-role validation was unavailable because Docker Desktop did not start during cycle 4; this change only alters queue-lock recovery, and exact-head CI remains the remote gate. The original maximum of five remediation cycles is reached. Awaiting push, exact-head CI and a fresh Codex review; PR #36 remains open and unmerged, and neither PR #36 nor #37 was merged.

### PR #37 review remediation cycle 6/10 — 2026-09-30

The user extended the original five-cycle cap by five without resetting completed cycles. Fresh review of `c85f9021896de2cf65fec753f50bba8c5bf43dfa` found an actionable P2 in exception deduplication: when stderr logging succeeded but diagnostic PostgreSQL storage failed, `IncidentRecorder` returned success before adding the exception to its `WeakMap`, so the API renderer logged the same exception again. A new API-level regression reproduced two `diagnostics.incident` events before the fix; recorded-exception bookkeeping now runs after both sink attempts whenever either sink succeeded. The finding is fixed in this cycle; ADR-0022 and browser telemetry semantics remain unchanged. `NEXT.md` and `BLOCKERS.md` remain unchanged under the bounded user override.

Focused validation passed after the fix: three diagnostics regressions / 15 assertions, including sink outage deduplication, both-sink failure preserving the primary exception, and synchronous queue failure recorded once. Full backend validation passed (249 tests / 1,896 assertions, 10 PostgreSQL-only skips); Pint, PHPStan (0 errors), `git diff --check` and PR contract passed. Exact-head CI and fresh review for this cycle's push remain pending. Cycle count is 6/10; PR #36 and #37 remain open and unmerged.

### PR #37 review remediation cycle 7/10 — 2026-09-30

Fresh Codex review of `c3166ec9e0de61d909bb32496756fc30f2cd351e` found that queued retryable provider failures in `CareerExtractionService` and `VacancyAnalysisService` were classified by reading `job_id` from shared log context. If `Queue::before` could not establish that context, both services persisted an incident per retry in addition to the final `Queue::failing` incident. Added a dedicated nesting-aware `QueueExecutionContext`, set at `JobProcessing` and cleared after successful processing or `JobExceptionOccurred`; both services now use it to suppress per-attempt incidents independently of logger availability. Regression coverage exercises both services when attaching logger context fails and verifies queue state survives setup failure and clears after exception. ADR-0022 and browser telemetry semantics remain unchanged; `NEXT.md` and `BLOCKERS.md` remain unchanged under the bounded override.

Local validation passed: focused service/lifecycle regressions (3 tests / 8 assertions); full backend suite (252 passed / 1,904 assertions / 10 PostgreSQL-only skips); Pint; PHPStan (0 errors); `git diff --check`; and PR contract. Exact-head CI and fresh review for this cycle's push remain pending. Cycle count is 7/10; PR #36 and #37 remain open and unmerged.

### PR #37 review remediation cycle 8/10 — 2026-09-30

Fresh Codex review of `2940efcc1e33e7b80b5dc2c5ace13aeeefc2655e` found two current actionable P2 findings. Synchronous and nested queue callbacks now save and restore the previous shared log context, preserving the originating request and parent job context. Unexpected application-draft generation failures now record `LLM_PROVIDER_FAILED`, matching the stable code already returned by the API. Regressions cover nested sync context restoration and a post-provider draft-item persistence failure. ADR-0022 and browser telemetry semantics remain unchanged; `NEXT.md` and `BLOCKERS.md` remain unchanged under the bounded user override.

Focused regressions passed (3 tests / 13 assertions); the full backend suite passed (254 passed / 1,915 assertions / 10 PostgreSQL-only skips) with `APP_ENV=local` and an ephemeral test-only `APP_KEY`. Pint and PHPStan passed (0 errors); `git diff --check` passed. The initial full-suite run without `APP_KEY` produced environment-only encryption/HTTP failures and was superseded by the passing configured run. Exact-head CI and fresh review for this cycle's push remain pending. Cycle count is 8/10; do not merge PR #37.

### PR #37 review remediation cycle 9/15 — 2026-09-30

Fresh Codex review of `207fc2687e0d5b79ae0acd9bcd7e549e08ecbb9c` found one current actionable P2: a retryable queue exception could be reported as a synthetic console incident after Laravel emitted `JobExceptionOccurred` and the active queue context had already been cleared. Queue execution now retains weak exception-identity markers independently of logger context; the global console reporter suppresses queue-origin failures while preserving normal console reporting and the final queue-failure policy. Regression reproduces failed queue log-context setup, exception-context cleanup, then Laravel's `report()` call. ADR-0022 and browser telemetry semantics remain unchanged; `NEXT.md` and `BLOCKERS.md` remain unchanged under the bounded user override.

Focused diagnostics regressions passed (3 tests / 12 assertions); the full backend suite passed (255 passed / 1,921 assertions / 10 PostgreSQL-only skips) with `APP_ENV=local` and an ephemeral test-only `APP_KEY`. Pint and PHPStan passed (0 errors); `git diff --check` passed. The user extended the total remediation cap to 15 without resetting completed cycles. Exact-head CI and fresh review for this cycle's push remain pending. Live GitHub reports PR #36 already merged before this cycle; no merge was performed, and PR #37 remains open.

### PR #37 review remediation cycle 10/15 — 2026-09-30

Fresh Codex review of `d6312d5e49e49a5df1476bd4bce4f8a6de62922d` found two current actionable P2 findings. Unexpected 5xx failures reported from `/oauth/*`, `/mcp/v1` and OAuth discovery routes now enter the same incident boundary as API failures; expected OAuth protocol/authentication rejections below 500 remain excluded. The `diagnostics:failed-jobs` command now catches query and table-rendering failures, records a safe CLI reference and returns status 1 without printing database details. Documentation covers both behaviors. ADR-0022 and browser telemetry semantics remain unchanged; `NEXT.md` and `BLOCKERS.md` remain unchanged under the bounded user override.

Focused OAuth/CLI regressions passed (3 tests / 15 assertions), and the existing MCP authentication regression passed (1 test / 20 assertions). Full backend suite passed (258 passed / 1,936 assertions / 10 PostgreSQL-only skips) with `APP_ENV=local` and an ephemeral test-only `APP_KEY`; Pint and PHPStan passed (0 errors); `git diff --check` passed. Exact-head CI and fresh review for this cycle's push remain pending. The total remediation cap remains 15. Live GitHub reports PR #36 already merged before this cycle; no merge was performed, and PR #37 remains open.

### PR #37 review remediation cycle 11/20 — 2026-09-30

Fresh Codex review of `159365622855f8d62ed9fe1263b940762dc87b1b` found one current actionable P2: synchronous application-draft generation discarded a validated provider `Retry-After` and immediately re-enabled generation. The frontend API client now retains only a retryable numeric delay from 0 to 86400 seconds, includes a positive delay in safe recovery copy, and the generation panel disables another attempt until that delay expires with an accessible wait status. The delay is displayed in readable hours/minutes/seconds. ADR-0022 and browser telemetry semantics remain unchanged; `NEXT.md` and `BLOCKERS.md` remain unchanged under the bounded user override. The user extended the original cap by five more cycles, so the total maximum is now 20 with prior cycles preserved.

Focused diagnostics regressions passed (40/40); full frontend Vitest passed (57/57), ESLint, TypeScript and production build passed. Exact-head CI and fresh review remain pending after push. Live GitHub reports PR #36 merged and PR #37 open; no PR was merged.

### PR #37 review remediation cycle 12/20 — 2026-09-30

Fresh Codex review of `99cd47143f7e24763f384c1c50eb18d00c5da6d1` found one current actionable P2: a positive provider `Retry-After` after draft edit, accept or approve did not block another provider-backed action. The same bounded cooldown now disables generation, edit, accept and approve while leaving provider-free Reject available. Added per-action regressions and updated the UX spec. ADR-0022 and browser telemetry semantics remain unchanged; `NEXT.md` and `BLOCKERS.md` remain unchanged under the bounded user override.

Cycle 12 validation passed: focused diagnostics tests 45/45, full frontend Vitest 62/62, ESLint, TypeScript typecheck, production build, and `git diff --check`. The fix was committed/pushed as `0ee0b6f47b83ca64b96308d4d573667b80a1d718`; its thread was answered and resolved. Fresh exact-head review on that SHA produced cycle 13 findings. The total remediation cap remains 20, including the original cycles; PR #36 is merged on GitHub, PR #37 remains open, and neither PR was merged by this work.

### PR #37 review remediation cycle 13/20 — 2026-09-30

Fresh exact-head review of `0ee0b6f47b83ca64b96308d4d573667b80a1d718` found two actionable P2 findings: draft provider cooldown could be bypassed by remount/reload, and anonymous PHP class names could leak absolute paths through safe stack frames. The cooldown persists only its bounded expiry timestamp in browser storage and reloads it before enabling provider-backed actions. `Redactor::stack()` truncates anonymous class identifiers at the NUL separator before rendering. Added UI remount/vacancy-switch and real anonymous-class regressions. Validation: full frontend Vitest 63/63, ESLint, TypeScript, production build; focused backend PHPUnit 1/1 (3 assertions), full backend PHPUnit 258 passed with one unrelated existing AccessCore CSRF failure and 10 PostgreSQL-only skips using an ephemeral process-only APP_KEY; Pint and full PHPStan passed. Code and validation are committed as `078f5c7b212b9c8c0811e6ed03742d45dfad5948` and pushed. Both fixed threads were answered and resolved. Fresh review on `56c6934fde64d0d97dc2daeb3d34cae89dbbd114` found one new P2 on thrown anonymous exception class names; it is handled in cycle 14 below.

### PR #37 review remediation cycle 14/20 — 2026-09-30

Fresh exact-head review of `56c6934fde64d0d97dc2daeb3d34cae89dbbd114` found that a thrown anonymous exception still exposed its absolute defining path and NUL byte in the stack origin and persisted `exception_class`. A shared safe exception-class formatter now strips the PHP anonymous-class suffix at all diagnostic boundaries: safe stack, structured log message/context, incident fingerprint and database row. Regression coverage records an actual anonymous Throwable and verifies the log, stack and incident contain neither a path nor NUL. During reconciliation with `stage` at `58aa91982533d57f2e9de1cda13debeb013a34fb`, queue context cleanup now independently clears resolved channels and shared context, Retry-After parsing retains a single bounded parse, and legacy generation/occurrence regressions match the established provider-failure and exception-deduplication behavior.

Cycle 14 validation passed: focused backend 5 tests / 55 assertions; full backend PHPUnit 285 passed / 2,149 assertions / 11 PostgreSQL-only skips with process-only test `APP_KEY`; Pint; PHPStan (0 errors); frontend Vitest 66/66, ESLint, TypeScript and production build; `git diff --check`. Stage reconciliation was pushed as `2d4a79a79286badd5a6204521787ecb2ef1ce415`; the cycle 14 finding thread was answered and resolved after exact-head verification. Fresh Codex review and exact-head CI remain pending. PR #37 remains open and unmerged.

### PR #37 review remediation cycle 15/20 — 2026-09-30

Fresh Codex review of `a439c6fd3fd7d6fdc409e7e77e921ba1348e2600` found three actionable P2s. Unexpected local failures after draft generation again remain `INTERNAL_ERROR` in both API and incident storage, as ADR-0022 requires; provider-origin errors keep provider categories. Occurrence details now show and copy the persisted opaque browser `error_ref`. The bounded Retry-After expiry now listens for cross-tab `storage` updates and unlocks mounted panels when the shared deadline is removed. Added regressions for classification alignment, visible/copyable browser references, and cross-tab cooldown start/clear; updated the Error Center UX and operator guide.

Cycle 15 validation passed: focused backend 2 tests / 18 assertions; full backend PHPUnit 285 passed / 2,149 assertions / 11 PostgreSQL-only skips with process-only test `APP_KEY`; Pint; PHPStan (0 errors); focused frontend diagnostics 48/48 and full Vitest 68/68, ESLint, TypeScript and production build; `git diff --check`. Code was committed and pushed as `c7a9c85c87fe0cf817aaf1f6cbe81350f487c8ad`; the PR contract passed and all three fixed threads were answered and resolved after exact-head verification. A fresh Codex review and `m0-quality` completion are pending. PR #37 remains open and unmerged.

### PR #37 review remediation cycle 16/20 — 2026-10-01

Fresh exact-head Codex review of `6e3803df9f85d5a36952ec535c7a1064daf3d992` found two actionable P2 findings. Provider cooldown writes and cross-tab storage events now retain the longest active deadline across local storage, mounted panel state, late storage events and remount recovery; later retry responses can extend the deadline, expired values are removed, and non-retryable failures do not start cooldown. Career and Vacancy jobs now persist private `next_attempt_at` and bounded `dispatch_recovery_at` metadata. Row-locked recovery preserves genuine delayed retries, selectively releases only the matching Laravel `ShouldBeUnique` lock once the persisted recovery window expires, and serializes concurrent recovery attempts. Career now also recovers stale `RUNNING` work while leaving fresh work alone. Legacy pending rows retain the historical lock window because their original retry deadline was not persisted. The two nullable fields are hidden from serialization and cleared on claim or terminal completion. Documentation and a PostgreSQL concurrency harness were updated; ADR-0022 and public API schemas are unchanged. `NEXT.md` and `BLOCKERS.md` remain unchanged under the explicit PR follow-up.

Cycle 16 code was committed as `9e50aaa56d5eb1eb097cd1244c2d9f7be94ec845` (`fix(queue): preserve retry deadlines and recover orphan jobs`) and pushed to PR #37. Validation: full frontend Vitest 70/70, ESLint, TypeScript and production build passed; focused Career/Vacancy PHPUnit files passed (128 tests / 803 assertions / 1 skip); full backend suite excluding the unrelated existing CSRF expectation passed (288 tests / 2,187 assertions / 11 PostgreSQL-only skips). The unfiltered backend suite had one unrelated `AccessCoreTest::test_http_registration_rejects_missing_csrf_before_any_user_is_created` failure (expected 419, received 201). Pint and PHPStan passed (0 errors); worker PHP syntax, concurrency script shell syntax, PR contract and `git diff --check` passed. The PostgreSQL concurrency harness could not run because Docker Desktop reported `unable to start`; its races are not runtime-verified in this environment. PR #37 remains open against `stage` and mergeable. Both cycle 16 threads were answered with root cause, fix, regressions, validation and SHA, then resolved after exact-head verification. Exact-head GitHub checks are running; fresh review will follow the final state commit.

### PR #37 recovery harness seed follow-up — 2026-10-01

The PostgreSQL runtime harness now clears only the matching `ShouldBeUnique` lock left by the preceding fake-queue analysis before seeding that exact job as an orphan. This prevents the earlier test fixture from accidentally occupying the key needed by the recovery scenario. Both Vacancy and Career orphan recovery races completed with exactly one dispatch and a no-op competitor; the canonical `bash scripts/test-vacancy-postgres-concurrency.sh` passed. Focused Career/Vacancy PHPUnit passed (128 tests / 803 assertions / 1 PostgreSQL-only skip); Pint passed (183 files); PHPStan passed (0 errors); worker PHP syntax and harness shell syntax passed. The CSRF registration test passed on the current PR tree with both SQLite and an isolated PostgreSQL database (3 assertions). Fresh review on `a2a1fe48` found a legacy retry deadline edge case; it is recorded with its regression and fix below. PR #37 remains open.

### PR #37 legacy retry window review remediation — 2026-10-01

Fresh exact-head review found that legacy `PENDING` rows with null persisted deadlines used the original creation timestamp even when the old retry path had refreshed `updated_at`. That could release a still-valid long-lived unique lock and redispatch during a provider delay. Legacy recovery now chooses the newest available timestamp across the operation creation/update and the caller's snapshot timestamp. Career and Vacancy regressions keep old-created/recently-retried jobs protected, then verify one recovery after the legacy lock window. Focused Career/Vacancy PHPUnit passed (129 tests / 807 assertions / 1 PostgreSQL-only skip); the PostgreSQL concurrency harness passed; Pint (183 files), PHPStan (0 errors), and PHP syntax checks passed. PR #37 is open pending push, exact-head checks, fresh review and merge gate.

### PR #37 CI retry synchronization — 2026-10-01

Exact-head CI for `03cf9a2640237115ff0462c646fa9cddf43fbccd` exposed a timing race in the Preview access-shell test: the provider-action button is intentionally disabled until its persisted cooldown state is loaded asynchronously, while the test asserted enabled immediately after locating it. The test now waits for the existing enabled condition before clicking; product behavior is unchanged. Full frontend Vitest passed (70/70), ESLint for the changed test, TypeScript typecheck and production build passed. `git fetch origin --prune` now completes cleanly after correcting ownership of the affected remote-tracking reflogs. Fresh exact-head CI and Codex review remain pending for this test adjustment; PR #37 remains open.

### PR #37 retry claim review remediation — 2026-10-01

Fresh exact-head review of `5fd0ac62c4cadf4ed4bba1e1d181f6451b9d6d79` found that an already queued duplicate Career or Vacancy job could claim `PENDING` work before its persisted `next_attempt_at`, bypassing provider retry delay. Both atomic claim updates now require the deadline to be null or due. Regressions exercise duplicate worker claims before the deadline and assert the operation remains pending, the deadline remains persisted, and no LLM run/provider call starts. The existing orphan-recovery test now advances its frozen clock to the newly reserved immediate retry deadline before synchronous execution.

Validation: Career/Vacancy feature files passed (131 tests / 815 assertions / 1 PostgreSQL-only skip); PostgreSQL concurrency harness passed (import, reanalysis and single-dispatch Career/Vacancy orphan recovery races); Pint and PHPStan passed (0 errors). Exact-head CI and fresh review remain pending after push. PR #37 remains open.

### PR #37 retry transition race follow-up — 2026-10-01

The next exact-head review of `2cd16482efbf0deaf06a8317e5a0fdc680f46c6d` found a race before the retry deadline was stored: a retryable provider exception first changed Career/Vacancy to `FAILED`, allowing a concurrent duplicate submission to release its unique lock before the queue job persisted `Retry-After`. During retryable queue execution, the services now keep the operation `RUNNING` and refresh its recovery timestamp; the queue jobs atomically change `RUNNING` to `PENDING` with the persisted deadline, or mark terminal failures `FAILED`. Regressions verify duplicate Career/Vacancy submissions do not dispatch during that transition, and the retry fixtures exercise queue recovery from `RUNNING`.

Validation: Career/Vacancy feature files passed (131 tests / 819 assertions / 1 PostgreSQL-only skip); PostgreSQL concurrency harness passed (import, reanalysis and single-dispatch Career/Vacancy orphan recovery races); Pint and PHPStan passed (0 errors). Exact-head CI and fresh review remain pending after push. PR #37 remains open.

### PR #37 early duplicate Vacancy delivery — 2026-10-01

Fresh exact-head review found that an early duplicate `AnalyzeVacancy` rejected by the persisted retry deadline could propagate `SafeVacancyException` into Laravel's queue retry/failure path. The job now performs a fresh owner-scoped aggregate read and treats the exception as a no-op only while the aggregate is still `PENDING` with a future `next_attempt_at`; other safe exceptions still propagate. The regression verifies no provider/LLM run, no job failure/release, preserved retry and recovery deadlines, no diagnostic incident, and no replacement dispatch. `ExtractCareerSource` has no corresponding claim-rejection path.

Validation: new regression passed (1 test / 9 assertions); VacancyCoreTest (112 passed / 1 PostgreSQL-only skip); CareerCoreTest (20 passed); PostgreSQL concurrency harness passed; Pint and PHPStan passed (0 errors); changed-file PHP syntax, frontend Vitest (70/70), ESLint, TypeScript, production build, PR contract and `git diff --check` passed. Full backend suite: 281 passed / 1 unrelated Access Core CSRF expectation failure / 11 PostgreSQL-only skips with a temporary test-only APP_KEY. Exact-head CI and fresh review remain pending after push; PR #37 remains open.

The first exact-head CI run exposed a frontend test timing race: cooldown tests clicked the provider action before its asynchronous persisted-state bootstrap enabled it, and the remount test read its status before that bootstrap rendered. Tests now await the enabled state before clicks and the expected status after remount; product behavior is unchanged. The diagnostics test file passed (50/50), full frontend passed (70/70), ESLint, TypeScript and production build passed. CI and exact-head review are pending for the follow-up test commit.

Fresh review of `8768d20d11ae3412489dbb28aa40deb7b184f438` found a concurrent duplicate Vacancy claim case: after one job changes the current aggregate to `RUNNING`, another job's rejected claim could be reported as a queue failure. The job now checks the aggregate under `lockForUpdate()` and treats `RUNNING` as a no-op only when the delivered snapshot is still the latest; the same locked check preserves the future-`PENDING` retry case. Regression also verifies a `SafeVacancyException` for other aggregate states still propagates. Validation: duplicate-job regressions (3/3, 20 assertions); VacancyCoreTest (114 passed / 1 PostgreSQL-only skip); CareerCoreTest (20 passed); PostgreSQL concurrency harness, Pint, PHPStan, PHP syntax and `git diff --check` passed. CI and a fresh exact-head review remain pending after push.

### PR #37 stale queue-attempt fencing — 2026-10-01

Fresh exact-head review of `deb2ba8351d1876c0bfbe3a70351a76a932ddc1d` found that stale `RUNNING` recovery released the unique queue lock without revoking the original execution. Vacancy and Career aggregates now store a private active-run token. Recovery clears the old token transactionally; claims install a new token; provider results, matching persistence, terminal transitions and retry transitions verify ownership before writing. Superseded jobs finish as no-ops and cannot overwrite the replacement attempt. Career was audited and fenced because its stale recovery had the same race. Tests execute both real queue Jobs, replace ownership during provider execution, and verify no stale result, queue failure or diagnostic incident is persisted.

Validation: VacancyCoreTest passed (115 tests / 689 assertions / 1 PostgreSQL-only skip); CareerCoreTest passed (21 / 171); DiagnosticsTest passed (57 / 575); PostgreSQL concurrency harness passed; Pint, PHPStan (0 errors), changed-file PHP syntax and `git diff --check` passed. Full backend PHPUnit ran 297 tests: 285 passed, 11 PostgreSQL-only skips, and the known local AccessCore CSRF baseline failed (expected 419, received 201); exact-head CI is the remote gate. Pending bounded commit/push, exact-head CI and fresh Codex review; PR #37 remains open.

### PR #37 queued terminal provider finalization — 2026-10-01

Fresh exact-head review of `74b112baaf71e904c89e6e207871288b8be5da25` found a terminal-provider race: a queued failure could expose `FAILED` before the Job finalized, allowing duplicate submission to revoke ownership and dispatch another provider call. Both services now keep queued provider failures `RUNNING` and token-owned until the Job atomically writes retry `PENDING` or terminal `FAILED`; synchronous provider failures retain their existing immediate terminal transition. Vacancy and Career regressions re-submit the same aggregate from the failure logging callback, proving no replacement dispatch occurs and the original Job still finalizes its terminal failure.

Validation: both terminal regressions passed (2 tests / 14 assertions); VacancyCoreTest passed (116 / 696 / 1 PostgreSQL-only skip); CareerCoreTest passed (22 / 178); DiagnosticsTest passed (57 / 575); PostgreSQL concurrency harness passed; Pint and PHPStan (0 errors) passed. Full backend PHPUnit: 287 passed / 11 PostgreSQL-only skips and the known local AccessCore CSRF baseline failed (419 expected, got 201). This finding is committed/pushed pending exact-head CI and fresh review; PR #37 remains open.

### PR #37 stored Retry-After deadline UX — 2026-10-01

Fresh exact-head review of `4306420f5ecf041282c5c0f099af20e564a70c02` found one actionable P2: Error Center guidance restarted the full persisted `retry_after_seconds` interval when an incident was viewed later. The frontend now derives the remaining delay from `occurrence.created_at + retry_after_seconds`, reports the retry as available after expiry, and covers recent, partially elapsed and expired delays with regressions. `NEXT.md` and `BLOCKERS.md` remain unchanged under the explicit PR follow-up.

Validation: focused Error Center Vitest passed (52/52); ESLint and TypeScript typecheck passed; `git diff --check` passed. Pending bounded commit/push, exact-head checks, thread reply/resolve and fresh Codex review; PR #37 remains open.

### PR #37 retry guidance expiry refresh — 2026-10-03

Fresh exact-head Codex review of `41592b509ec99ae80ed11f40e078b6fffe915766` found one actionable P2: an open Error Center detail kept stale retry guidance after the persisted provider deadline elapsed. The frontend now schedules a bounded rerender at the absolute retry deadline; missing or invalid timestamps remain without invented guidance. Added a regression that advances the clock while the detail remains open and verifies the transition from a one-second wait to `Retry is available now.`

Validation: the regression first reproduced the stale guidance and failed; after the fix, the full diagnostics Vitest passed (53/53), ESLint, TypeScript typecheck, production build and `git diff --check` passed. Pending bounded commit/push, exact-head checks, thread reply/resolve and one fresh Codex review; PR #37 remains open.

### Local startup documentation — 2026-10-03

The local launch runbook now requires `make init`, Compose configuration validation, `make up`, `make migrate`, and an explicit loopback health check. It documents the observed PostgreSQL database-name mismatch (`POSTGRES_DB` pointing at a database absent from the existing named volume), explains that the failure occurs before Laravel migrations, and gives a non-destructive recovery path without removing persistent volumes. The README quick start links to the runbook and includes the migration step.

Validation: current Compose runtime was inspected; `docker compose ps` reported healthy services; the PostgreSQL database list was queried read-only; and the documented `docker compose --env-file .env config --quiet` / health-check commands remain the required launch checks. No database, volume, migration or application state was modified by this documentation task.

### PR #38 startup readiness documentation remediation — 2026-10-03

Fixed both current P2 review findings in the existing documentation PR: post-migration verification now uses the dependency-aware `/api/v1/health/ready` probe, and the health-check command reads only `CVORTEX_PORT` from `.env` without executing or exposing the file. The documented browser step now uses the configured `APP_URL`. No runtime code, Compose configuration, database, volume or `stage` state changed.

Validation: the documented shell snippet passed `/bin/sh -n`; the configured-port parser returned the current `CVORTEX_PORT=8080`; the readiness request reached `/api/v1/health/ready` and returned the known local `503 {"status":"not_ready"}` caused by the existing database-name mismatch; Markdown targets and `git diff --check` passed.

### PR #38 fresh review port parsing remediation — 2026-10-03

Fresh exact-head review found that the first configured-port parser did not preserve all valid Compose dotenv forms such as quoted, spaced or inline-commented values. The health command now obtains Nginx's effective published address through `docker compose port nginx 80`, delegating port resolution to Compose and preserving the `/ready` readiness check. No runtime code, Compose configuration, database, volume or `stage` state changed.

Validation: the updated shell snippet passed `/bin/sh -n`; `docker compose port nginx 80` returned the configured loopback address; the readiness request used that address and returned the known local `503 {"status":"not_ready"}` caused by the existing database-name mismatch; and `git diff --check` passed.

### User-facing documentation refresh — 2026-10-03

Reworked the root README into a concise project landing page, added a current-state User Guide, separated user/operator instructions from product and architecture references in the documentation map, and clarified that Scope records direction rather than feature availability. Reconciled the local setup/runbook with Makefile, Compose, health routes and environment sources; corrected shell placeholders in Access Core and removed legacy diagnostic codes no longer emitted by the application. Current limitations are explicit: AI is disabled by default, vacancy URLs are metadata only, application drafts are not document exports or submissions, MCP is read-only/disabled by default, account recovery and backup/restore are not implemented, and Preview 0.1 lacks recorded real-user acceptance.

Validation: Markdown render/fence checks passed for 55 `README.md`/`docs` files; shell syntax passed for 22 documentation command blocks; relative Markdown/Obsidian file targets passed across 99 project Markdown files; Compose configuration passed with both `.env` and `.env.example`; and `git diff --check` passed. `make up` and `docker compose ps` passed with services running/healthy; the local homepage returned HTTP 200, while `/api/v1/health/ready` returned HTTP 503. `make migrate` stopped in runtime-role provisioning before Laravel migrations because the configured database is absent from the existing PostgreSQL volume. No setup rebuild, migration, volume or application-data change was performed.

### README and process map documentation — 2026-10-03

Reworked the README as a human-readable landing page with current M1/Preview status, available capabilities, a compact user flow, local quick start and focused documentation links. Added `docs/00-Home/Process-Map.md` with separate Preview, provenance, local runtime and diagnostics diagrams; M2 package work is clearly marked as planned and gated on Preview 0.1 evidence. Linked the map from the Documentation Map. `NEXT.md` and `BLOCKERS.md` remain unchanged; no Preview E2E or M2 work started.

Validation: Markdown rendered for 12 changed documents; 19 fenced blocks balanced; 40 relative Markdown and 76 Obsidian links resolved; all 12 shell snippets passed `sh -n`/`bash -n`; all five Mermaid diagrams rendered to SVG using the locally installed Mermaid 11.15.0 and Chrome; `git diff --check` passed. Reused the prior runtime evidence that local readiness returns 503 due to the configured database being absent from the existing PostgreSQL volume. Runtime, database and volume were not changed.

### User and operator documentation language pass — 2026-10-03

Reworked the Russian prose across the README, user guides, process map, documentation map and local operator instructions. Translated the remaining operator pages for logging/diagnostics and MCP validation. Exact interface labels, commands, API names, status values and technical identifiers remain unchanged; internal technical references remain outside this language pass. No application code or runtime configuration changed.

Validation: Markdown rendered for 11 user/operator documents; 42 relative Markdown targets and heading anchors plus 72 Obsidian links resolved; all 15 shell snippets passed `bash -n`; all five Mermaid diagrams rendered in the locally available Mermaid/Chrome toolchain; and `git diff --check` passed. `NEXT.md` and `BLOCKERS.md` were not changed by this pass.

### User-facing documentation editorial pass: 2026-10-03

Applied a humanizer pass to prose in `README.md`, the CVortex home and documentation maps, both user guides, the process map, product scope, and the local development, diagnostics, M0, M1 access and MCP validation guides. Simplified repeated explanations and instructions, tightened transitions, and removed dash-heavy phrasing. Preview 0.1 acceptance, the M2 gate, MCP E2E status, and known local runtime limitations retain their existing status. The added Mermaid diagrams and technical identifiers were not changed.

Validation performed: `git diff --check`; Markdown fence balance across 14 changed documents; `bash -n` on 15 fenced shell blocks; relative Markdown and Obsidian file targets across 14 changed documents. Markdown and Mermaid renderers were unavailable in this checkout, so those renders were not rerun. `NEXT.md` and `BLOCKERS.md` remain unchanged.

### PR #39 Codex review remediation — 2026-10-03

Fixed the P2 configuration mismatch: Compose forwards `OPENAI_APPLICATION_DRAFT_MODEL` through the shared backend/Horizon environment, `.env.example` exposes the setting, and the local setup guide explains how to enable draft generation. A fresh exact-head review also found the application pricing inputs were not forwarded; those are now exposed and forwarded too, with the guide clarifying they are required for cost estimates. This is a bounded correction to review findings; no provider credentials were added.

Validation: `docker compose --env-file .env config --quiet` passed; a rendered Compose config with a test model confirmed the value reaches both `backend` and `horizon`; `git diff --check` passed. Repository Compose configuration changed, but no containers were recreated and no local runtime state, database or volumes were changed.

Fresh exact-head Codex review also found that the user guide overstated offline matching availability. The user and local setup guides now clarify that first analysis of a vacancy needs AI to extract requirements; deterministic matching can be repeated offline only after requirements were successfully extracted, because manual requirement entry is not available.

Validation: confirmed the call order in `VacancyAnalysisService::analyzeForOwner()`, then checked the edited Markdown targets and `git diff --check`. No application code or runtime state changed.

### Project-state audit — 2026-10-03

Recorded the audit in [the current project-state report](../../docs/00-Home/CURRENT-PROJECT-STATE.md), based on `a599330bbaec5ff957bd6d6600136b97c2a83655`, which matched `origin/stage` during the audit. Compose services were healthy and the homepage returned HTTP 200, but `/api/v1/health/ready` returned 503 because configured database `cvortex2` is absent from the existing PostgreSQL volume; `migrate:status` stopped before migrations. Preview 0.1 remains blocked pending runtime reconciliation and real-user acceptance; M2 remains gated. No migration or data changes were made. `NEXT.md` and `BLOCKERS.md` remain unchanged.

Validation: the audit's live checks and their results are recorded in the report's checks table. No application test suite was rerun for this documentation record.

### ChatGPT read-only MCP integration preflight — 2026-10-03

Rechecked current official OpenAI integration guidance. ChatGPT does not connect directly to a local MCP endpoint; private MCP requires Secure MCP Tunnel. The authenticated Plus account displays the custom MCP app form, and the existing Platform tunnel resolves in that form; no app was created. Official Plus eligibility sources conflict; native ChatGPT Desktop MCP invocation remains unverified and the Help Center describes custom MCP apps as web-only. The official `tunnel-client` v0.0.15 binary was downloaded from OpenAI's GitHub release and SHA-256 verified against GitHub's published asset digest. It is installed locally, and a `cvortex-chatgpt` profile points at the existing tunnel and local MCP endpoint using only an environment-variable reference for the key. `doctor --explain` fails solely because `CONTROL_PLANE_API_KEY` is unset; the tunnel client is not running. The ChatGPT app form is populated but unsubmitted and its security acknowledgment remains unchecked. Recorded the updated boundary in MCP research, threat model, validation, local setup and blockers. Added a portable read-only CVortex skill package and repo marketplace, then registered and installed it through the official Codex CLI after making and byte-verifying a backup of the existing user config. The skill is installed and enabled in local Codex plugin configuration, but desktop reload and MCP app binding remain unverified. An MCP app binding needs ChatGPT to register the app and supply a real `plugin_asdk_app...` ID. `.env.example` already has `MCP_ENABLED=false` and optional MCP URL settings, so it did not need modification. `NEXT.md` remains unchanged.

The PostgreSQL boundary harness previously selected the last six migration records, which stopped targeting the OAuth/Application migrations as later migrations were added. It now isolates those six named records into a temporary rollback batch and verifies re-up while preserving seeded parent data. No production migration, persistent database, or volume was changed.

Validation: `make test` passed (backend 299 tests, 2261 assertions, 11 skipped; frontend 73 tests); `make lint` passed (Pint 187 files, PHPStan 117 files, ESLint and TypeScript); `bash -n scripts/test-application-postgres-boundary.sh` passed; the PostgreSQL boundary harness passed, running its runtime-role/RLS suite twice (3 tests, 55 assertions each), plus exact OAuth/Application rollback/re-up and preserved rows; local discovery returned HTTP 200 at all three metadata routes and unauthenticated `POST /mcp/v1` returned HTTP 401; plugin and marketplace JSON parsed and matched the documented package shape; `codex plugin marketplace add ./`, `codex plugin add cvortex-read-only@cvortex-repository` and `codex plugin list --marketplace cvortex-repository --json` confirmed an installed/enabled local package; Markdown fences, whitespace and relative links passed across ten files; secret/local-path scan found no matches; `git diff --check` passed.

Result: **BLOCKED_EXTERNAL** pending the user's creation and local configuration of the separate tunnel runtime key. After the client authenticates, the ChatGPT app still needs action-time confirmation before creation, and the user's own OAuth interaction and real read-only tool calls must be verified. No commit, push, PR, CI run, or merge was started because the task's post-PASS delivery gate was not met.

### Local MCP/OAuth origin correction — 2026-10-03

**Local implementation: PASS; ChatGPT E2E: BLOCKED_EXTERNAL / manual follow-up.** Explicit current-user override authorized this bounded IMPLEMENT task rather than Preview work; `bash scripts/check-agent-contract.sh implementation --write --user-override` passed on existing related branch `feature/chatgpt-local-mcp-integration`, base/target `stage`. Existing uncommitted changes were preserved; no commit, push, PR or app creation occurred.

Root cause: root `.env.example` and active ignored `.env` supplied `APP_URL=http://localhost:8080`, whereas the tunnel target is `http://127.0.0.1:8080/mcp/v1`. Existing `McpResource` already derives resource, issuer and endpoints from trusted APP_URL when overrides are empty; no new abstraction or OAuth implementation change was needed. APP_URL now uses `http://127.0.0.1:8080`; Compose forwards both existing optional MCP URL settings. Only the non-secret APP_URL line was changed in active .env. Backend/Horizon were recreated without dependencies and backend config cache cleared; no migrations or volume operations were performed.

Added structural metadata regression with a differing localhost request Host, exact resource/issuer/endpoints, shared origin and preserved advertised OAuth capabilities. Existing exactly-two-read-tools and mutation rejection tests pass. Security controls and domain/tool implementations are unchanged. Documentation updates cover architecture, validation, setup, local defaults, research and threat boundary.

Validation actually executed: `make test` PASS (backend 300 tests, 2285 assertions, 11 skipped; frontend 73 tests); `make lint` PASS (Pint 187 files, PHPStan 117 files/no errors, ESLint, TypeScript); `docker compose --env-file .env.example config --quiet` PASS; both live curl metadata commands + JSON formatting PASS; independent structural HTTP check PASS for resource, issuer, authorization server and all three endpoints, with no localhost in either response; `git diff --check` PASS. Installed tunnel-client version/help and matching official release docs confirm `--harpoon.allow-plaintext-http`; health listener `127.0.0.1:0` avoids port 8080 collision. Runtime key absent from this process, so doctor not run. User's Harpoon HTTP 200 + ChatGPT discovery error evidence predates correction; post-fix discovery remains manual/unverified, not a proven upstream issue.

### User-authorized Git delivery — 2026-10-03

The user explicitly authorized this bounded GIT/RELEASE override despite the canonical `NEXT.md` task pointing to Preview 0.1 acceptance. `bash scripts/check-agent-contract.sh git-release --write --user-override` passed. Implementation was committed as `337b97c78c950718bbd8650da78c2d8e3409e384` (`feat(chatgpt): add local read-only CVortex integration`) and pushed to `origin/feature/chatgpt-local-mcp-integration`. PR #42 targets `stage` under M3, assigned to the user, and is marked `status:blocked` pending external OAuth discovery.

`bash scripts/check-pr-contract.sh 42` passed after applying roadmap/type/area/priority/changelog metadata and both required checkpoint comments. The latest GitHub `Roadmap metadata` and `PR contract` checks passed on the implementation head. GitHub `m0-quality` was still running when this entry was prepared; earlier Governance events from initial PR creation failed before assignee/checkpoint metadata was complete. No review approval or merge occurred. ChatGPT post-origin OAuth discovery and tool-call E2E remain unverified; the existing manual follow-up in `BLOCKERS.md` is unchanged. The Preview task in `NEXT.md` and unrelated milestone gates remain unchanged.

### PR #42 quick-start origin review remediation — 2026-10-03

The Codex P2 review noted that the root README still directed users to `localhost:8080` after the canonical APP_URL moved to `127.0.0.1:8080`. Updated the quick-start URL and pushed commit `ccb6488dc836293eacbd8cf63b0eca80e1298a02`. The existing regression `test_local_oauth_metadata_uses_one_configured_origin_independent_of_request_host` covers the configured-origin boundary. `git diff --check` passed, and a targeted README scan confirmed no `localhost:8080` reference remains. Replied to and resolved only the matching review thread; a fresh exact-head review remains pending. The ChatGPT OAuth/E2E blocker remains unchanged.
