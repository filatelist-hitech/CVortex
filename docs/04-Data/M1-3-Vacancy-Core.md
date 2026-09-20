---
title: M1.3 Vacancy Core Implementation
status: remediation-awaiting-independent-review
owner: project
created: 2026-09-19
updated: 2026-09-19
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

The original independent review verdict is `CHANGES REQUIRED`. The M1.3R implementation below remediates the four confirmed findings and awaits a separate independent verdict; this document does not declare that gate passed.

The optional source URL is metadata only. The backend does not resolve DNS, open a socket, follow a redirect or fetch the URL. URL/server ingestion and job-board adapters remain M3 work.

## Data and provenance

`Vacancy` is the logical owner-scoped aggregate. A partial PostgreSQL unique index enforces one aggregate per `owner_id + source_url` when URL metadata exists; `NULL` manual/paste URLs remain independent. Import takes a transaction-scoped PostgreSQL advisory lock for that logical key and locks the aggregate row before allocating the next snapshot version. The existing `(vacancy_id, version)` unique constraint is the final duplicate-version guard. Canonicalized line endings and outer whitespace are used only for hashing; they do not replace `raw_text`. An exact same-owner content hash reuses the existing snapshot. Concurrent changed-content imports for the same owner and URL converge on one Vacancy and create monotonically increasing snapshots.

`VacancySnapshot` preserves the exact pasted text, source URL metadata, import time, content hash and version. It is creation-only through `VacancySnapshot::record`: all attributes are guarded, Eloquent rejects updates, and a PostgreSQL `BEFORE UPDATE` trigger rejects raw SQL mutation of historical content, hash, version, URL or owner/aggregate identity. New source content creates a new row.

`VacancyRequirement` stores one of the seven dimensions, `MANDATORY / PREFERRED / UNCERTAIN`, a normalized label/value, a verbatim source excerpt, confidence and Skill identity. Provider output is still untrusted after schema validation. If source text contains an instruction-directed role message, recommendation manipulation, ignore/override directive or fake JSON/XML instruction wrapper, extraction fails closed even when the provider returns an empty or partial set. In ordinary text, deterministic validation rejects instruction-directed output before matching. Legitimate requirements about system design, JSON/XML APIs, prompt engineering and recommendation systems remain valid. Employer-marketing output is discarded while concrete requirements embedded in hiring phrasing are preserved. Non-null structured values must be supported by the source excerpt. Deterministic validation forces “will be a plus”, “nice to have”, “preferred”, “желательно” and “будет плюсом” to `PREFERRED` even if a model labels it mandatory.

`VacancyAnalysis` links a snapshot and a hash of the current trusted Career context. Every result has seven `VacancyMatchDimension` rows. Candidate evidence is linked through `VacancyMatchEvidence` to either a same-owner current confirmed CareerFact or a live Truth-Guard `PASS` Claim. PostgreSQL owner-composite foreign keys enforce the owner chain in addition to server-side query scoping.

All seven Vacancy tables additionally use forced PostgreSQL RLS. HTTP middleware derives `cvortex.owner_id` from the authenticated server-side user; ingestion/analysis services and Horizon jobs establish the same scoped context and restore or clear it in `finally`. Missing context sees no private Vacancy rows. The Compose runtime connects as `cvortex_app`, a login role with neither `SUPERUSER` nor `BYPASSRLS`; migrations use the separate administrative connection. The PostgreSQL init script provisions or reconciles the runtime role without storing a production credential in Git.

```text
recommendation
→ dimension
→ requirement
→ snapshot

dimension evidence
→ CONFIRMED CareerFact / PASS Claim
→ confirmed Career provenance
```

The detail API recomputes the trusted Career signature. Adding, superseding or deprecating trusted Career evidence makes an earlier analysis observably `stale`; explicit reanalysis produces a new signature-linked result without mutating the source snapshot.

## Matching and recommendation policy

The dimensions are `TECHNICAL`, `EXPERIENCE`, `DOMAIN`, `LANGUAGE`, `LOCATION`, `WORK_FORMAT` and `SALARY`. Missing vacancy requirements are `NOT_APPLICABLE`; missing candidate data is `UNKNOWN`. Results can also be `MATCH`, `ADJACENT`, `GAP` or deterministic `BLOCKER`.

Exact source/candidate comparisons and supported structured values are deterministic. Location, work-format and salary comparisons require dimension-specific explicit candidate wording; incidental mentions do not count as evidence. Experience duration candidates must be about the requirement subject, and all relevant values are considered before declaring incompatibility. Conservative adjacency families can identify weak related evidence, but adjacency remains a gap and never upgrades familiarity into direct/commercial experience. Duration comparison occurs only when both sides contain an explicit computable number. Missing candidate values for mandatory structured requirements remain unknown and cap the recommendation at `MAYBE`.

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

`POST /vacancies` accepts at most 50,000 characters and only HTTP(S) syntax for URL metadata. It returns `202` for a new snapshot and `200` with `duplicate: true` for an exact same-owner duplicate. Cross-owner direct and action routes return `404`.

## Runtime AI and failure behavior

`vacancy.requirement-extraction@1.0.0` lives under `/runtime-ai/skills/vacancy-requirement-extraction/v1/`. Trusted instructions and untrusted vacancy data are separate fields at the provider boundary. Strict structured output contains requirements only; matching and recommendation remain application policy.

`VacancyLlmRun` records snapshot/owner, Skill/Prompt versions, logical ModelPolicy, actual provider/model, request ID, status, usage, latency, retry, safe validation category and estimated cost when available. It stores no raw vacancy text or credentials. A failed provider/schema/semantic run leaves the snapshot intact, exposes a stable safe error code and never produces a confident recommendation.

The frontend renders raw text as inert React text, never trusted HTML. It labels source wording, CVortex inference, confirmed candidate evidence, unknown data and deterministic blockers separately. There is no ATS probability or numeric universal score.

## Validation entry points

```bash
docker compose run --rm --no-deps -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= backend php artisan test --filter=VacancyCoreTest
docker compose run --rm --no-deps -e DB_DATABASE=<disposable-db> backend php artisan test --filter=VacancyPostgresSecurityTest
bash scripts/test-vacancy-postgres-revalidation.sh
bash scripts/test-vacancy-postgres-concurrency.sh
docker compose run --rm --no-deps frontend npm test
```

The feature suite covers snapshot/hash/version behavior, URL non-fetching, preferred/mandatory correction, marketing and instruction-family filtering, legitimate controls, exact and adjacent matching, PENDING exclusion, all seven dimensions, stale detection, cross-user denial, safe URL/size validation and absence of an ATS score field. PostgreSQL-only suites verify real runtime-role flags, forced RLS/fail-closed behavior, HTTP/job context cleanup, cross-owner raw SQL denial, snapshot triggers and independent-process import convergence. `test-vacancy-postgres-revalidation.sh` creates one uniquely named disposable database, runs the security suite, rolls back only the remediation migration, migrates it forward, then runs the same suite again without removing first-run rows; it verifies that preserved `users` rows survive both migration stages.
