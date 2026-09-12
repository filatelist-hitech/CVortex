---
title: CVortex M1.2 — Career Core
status: ready
milestone: m1-first-value
slice: m1-2-career-core
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [task, career, truth-first, provenance, ai]
related:
  - ../../docs/01-Product/Roadmap.md
  - ../../docs/04-Data/Phase-06-Data-Design.md
  - ../../docs/06-AI/Phase-06-AI-Design.md
  - legacy/phase-10-career-foundation.md
---

# CVortex M1.2 — Career Core

## Observable outcome

An authenticated user can paste career/resume text, receive structured `PENDING` fact candidates, review source evidence, Confirm/Edit/Reject them and end with a usable set of `CONFIRMED` Career Facts/Claims for matching.

Manual fact entry remains available even when no LLM provider is configured.

## Core invariant

```text
source text
→ extraction
→ PENDING fact candidate
→ explicit human review
→ CONFIRMED Career Fact
→ Claim
```

LLM/tool output never confirms a Career Fact.

Candidate-facing Claims require same-owner `CONFIRMED` evidence and provenance. Missing/invalid provenance fails closed.

## Scope

Implement only the minimum Career/Truth/AI foundation needed by the observable flow:

- CareerProfile;
- CareerFact lifecycle `PENDING/CONFIRMED/REJECTED/DEPRECATED`;
- source/provenance record for pasted career text;
- Claim + ClaimEvidence foundation;
- manual fact entry;
- paste-text extraction workflow;
- human review UI/API;
- Truth Guard checks needed for Claim validity;
- minimal provider-independent runtime AI plumbing used by extraction;
- LLM run metadata/eval fixtures required for this Skill.

CareerTrack/EmploymentHistory may be introduced only when the chosen fact model genuinely needs structured grouping for this slice. Do not build every conceptual Career entity by default.

## AI implementation

Use `/runtime-ai/` for canonical Skill/prompt assets.

Implement provider-independent contracts at the smallest useful level:

```text
LlmProvider
ModelPolicy
Skill/SkillVersion
PromptVersion
bounded extraction workflow
schema validation
LlmRun metadata
```

The first concrete provider adapter may be OpenAI if current official API/capability research confirms the implementation choice. Domain code must depend only on the provider-independent boundary and logical model policy.

For M1, system-managed provider credentials may come from backend environment configuration. BYOK/persisted encrypted provider credentials are M2.

Structured outputs/schema validation are required where supported. Source text is untrusted data, never instructions.

## Fact semantics

Every fact carries enough metadata to answer:

- what is asserted?;
- where did it come from?;
- what source excerpt supports it?;
- who/what extracted it?;
- current lifecycle state;
- who confirmed/rejected/edited it and when?;
- whether a confirmed value was superseded.

Extraction confidence is metadata, not confirmation.

Do not silently infer missing dates, responsibility, seniority, technology depth or commercial experience.

No semantic upgrade, e.g. familiarity → production experience or participation → leadership.

## User actions

Pending review supports:

```text
Confirm
Edit and Confirm
Reject
Leave Pending
```

Editing must preserve original provenance/source wording and record the human-approved value.

Manual user-created facts may use an explicit human-confirmed entry path with `user_manual` provenance. They do not need to pass through AI just for architectural symmetry.

## Truth Guard foundation

Deterministic checks include:

- owner consistency;
- valid CareerFact state;
- provenance exists;
- ClaimEvidence links only same-owner CONFIRMED facts;
- rejected/deprecated/pending evidence is not usable;
- structured contradictions/date upgrades where code can detect them.

Outcomes:

```text
PASS
BLOCK
USER_RESOLUTION_REQUIRED
```

Missing evidence/provenance is `BLOCK`, not a soft warning.

## Frontend

Create only:

- Career overview sufficient for First Value;
- paste career/resume text;
- extraction status/error;
- pending fact review with evidence;
- Confirm/Edit/Reject;
- confirmed fact list;
- minimal Claim visibility where needed to explain later matching.

Never style `PENDING` as confirmed. Use the Phase 07 state/evidence patterns.

## Non-goals

Defer to M3 or later:

- DOCX/PDF upload;
- file parser stack;
- OCR;
- complex EmploymentHistory editor;
- all possible Education/Language/Achievement tables unless needed by current fact schema;
- vacancy matching;
- resume tailoring;
- cover letters;
- Employer Memory;
- vector DB/RAG.

## Security

- all Career resources owner-scoped server-side;
- source text is untrusted and prompt-injection resistant;
- no private Career text in normal logs/audit;
- minimal provider context;
- no credentials in LLM run records;
- synthetic test fixtures only.

## Tests / evals

Cover:

- lifecycle transitions;
- extraction creates `PENDING` only;
- no auto-confirm path;
- manual confirm/edit/reject;
- provenance required;
- Claim cannot use non-confirmed/cross-user facts;
- semantic-upgrade adversarial fixtures;
- prompt injection inside pasted career text;
- malformed/schema-invalid model output;
- retry/idempotency does not duplicate facts;
- cross-user Career access blocked.

## Completion criteria

- [ ] user can paste career text and start extraction;
- [ ] extraction creates only PENDING facts with evidence;
- [ ] user can Confirm/Edit/Reject;
- [ ] confirmed facts are queryable for later matching;
- [ ] valid Claims trace to CONFIRMED same-owner facts;
- [ ] Truth Guard fails closed on missing/invalid evidence;
- [ ] manual fact entry works without LLM;
- [ ] provider-specific SDK types do not leak into domain logic;
- [ ] BYOK/file import did not leak into scope;
- [ ] tests/evals/docs/state pass.

## State update

On PASS: `NEXT.md = m1-3-vacancy-core`.

## STOP

Do not implement vacancy ingestion/matching in this task.
