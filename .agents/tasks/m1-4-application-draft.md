---
title: CVortex M1.4 — Application Draft
status: ready
milestone: m1-first-value
slice: m1-4-application-draft
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [task, application, resume, cover-letter, truth-first]
related:
  - ../../docs/01-Product/Roadmap.md
  - legacy/phase-12-application-package.md
---

# CVortex M1.4 — Application Draft

## Observable outcome

From a previously analyzed vacancy, the user can review truthful resume recommendations and generate short/standard cover drafts. Every candidate assertion is traceable to valid confirmed evidence, user edits are revalidated, and the user explicitly approves/rejects content.

Passing this slice is **Preview 0.1**.

## Scope

Implement only what connects Career Core + Vacancy Core into a useful preparation flow:

- minimal Application/preparation record tied to same-owner Vacancy/Career context;
- structured resume recommendations;
- recommendation review actions;
- candidate-facing generated content + Claim usage/provenance;
- short cover draft;
- standard cover draft;
- Truth Guard integration before approval and after user edits;
- explicit approval state/history sufficient for Preview 0.1;
- UI/API/evals/security required by those flows.

Do not claim `READY_TO_APPLY` yet if deterministic document/package requirements from M2 are not satisfied.

## Resume recommendations

Each recommendation contains:

```text
Before
After
Reason
Evidence
Risk
```

Also retain affected section/item, VacancyRequirement evidence, Claim/CareerFact provenance and user decision.

Actions:

```text
Accept
Edit
Reject
```

AI may rephrase, prioritize, condense or reorder **supported** content. It may not invent skill/experience/date/achievement/responsibility or upgrade adjacent familiarity into direct experience.

User-edited candidate factual content re-enters Truth Guard before approval.

## Cover drafts

Generate two bounded variants for Preview 0.1:

```text
short
standard
```

ContextBuilder uses only:

- current vacancy requirements;
- relevant valid Claims/CONFIRMED Career Facts;
- explicit style/language/settings available in scope.

Do not send the entire Career database.

Candidate assertions trace to Claims. Vacancy/employer-specific statements trace to the vacancy source when used.

Explicit user approval is required; generation alone never marks anything sent/applied.

## Truth Guard

Before content can be approved:

- candidate-specific statement has Claim usage;
- Claim has same-owner valid CONFIRMED evidence;
- no semantic overstatement/invented critical value;
- user edit passes the same checks;
- unresolved `BLOCK` prevents approval;
- valid-evidence ambiguity may require explicit user resolution and revalidation.

Do not persist chain-of-thought as provenance.

## Minimal Application semantics

Introduce only enough lifecycle to save/resume this preparation flow. Avoid pre-building the full M2 application/status CRM.

The system must not mark an application `APPLIED` or imply external submission.

M2 will extend the model with final package readiness, documents, full status history, Employer Memory and manual `Mark as applied`.

## Frontend

Provide an end-to-end review path from vacancy analysis to:

- resume recommendation list/diff;
- evidence/provenance disclosure;
- Accept/Edit/Reject;
- short/standard cover generation;
- cover edit/revalidation;
- explicit approval;
- clear Preview status.

AI output is labelled as recommendation/draft, not confirmed truth.

## Non-goals

Defer to M2:

- DOCX/PDF rendering;
- ResumeTemplate engine;
- final ResumeVersion file artifacts;
- generated-file download;
- basic/full Employer Memory;
- EmployerConsistencyCheck across prior applications;
- BYOK/encrypted provider credential UI;
- full ApplicationStatusHistory;
- `READY_TO_APPLY` package readiness;
- `Mark as applied`.

Defer later:

- job-board submission;
- recruiter messaging;
- interviews/analytics.

## Tests / evals

Cover:

- recommendation introduces no unsupported fact;
- evidence mapping survives generation/review;
- accepted/edit/rejected state behavior;
- user edit with unsupported claim is blocked;
- PENDING/rejected/deprecated fact cannot support content;
- short/standard cover both preserve truth/provenance;
- prompt injection from vacancy source does not alter generation instructions/tools;
- context is owner-scoped and minimized;
- no cross-user Claim reuse;
- generation failure does not create approved state.

## Preview 0.1 completion criteria

A real user can:

- [ ] authenticate through M1.1;
- [ ] establish CONFIRMED career facts through M1.2;
- [ ] paste/analyze a vacancy through M1.3;
- [ ] see explainable match/gaps;
- [ ] review Before/After/Reason/Evidence/Risk recommendations;
- [ ] Accept/Edit/Reject recommendations;
- [ ] generate short and standard truthful cover drafts;
- [ ] edit and approve content only after Truth Guard PASS;
- [ ] resume the saved draft flow;
- [ ] observe no automatic employer/application action.

## State update

On PASS record `Preview 0.1` as validated and set `NEXT.md` to an explicit bounded M2 planning/execution task derived from the actual Preview findings. Do **not** invent a giant M2 spec in this same session.

## STOP

Stop after Preview 0.1 acceptance. Do not start documents, Employer Memory or integrations automatically.
