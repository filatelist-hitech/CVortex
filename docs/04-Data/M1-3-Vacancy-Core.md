---
title: M1.3 Vacancy Core Implementation
status: review-pass-awaiting-merge
owner: project
created: 2026-09-19
updated: 2026-09-23
tags: [vacancy, matching, provenance, ai, m1]
related:
  - "[[Phase-06-Data-Design|Phase 06 Data Design]]"
  - "[[M1-2-Career-Core|M1.2 Career Core Implementation]]"
  - "[[../03-ADR/ADR-0017-untrusted-external-content|ADR-0017]]"
  - "[[../../runtime-ai/README|Runtime AI assets]]"
---

# M1.3 Vacancy Core Implementation

M1.3 implements the bounded pasted-text vacancy flow:

```text
pasted untrusted text
→ immutable owner-scoped snapshot
→ deterministic hash / exact duplicate handling
→ semantic requirement extraction
→ deterministic matching against trusted Career evidence
→ seven explainable dimensions, gaps and recommendation
```

The original review returned `CHANGES REQUIRED`. Final PR #27 remediation and review now pass; the PR awaits merge into `stage`. M1.4 has not started.

The optional source URL is metadata only. The backend does not resolve DNS, open a socket, follow a redirect or fetch the URL. URL/server ingestion and job-board adapters remain M3 work.

## Data and provenance

`Vacancy` is the logical owner-scoped aggregate. A partial PostgreSQL unique index enforces one aggregate per `owner_id + source_url` when URL metadata exists; `NULL` manual/paste URLs remain independent. Import takes a transaction-scoped PostgreSQL advisory lock for that logical key and locks the aggregate row before allocating the next snapshot version. The existing `(vacancy_id, version)` unique constraint is the final duplicate-version guard. Canonicalized line endings and outer whitespace are used only for hashing; they do not replace `raw_text`. Deduplication compares incoming content only with the latest snapshot of the same logical URL aggregate. A historical hash that reappears after changed content creates a new, higher snapshot version. Concurrent changed-content imports for the same owner and URL converge on one Vacancy and create monotonically increasing snapshots.

`VacancySnapshot` preserves the exact pasted text, source URL metadata, import time, content hash and version. It is creation-only through `VacancySnapshot::record`: all attributes are guarded, Eloquent rejects updates, and a PostgreSQL `BEFORE UPDATE` trigger rejects raw SQL mutation of historical content, hash, version, URL or owner/aggregate identity. New source content creates a new row. Reanalysis locks the Vacancy aggregate before selecting its current snapshot and setting `PENDING`; import uses that same row lock before publishing a new version. Requests made while the current analysis is already `PENDING` or `RUNNING` leave that state intact. Job claim and terminal status updates verify snapshot currency atomically, so stale work cannot replace the current version's status.

`VacancyRequirement` stores one of the seven dimensions, `MANDATORY / PREFERRED / UNCERTAIN`, a normalized label/value, a verbatim supporting source clause, confidence and Skill identity. Provider output is still untrusted after schema validation: deterministic validation binds each label to one supporting clause before deriving dimension, value, negation and importance. Multiple supporting clauses fail closed instead of combining their attributes. A provider enum cannot reclassify a technical skill as domain (or structured location, salary or language evidence as another dimension). Residency wording derives `LOCATION`, and source-bound industry or sector experience derives `DOMAIN`; a city or industry mentioned only in a parser project is not matching candidate evidence. A qualified arbitrary language name is `LANGUAGE`, not `TECHNICAL`. Technical/domain/experience labels must identify a concrete source-bound qualification subject; a duration or modifier alone is not a subject. Modifier-only labels such as `Strong` cannot stand in for `Kubernetes` in `Strong Kubernetes skills are required`, and `3 years` cannot replace `PHP` in `3 years of PHP experience required`. Language labels must identify the named language; `Advanced` cannot stand in for `English` in `Advanced English B2 proficiency required`. Generic categories such as `technology`, `framework`, `skill` or unrelated role fragments such as `backend` also fail. Candidate-directed requirement cues stop before bounded role, position, team and project framing. Source duration wording determines `EXPERIENCE` even if a provider supplies only the subject label. Source wording also determines explicit requirement strength: preferred cues produce `PREFERRED`, while `required`, `mandatory`, `must have` and equivalent Russian wording produce `MANDATORY`; mixed cues in one inseparable clause remain `UNCERTAIN`. Candidate evidence is evaluated by subject occurrence and local clause, including contracted negation and explicit zero/none/lack of experience; negative evidence for one technology cannot suppress positive evidence for another or a separate positive clause. Location evidence accepts explicit forms such as `Location: Berlin`, `Based in Berlin`, `Located in Berlin` and `Lives in Berlin`; incidental mentions do not establish a candidate location. Salary normalized values must match one source-supported currency/amount expression, including unambiguous space-grouped thousands. Empty-output tokens alone (`[]`, `nothing`, `empty array/list`) do not signal prompt injection; they are rejected when coupled to a requirement extraction/parsing or provider-output directive. Quoted attack examples in a bounded prompt-injection security qualification remain source data; an actual instruction in the same or a later clause still fails closed. If source text contains an instruction-directed role message, recommendation manipulation, an ignore/disregard/override instruction aimed at prior, previous or earlier prompts, rules, directions, context or messages, or an instruction to avoid, prevent, skip, omit, ignore or suppress requirement extraction/parsing or return empty output, extraction fails closed even when the provider returns an empty or partial set. In ordinary text, deterministic validation rejects instruction-directed output before matching. Legitimate technical requirements that discuss preventing SQL injection, avoiding N+1 queries, empty API arrays or data extraction remain valid. Employer hiring requirements are preserved; mission, product, architecture and business statements alone do not become candidate requirements. Non-null structured values must be supported by the bound source clause.

`VacancyAnalysis` links a snapshot and a hash of the current trusted Career context. Every result has seven `VacancyMatchDimension` rows. Candidate evidence is linked through `VacancyMatchEvidence` to either a same-owner current confirmed CareerFact or a live Truth-Guard `PASS` Claim. PostgreSQL owner-composite foreign keys enforce the owner chain in addition to server-side query scoping.

All seven Vacancy tables additionally use forced PostgreSQL RLS. HTTP middleware derives `cvortex.owner_id` from the authenticated server-side user; ingestion/analysis services and Horizon jobs establish the same scoped context and restore or clear it in `finally`. Missing context sees no private Vacancy rows. Backend and Horizon receive only `cvortex_app`, a login role with neither `SUPERUSER` nor `BYPASSRLS`; the Compose `migration` tools-profile service receives the separate administrative connection. `POSTGRES_RUNTIME_PASSWORD` is required and must differ from the administrative password: `make init` upgrades a missing or equal local value, while Compose and the init script fail closed otherwise. The PostgreSQL init script provisions or reconciles the runtime role without storing a production credential in Git.

```text
recommendation
→ dimension
→ requirement
→ snapshot

dimension evidence
→ CONFIRMED CareerFact / PASS Claim
→ confirmed Career provenance
```

The detail API recomputes the trusted Career signature. It selects an exact current-signature analysis first, then uses `created_at DESC, id DESC` as a deterministic stale fallback. Adding, superseding or deprecating trusted Career evidence makes an earlier analysis observably `stale`; explicit reanalysis produces a new signature-linked result without mutating the source snapshot.

## Matching and recommendation policy

The dimensions are `TECHNICAL`, `EXPERIENCE`, `DOMAIN`, `LANGUAGE`, `LOCATION`, `WORK_FORMAT` and `SALARY`. Missing vacancy requirements are `NOT_APPLICABLE`; missing candidate data is `UNKNOWN`. Results can also be `MATCH`, `ADJACENT`, `GAP` or deterministic `BLOCKER`.

Exact source/candidate comparisons and supported structured values are deterministic. Provider labels for technical/domain requirements must identify the cue-bound source-derived subject, including active wording such as `role requires Kubernetes`, not merely a generic word such as `experience` or an unrelated role descriptor. Token/skill matches are boundary-aware: `Go` can match `Go backend` but not `Google`. Location uses canonical equality, so `US` cannot match `Russia`. Work format requires arrangement wording such as `remote work`, `fully remote`, `hybrid role`, `on-site role` or `office-based`; remote access, desktop, APIs and hybrid architecture do not establish a work format. Candidate inability evidence such as `cannot work remotely` is never affirmative evidence for a structured work-format requirement. Language evidence must tie a proficiency, fluency or level construction to the named language, including bounded forms such as `English at B2 level`, `English level B2` and `B2 English`; this grammar applies to qualified language names beyond the built-in canonical aliases. An English-speaking team or English documentation remains incidental. Candidate evidence must support the named language and qualification. A quantified language requirement matches only the same source-supported construction and language name; CVortex does not infer equivalence among CEFR levels, fluency and other descriptors. Salary comparison retains lower, upper, exact and range semantics: overlapping intervals, including a shared endpoint, are compatible; only an explicit non-overlapping interval in the same currency and comparable pay period is a blocker. Missing, mixed-currency or incomparable-period values remain unknown. Unquantified experience can use exact confirmed commercial/direct evidence after removing source-only requirement-strength framing; commercial/professional/production wording in the source makes the requirement `EXPERIENCE` even when provider output calls the subject a technical skill. Duration comparison requires a source-supported number and explicit unit tied to experience, plus a candidate duration tied to the same subject. Canonical values use `years:<number>` or `months:<number>`; years/months convert only when both sides state units, and sentence framing such as `we require` is not part of the subject. Ambiguous multi-duration or unrelated duration wording remains unknown. All relevant values are considered before declaring incompatibility. Conservative adjacency families can identify weak related evidence, but adjacency remains a gap counted according to requirement importance and never upgrades familiarity into direct/commercial experience. One or two unsupported mandatory requirements, including adjacent evidence, cap the recommendation at `MAYBE`; three or more produce `LOW_PRIORITY`. Missing candidate values for mandatory structured requirements remain unknown and cap the recommendation at `MAYBE`.

Recommendation classes are priority decisions, not interview probability or an ATS reconstruction:

- any deterministic mandatory incompatibility → `SKIP`;
- three or more unsupported mandatory requirements → `LOW_PRIORITY`;
- one or two unsupported mandatory requirements → `MAYBE`;
- all mandatory requirements matched with no preferred gap or uncertainty → `STRONGLY_APPLY`;
- confirmed matches with only softer gaps → `APPLY`;
- insufficient evidence → `MAYBE`.

Gap advice names unsupported hard/preferred requirements, adjacent evidence, unknown Career data or structured incompatibility. It never creates or recommends inventing a Career Fact.

## API boundary

All routes are authenticated, active-user, same-origin `/api/v1` routes. Acting owner identity always comes from the server session.

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/vacancies` | Owner-scoped list, latest status/recommendation and stale marker |
| `POST` | `/vacancies` | Preserve pasted text plus optional URL metadata and enqueue analysis |
| `GET` | `/vacancies/{id}` | Snapshot, requirements, run metadata, seven dimensions, gaps and recommendation |
| `POST` | `/vacancies/{id}/reanalyze` | Re-run the current snapshot against current trusted Career evidence |

`POST /vacancies` accepts at most 50,000 characters and only HTTP(S) syntax for URL metadata. For a URL-backed aggregate, it returns `200` with `duplicate: true` only when content matches that aggregate's current snapshot; otherwise it returns `202` for a new snapshot version. Pasted submissions without URL metadata have no shared logical identity and create independent aggregates. Cross-owner direct and action routes return `404`.

## Runtime AI and failure behavior

`vacancy.requirement-extraction@1.0.0` lives under `/runtime-ai/skills/vacancy-requirement-extraction/v1/`. Trusted instructions and untrusted vacancy data are separate fields at the provider boundary. Strict structured output contains requirements only; matching and recommendation remain application policy.

`VacancyLlmRun` records snapshot/owner, Skill/Prompt versions, logical ModelPolicy, actual provider/model, request ID, status, usage, latency, retry, safe validation category and estimated cost when available. It stores no raw vacancy text or credentials. A failed provider/schema/semantic run leaves the snapshot intact, exposes a stable safe error code and never produces a confident recommendation.

The frontend renders raw text as inert React text, never trusted HTML. It labels source wording, CVortex inference, confirmed candidate evidence, unknown data and deterministic blockers separately. Polling refreshes and an older import response may update the list but cannot replace a newer explicit selection or detail; overlapping older list/detail responses are ignored, and a selected vacancy removed from the refreshed list follows the existing first-item fallback. There is no ATS probability or numeric universal score.

## Validation entry points

```bash
docker compose run --rm --no-deps -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= backend php artisan test --filter=VacancyCoreTest
docker compose run --rm --no-deps -e DB_DATABASE=<disposable-db> backend php artisan test --filter=VacancyPostgresSecurityTest
bash scripts/test-vacancy-postgres-revalidation.sh
bash scripts/test-vacancy-postgres-concurrency.sh
docker compose run --rm --no-deps frontend npm test
bash scripts/check-runtime-db-credentials.sh
```

The feature suite covers snapshot/hash/version behavior, URL non-fetching, preferred/mandatory correction, marketing and instruction-family filtering, legitimate controls, exact and adjacent matching, PENDING exclusion, all seven dimensions, stale detection, cross-user denial, safe URL/size validation and absence of an ATS score field. PostgreSQL-only suites verify real runtime-role flags, forced RLS/fail-closed behavior, HTTP/job context cleanup, cross-owner raw SQL denial, snapshot triggers and independent-process import convergence. `test-vacancy-postgres-revalidation.sh` creates one uniquely named disposable database, verifies that a one-step rollback of the snapshot-history migration does not introduce a non-existent owner-wide constraint, then rolls back both current Vacancy remediation migrations, migrates them forward, and runs the security suite again without removing first-run rows; it verifies that preserved `users` rows survive both migration stages.
