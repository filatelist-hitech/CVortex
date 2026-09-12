---
title: CVortex Phase 11 — Vacancies + Matching
status: ready
phase: 11-vacancies-matching
owner: project
created: 2026-09-12
updated: 2026-09-12
tags:
  - task
  - vacancies
  - matching
  - ingestion
  - ai
related:
  - ../../PROJECT.md
  - ../state/STATUS.md
  - ../../docs/03-ADR/INDEX.md
---

# CVortex — Phase 11: Vacancies + Matching

## Goal

Реализовать vacancy ingestion, normalization, requirement extraction, matching, gap analysis и explainable `should-i-apply` foundation поверх подтверждённого Career Foundation.

Phase 11 должна принимать вакансии безопасно, сохранять исходный источник, извлекать требования без выдумывания, сопоставлять их с `CONFIRMED` Career Facts/Claims и выдавать explainable recommendation без fake universal ATS score.

## Execution mode

Следуй `/AGENTS.md`, scoped instructions, resource policy, accepted ADR, Phase 04 integration research, Phase 06 architecture/security design и Phase 10 Truth-first implementation.

Default:

- один агент;
- sequential implementation;
- deterministic ingestion/hash/dedup before AI;
- targeted research only for source capability/ToS questions not already settled;
- browser/network functionality only where explicitly permitted by research;
- provider-independent runtime AI Skills;
- narrow tests while iterating, final pipeline/security suite before completion.

## Minimum context

Прочитай:

1. `PROJECT.md`.
2. current project state.
3. accepted ADR relevant to API, ownership, untrusted external content, provider independence and storage.
4. Phase 06 Vacancy flow, Data/AI/Security design.
5. Phase 10 CareerFact/Claim/Truth Guard implementation contracts.
6. Phase 04 vacancy-source matrix/integration strategy only for sources or URL-ingestion decisions actually implemented in this phase.
7. current OpenAPI/domain docs.

Не перечитывай весь Phase 04 corpus. Для конкретного source используй canonical matrix и linked evidence.

# Core invariants

## Raw-source preservation

Система должна сохранять immutable/logically versioned source snapshot sufficient to reproduce what vacancy content was analyzed at that time.

Normalization/parsing не заменяет raw source.

## Untrusted input

Vacancy text, HTML, external API responses and source metadata are `DATA`, not instructions.

Prompt injection inside vacancy must not alter agent/tool/system behavior.

## Truth-first matching

Candidate-side match evidence может использовать только permitted Career data, прежде всего `CONFIRMED` facts and valid Claims.

`PENDING` facts must not silently improve match.

## Explainability

Recommendation must explain dimensions, supporting evidence and gaps.

No fabricated universal ATS probability/score.

# Scope

Реализовать:

- Vacancy;
- VacancySource;
- VacancySnapshot;
- VacancyRequirement;
- content hashing;
- duplicate detection;
- raw-source preservation;
- adapter interface;
- Manual/Paste ingestion;
- URL ingestion only for source classes explicitly permitted by Phase 04 evidence and current restrictions;
- source detection;
- parsing/normalization;
- requirement extraction;
- matching;
- gap analysis;
- should-i-apply recommendation;
- API/UI needed for those workflows;
- provenance and LLM run accounting;
- tests/evals/security controls.

# Non-goals

НЕ реализовывать:

- automatic application submission;
- resume tailoring;
- cover letter generation;
- Application/Employer Memory workflow beyond interfaces strictly required for future Phase 12;
- recruiter conversation import;
- interview prep;
- arbitrary scraping of restricted sites;
- undocumented API reverse engineering;
- headless browser automation for blocked sources unless separately accepted;
- fake ATS score;
- vector DB just for vacancy matching;
- Phase 12.

# Vacancy Domain

## Vacancy

Represent normalized logical vacancy.

Minimum concerns:

- user ownership/private scope;
- current/selected snapshot;
- title;
- company/employer identity as source data, without inventing canonical company merge if uncertain;
- location/work-format/salary/level where source supports it;
- source relation;
- status/lifecycle according to design;
- created/updated/imported timestamps;
- extraction/match state;
- provenance.

Do not discard ambiguous/raw values merely because normalized fields exist.

## VacancySource

Represent origin/category of vacancy input.

Must distinguish conceptually:

- manual paste;
- user-provided URL;
- official API/feed where implemented;
- supported structured career page/ATS source;
- uploaded file/document if Phase 11 permits it;
- future adapter types.

Do not label undocumented/internal endpoint as official API.

## VacancySnapshot

Each imported/retrieved content version should preserve enough information for reproducibility.

Consider:

- source URL/identifier;
- retrieval/import timestamp;
- content hash;
- raw text and/or sanitized raw artifact reference;
- source type;
- retrieval method;
- response metadata allowed by privacy/security policy;
- parsing version;
- immutable/versioned semantics.

Snapshot ownership must follow Vacancy owner.

## VacancyRequirement

Represent extracted requirements as structured, provenance-linked items.

Types may include according to design:

- hard skill/technology;
- experience/domain;
- seniority;
- education/certification;
- language;
- location;
- work format;
- salary/compensation;
- availability/work authorization if source says so;
- responsibility/context requirements.

Do not force every sentence into a requirement.

Each extracted requirement needs source evidence/reference and extraction metadata.

# Ingestion Pipeline

Implement pipeline:

```text
import
→ source detection
→ snapshot
→ deterministic preprocessing
→ parse
→ normalize
→ requirement extraction
→ validation
→ matching
→ gap analysis
→ should-i-apply
```

Every stage should have explicit status/error semantics.

Partial failure must not silently produce a confident final recommendation.

# Manual / Paste Ingestion

This is mandatory baseline and safest universal fallback.

User can paste vacancy content/metadata.

Requirements:

- preserve original input snapshot;
- validate size;
- treat as untrusted text;
- allow optional source URL as metadata without automatically fetching unless user explicitly requests/supported flow;
- content hash and duplicate detection;
- extraction pipeline;
- owner isolation.

# URL Ingestion

Implement only where Phase 04 evidence supports a permitted realistic strategy and current conditions remain compatible.

Before implementing each source category:

- read relevant Phase 04 source entry;
- verify freshness if `review_after` expired or capability is likely changed;
- distinguish official API/feed/browser capture/server-side fetch/manual fallback;
- do not expand access beyond documented/accepted approach.

If source restrictions changed, stop that adapter and document conflict rather than bypassing controls.

## SSRF Controls

Any server-side URL fetcher must enforce according to security design:

- allowed schemes, normally HTTP/HTTPS only;
- DNS/IP resolution validation;
- block loopback/private/link-local/metadata ranges;
- redirect revalidation at every hop;
- request timeout;
- response size limits;
- content-type handling;
- bounded redirects;
- no arbitrary auth headers/cookies supplied by user;
- audit/observability of fetch outcome without leaking secrets.

Do not fetch `file://`, internal Docker hostnames, localhost or cloud metadata endpoints.

If robust SSRF-safe fetching cannot be implemented in scope, do not add generic URL ingestion. Manual/Paste remains fallback.

# Adapter Interface

Create provider/source-neutral adapter contract consistent with Phase 04 research.

Adapter responsibilities may include:

- supports/detects source;
- fetch/import capability classification;
- retrieve content only where permitted;
- return normalized source snapshot input;
- surface source-specific errors/rate restrictions;
- never decide candidate match;
- never contain business-generation logic.

Do not implement adapters for every researched platform simply to populate a folder.

Phase 11 should implement only MVP adapters proven necessary and allowed.

# Source Detection

Prefer deterministic source detection from:

- URL host/path patterns;
- explicit user selection;
- supported feed/API metadata;
- structured page metadata.

LLM should not be required to decide whether `jobs.example.com` is localhost or which adapter owns a known host.

Unknown source should degrade safely to manual/paste or generic permitted parser, not arbitrary scraping.

# Hashing and Duplicate Detection

Use deterministic hashing for exact content/source snapshots.

Define normalization before hash carefully to avoid accidental equivalence or endless duplicates.

At minimum distinguish:

- exact same snapshot/content;
- same source URL with changed content;
- semantically similar but not exact vacancy.

Do not use LLM for exact duplicate detection.

Semantic near-duplicate detection is optional only if required by MVP and should not block baseline.

Duplicate behavior must preserve user intent/history; do not silently overwrite prior snapshot.

# Parsing / Normalization

Separate deterministic extraction of available structured data from semantic requirement extraction.

Possible deterministic inputs:

- JSON-LD `JobPosting` when present and trusted as source data;
- official structured API/feed fields;
- known supported ATS page structure;
- user-provided metadata.

Normalize fields while preserving raw values/provenance.

Do not invent salary, seniority, location or requirements when absent.

# Requirement Extraction Skill

Use runtime AI Skill only for semantic extraction that deterministic parsing cannot reliably cover.

Requirements:

- stable SkillVersion;
- structured input/output schema;
- source snapshot reference;
- extracted requirement evidence/excerpt;
- requirement class;
- hard/soft/uncertain classification only if justified by wording;
- mandatory/preferred signal with uncertainty handling;
- no inferred hidden employer requirements;
- logical ModelPolicy, not permanent model name;
- schema/invariant validation;
- prompt injection resistance;
- LLM run accounting/evals.

Never transform «будет плюсом» into mandatory requirement.

Do not convert marketing/company text into candidate requirements without evidence.

# Matching Model

Matching must be explainable and multi-dimensional.

Minimum dimensions:

```text
Technical
Experience
Domain
Language
Location
Work format
Salary
```

Additional dimensions only if Phase 06 requirements justify them.

## Technical

Compare VacancyRequirements to confirmed skills/experience/claims.

Distinguish:

- exact confirmed match;
- related/adjacent evidence;
- unknown/unconfirmed;
- explicit gap.

Do not treat keyword occurrence in resume as proof of experience unless backed by confirmed Career Facts.

## Experience

Use structured confirmed employment/facts.

Do not invent years from incomplete dates.

If duration calculation possible deterministically, compute it in code, not LLM.

## Domain

Semantic matching may help map adjacent domains, but evidence must remain explainable and must not upgrade familiarity to direct commercial experience.

## Language

Use confirmed language facts/requirements. Do not infer fluency from document language alone.

## Location

Use user CareerTrack/preferences and explicit vacancy requirements. Distinguish candidate preference from career fact.

## Work format

Remote/hybrid/office compatibility should be deterministic when structured values exist.

## Salary

Compare only when both candidate expectation/preference and vacancy compensation are available and compatible units/currency are known.

Do not assume missing salary = acceptable.

# Match Representation

Do not create a fake universal ATS score.

You may create internal explainable per-dimension states/scores only if Phase 06 requirements define them and they are not presented as «ATS probability».

Preferred representation should include:

- dimension;
- result/state;
- vacancy evidence;
- candidate evidence;
- gaps;
- uncertainty;
- provenance references;
- deterministic vs semantic origin.

If numeric internal weighting is used, document formula and limitations. It must not claim to reproduce a real employer ATS.

# Gap Analysis

Identify relevant gaps without recommending fabricated experience.

Gap categories may include:

- hard requirement not supported;
- preferred requirement not supported;
- candidate evidence exists but weak/indirect;
- unknown because candidate facts incomplete;
- compatibility conflict, e.g. location/work format/salary.

Recommendations can include:

- apply anyway with explicit risk;
- clarify missing Career Fact with user;
- emphasize existing adjacent confirmed evidence;
- skip/low priority due to hard blocker.

Never suggest adding skill/experience as fact merely to improve match.

# Should-I-Apply

Output classes:

```text
STRONGLY_APPLY
APPLY
MAYBE
LOW_PRIORITY
SKIP
```

Define deterministic/semantic decision policy and explainability.

Recommendation must include:

- class;
- key reasons;
- strongest confirmed matches;
- material gaps;
- hard blockers if any;
- uncertainties/missing candidate information;
- dimension results;
- provenance/evidence links.

Do not present class as guaranteed interview probability.

## Hard blockers

Where structured requirements/preferences produce confirmed conflict, define deterministic behavior according to product requirements, e.g. location/work authorization or non-negotiable work format.

LLM must not override deterministic blocker without explicit user resolution path.

# Career Fact Interaction

Phase 11 may discover that matching quality is limited by missing candidate facts.

It may propose a question/request for user clarification, but must not create `CONFIRMED` facts from vacancy content or match inference.

Vacancy requirement is not candidate fact.

Any new candidate fact must go through Phase 10 CareerFact flow.

# Provenance

Traceability required:

```text
Should-I-Apply conclusion
→ match dimensions
→ vacancy requirements
→ vacancy snapshot/source
```

and candidate side:

```text
match evidence
→ Claim / CareerFact
→ CONFIRMED CareerFact provenance
```

This does not mean storing every LLM chain-of-thought. Store decision/evidence metadata, not private reasoning traces.

# API

Implement endpoints/contracts for:

- vacancy import/manual paste;
- supported URL ingestion where permitted;
- vacancy list/detail;
- source/snapshot visibility appropriate to user;
- extraction/status;
- requirements;
- match/gap analysis;
- should-i-apply result;
- rerun/reanalysis only where safe and versioned.

Async work should expose job/status semantics according to Phase 06 API architecture.

Every endpoint enforces owner server-side.

# Frontend

Implement MVP flows:

- add vacancy;
- paste/manual entry;
- URL input only if backend supports it;
- import status/error;
- vacancy normalized view;
- raw/source snapshot access as appropriate;
- requirements review;
- match dimensions;
- gap analysis;
- should-i-apply recommendation;
- evidence/provenance disclosure.

Use Phase 07 design foundation.

Important UI constraints:

- no fake ATS gauge;
- uncertainty visible;
- deterministic hard blockers clearly separated from AI suggestions;
- pending/missing Career Facts not shown as candidate strengths;
- source retrieval restrictions/errors understandable;
- user can distinguish raw vacancy wording from CVortex inference.

# Queue / Idempotency

Semantic extraction/matching may be asynchronous.

Requirements:

- stable snapshot ID as input;
- idempotent retry where possible;
- no duplicate requirements on retry;
- match run version linked to snapshot and Career state/version as designed;
- stale match detectable when Career Facts or VacancySnapshot changes;
- bounded retry/escalation;
- safe failure status.

# LLM Accounting / Evals

All requirement extraction/matching semantic calls use runtime AI architecture.

Record:

- user;
- vacancy/snapshot;
- workflow/SkillVersion;
- PromptVersion;
- ModelPolicy + actual provider/model;
- usage/cost/latency;
- validation;
- retries/escalation;
- error class.

Eval fixtures minimum:

- simple exact tech match;
- preferred vs mandatory requirement;
- adjacent skill without production evidence;
- unclear experience duration;
- language requirement;
- location/work-format conflict;
- salary unknown/conflict;
- vacancy with marketing noise;
- prompt injection in vacancy;
- same vacancy snapshot duplicate;
- changed vacancy at same URL;
- multilingual vacancy if MVP scope requires it.

# Security

## Prompt injection

Vacancy/source content cannot issue tool/system instructions.

Test malicious text such as fake «ignore previous instructions», requests for secrets/system prompt, and attempts to access other users.

## SSRF

Mandatory negative tests if URL fetch exists:

- localhost;
- `127.0.0.1`/`::1`;
- private RFC1918 ranges;
- link-local;
- cloud metadata addresses;
- DNS rebinding/redirect into blocked range where testable;
- non-HTTP schemes;
- oversized response;
- redirect loops/timeouts.

## XSS

Imported HTML/rich content must be sanitized/escaped at rendering boundary.

Never render raw source HTML directly as trusted React content.

## Cross-user

User A cannot list/read/update/delete/reanalyze user B vacancies, snapshots, requirements, match results or ingestion jobs.

## Secrets/cookies

URL ingestion must not forward arbitrary internal cookies/credentials. Do not send authenticated browser session to external vacancy site from backend unless a separately designed user-authorized integration exists.

# Migrations

Phase 11 may add domain migrations.

Review:

- ownership FKs/indexes;
- snapshot version/hash uniqueness semantics;
- source classification;
- requirement provenance;
- match run/version relations;
- stale/recompute semantics;
- raw snapshot retention/deletion;
- cascade/restrict behavior;
- rollback.

Do not create Phase 12 Application/Resume/CoverLetter tables unless an existing migration is strictly required by accepted cross-phase architecture; avoid forward implementation.

# Tests

## Ingestion

- manual paste happy path;
- input size/validation;
- exact duplicate;
- same URL changed snapshot;
- unsupported source safe fallback;
- source detection deterministic cases;
- URL ingestion only supported/allowed source paths;
- retrieval/parser failures explicit.

## Requirement extraction

- source evidence preserved;
- mandatory/preferred distinction fixtures;
- no fabricated requirement;
- marketing text not promoted incorrectly;
- injection fixture ignored as instruction;
- schema validation.

## Matching

- confirmed exact match;
- pending CareerFact does not improve result;
- rejected/deprecated evidence handled correctly;
- adjacent evidence not upgraded to direct experience;
- dimension explainability;
- hard conflict behavior;
- missing salary/location/language uncertainty behavior;
- no universal ATS score field/claim.

## Should-I-Apply

Test all result classes with deterministic fixtures where possible and semantic evals where necessary.

Ensure recommendation explains evidence/gaps and does not invent candidate facts.

## Authorization

User A cannot access user B vacancy resources through direct/nested/list/action endpoints.

## SSRF/XSS

Mandatory if corresponding attack surface exists.

# Validation

Run actual:

- backend tests;
- frontend tests/type/lint if changed;
- migrations validation;
- queue/job tests;
- ingestion/matching/eval fixtures;
- OpenAPI validation;
- cross-user negative suite;
- SSRF/XSS security suite if URL/HTML handling exists;
- project required `make test`/lint checks.

Do not claim remote source behavior tested if external call was not actually made or suitable fixture/integration test did not run.

# Documentation

Update:

- Vacancy domain/data docs;
- source adapter/support matrix for actually implemented adapters;
- API/OpenAPI;
- matching/explainability rules;
- should-i-apply semantics;
- security/SSRF ingestion docs;
- AI Skill/eval registry;
- diagrams/data flow if implementation clarified details;
- documentation map/index;
- state.

If external source capability changed from Phase 04 evidence, update research with dated evidence and explicitly document conflict before implementation decision.

# Completion Criteria

Phase 11 PASS only if:

## Domain

- Vacancy implemented;
- VacancySource implemented;
- VacancySnapshot implemented;
- VacancyRequirement implemented;
- user ownership enforced.

## Ingestion

- Manual/Paste works;
- raw snapshot preserved;
- content hashing works;
- exact duplicate behavior defined/tested;
- adapter interface exists;
- only research-permitted URL/source integrations implemented;
- unsupported/restricted source has realistic safe fallback.

## Extraction

- structured/deterministic parsing used where possible;
- semantic extraction uses runtime Skill architecture;
- requirements trace to source evidence;
- no fabricated hidden requirements.

## Matching

- Technical/Experience/Domain/Language/Location/Work format/Salary dimensions implemented to MVP scope;
- match uses confirmed candidate evidence;
- gaps/uncertainties explicit;
- pending facts cannot boost candidate;
- explainability/provenance available.

## Should-I-Apply

- defined result classes implemented;
- result has evidence/reasons/gaps;
- no fake universal ATS score;
- deterministic hard conflicts respected.

## Security

- external content treated as data;
- SSRF controls implemented/tested if URL fetch exists;
- XSS rendering boundary safe;
- cross-user tests pass;
- no secrets forwarded/leaked.

## Validation

- ingestion tests pass;
- requirement extraction fixtures pass;
- matching/evals pass within documented criteria;
- authorization/security tests pass;
- migrations/OpenAPI/project checks pass.

## Scope

- no application package generation;
- no resume tailoring;
- no cover-letter generation;
- no automatic employer submission;
- Phase 12 not started.

# State update

After PASS:

- `STATUS.md`: Phase 11 completed, implemented source methods/adapters, match dimensions, validations/limitations;
- `NEXT.md`: expected `12-application-package`;
- `BLOCKERS.md`: real blockers only.

# Final Report

Concise:

1. Result.
2. Files/migrations/endpoints/Skills/adapters changed.
3. Ingestion methods actually supported.
4. Matching/should-i-apply behavior implemented.
5. External sources explicitly not supported and fallback, if relevant.
6. Validation/security tests actually executed.
7. Blockers/limitations.
8. Exact next phase.

# STOP

After Phase 11 stop.

Do not implement Application Package.
Do not tailor resume or generate cover letter.
Do not auto-apply.
Do not start Phase 12 in the same session.