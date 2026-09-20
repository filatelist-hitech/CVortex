---
title: CVortex M1.3 — Vacancy Core
status: changes-required-remediation-awaiting-review
milestone: m1-first-value
slice: m1-3-vacancy-core
owner: project
created: 2026-09-12
updated: 2026-09-19
tags: [task, vacancy, matching, truth-first, ai]
related:
  - ../../docs/01-Product/Roadmap.md
  - legacy/phase-11-vacancies-matching.md
---

# CVortex M1.3 — Vacancy Core

The original independent review verdict is `CHANGES REQUIRED`. M1.3R remediation is implemented and must pass `.agents/tasks/m1-3r-vacancy-core-remediation-review.md` before this task can return to completed/PASS or authorize M1.4.

## Observable outcome

An authenticated user pastes a real vacancy and receives an explainable requirement set, match/gap analysis and `STRONGLY_APPLY / APPLY / MAYBE / LOW_PRIORITY / SKIP` recommendation backed only by confirmed candidate evidence.

## Input boundary

M1.3 supports **pasted vacancy text only** plus optional user-entered source URL as metadata.

Do not fetch that URL in this slice.

Vacancy text is untrusted data and is preserved as a user-owned source snapshot/version before semantic processing.

## Scope

Implement minimum:

- Vacancy;
- VacancySnapshot/source metadata;
- VacancyRequirement;
- deterministic content hash/exact duplicate handling;
- pasted-text ingestion;
- semantic requirement extraction Skill;
- matching/gap analysis against current valid Career Facts/Claims;
- explainable per-dimension result;
- should-I-apply classification;
- API/UI/status/error paths;
- provenance and LLM run/eval metadata.

Do not create adapters for every researched job source.

## Requirement extraction

Separate deterministic preprocessing from semantic extraction.

The extraction Skill returns structured requirements with source evidence and uncertainty. It must distinguish mandatory/preferred where wording supports it and must not convert employer marketing text into hidden candidate requirements.

`will be a plus` is not mandatory.

Prompt-injection text inside a vacancy cannot alter system/tool behavior.

## Matching

Candidate-side evidence may use only same-owner `CONFIRMED` Career Facts and valid Claims.

`PENDING` facts never improve match.

Required explainable dimensions:

```text
Technical
Experience
Domain
Language
Location
Work format
Salary
```

A dimension may be unknown/not-applicable when source or candidate data is absent.

Deterministic comparisons are code where structured values exist, such as location/work format/salary compatibility and computable durations. Semantic models may assist adjacent-domain/technology reasoning but cannot upgrade familiarity into commercial/direct experience.

Do not present a universal ATS probability/percentage.

## Gap analysis

Classify relevant gaps, for example:

- hard requirement unsupported;
- preferred requirement unsupported;
- adjacent/weak candidate evidence;
- unknown because Career data is incomplete;
- structured incompatibility.

Allowed advice includes clarifying a missing Career Fact through the Career flow or emphasizing adjacent confirmed evidence. Never recommend inventing a fact.

## Should-I-Apply

Output:

```text
STRONGLY_APPLY
APPLY
MAYBE
LOW_PRIORITY
SKIP
```

Include key reasons, strongest confirmed matches, material gaps, deterministic blockers and uncertainties. Do not claim interview probability.

Traceability:

```text
recommendation
→ match dimensions
→ vacancy requirements
→ vacancy snapshot
```

Candidate side:

```text
match evidence
→ Claim/CareerFact
→ CONFIRMED provenance
```

## Frontend

Build only:

- Add vacancy → Paste text;
- import/extraction progress/error;
- normalized vacancy/requirements view;
- source wording/evidence disclosure;
- match dimensions;
- gap analysis;
- should-I-apply recommendation.

The UI clearly distinguishes source text, CVortex inference, confirmed candidate evidence, unknown data and deterministic blockers.

## Non-goals

Defer to M3:

- URL/server fetching;
- HH/Greenhouse/Lever/Workday/etc adapters;
- generic web parser;
- SSRF network boundary;
- uploaded vacancy files;
- browser extension.

Defer to M1.4/M2:

- resume tailoring;
- cover letters;
- Application/Employer Memory;
- DOCX/PDF.

## Security

- owner-scope all Vacancy resources/runs;
- render imported text safely; no trusted raw HTML;
- prompt-injection adversarial tests;
- size limits on pasted input;
- no external network call from user-supplied URL;
- no cross-user Career context in ContextBuilder.

## Tests / evals

Cover:

- source snapshot/hash/exact duplicate behavior;
- preferred vs mandatory extraction;
- marketing-noise vacancy;
- prompt injection;
- exact technology match;
- adjacent technology without production evidence;
- incomplete experience duration;
- language/location/work-format/salary unknown/conflict;
- PENDING candidate fact excluded;
- cross-user evidence impossible;
- changed career facts make prior analysis detectably stale if versioning contract supports it.

## Completion criteria

- [ ] pasted vacancy is preserved and analyzed;
- [ ] requirements have source evidence;
- [ ] match uses only valid confirmed candidate evidence;
- [ ] all seven dimensions are represented with unknown/N/A where appropriate;
- [ ] gaps and recommendation are explainable;
- [ ] no fake ATS score;
- [ ] no URL fetching/adapters were introduced;
- [ ] security/evals/authorization tests pass;
- [ ] docs/API/state updated.

## State update

On PASS: `NEXT.md = m1-4-application-draft`.

## STOP

Do not implement resume/cover generation in this task.
