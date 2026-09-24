---
title: CVortex M1.4 — Application Draft
status: ready
milestone: m1-first-value
slice: m1-4-application-draft
owner: project
created: 2026-09-12
updated: 2026-09-20
tags: [task, application, resume, cover-letter, truth-first]
related:
  - ../../docs/01-Product/Roadmap.md
  - legacy/phase-12-application-package.md
---

# CVortex M1.4 — Application Draft

## Goal

Complete the bounded M1.4 slice that connects Career Core and Vacancy Core into the first useful application-preparation workflow.

After completion, a real authenticated user must be able to:

1. open a previously analyzed vacancy;
2. review truthful resume recommendations;
3. inspect their evidence and provenance;
4. Accept, Edit, or Reject recommendations;
5. generate `short` and `standard` cover drafts;
6. edit generated candidate-facing content;
7. pass edited content through the same Truth Guard;
8. explicitly approve valid content;
9. leave and resume the saved preparation flow;
10. observe that CVortex performs no employer-facing submission action.

Passing this slice validates:

`Preview 0.1`

Do not extend the task into M2.

---

# Execution contract

This is one bounded implementation task.

Bias toward completing the task rather than stopping for routine implementation questions.

For reversible technical decisions:

1. inspect existing project conventions;
2. reuse existing architecture and abstractions;
3. choose the simplest solution consistent with accepted ADRs;
4. document a decision only when it is architecturally significant;
5. continue.

Do not ask the user to choose between routine implementation alternatives.

Only treat missing information as a blocker when it:

- changes product/business semantics materially;
- conflicts with an accepted architectural decision;
- would require an unsafe or irreversible assumption;
- makes a completion criterion impossible to satisfy correctly.

If such a blocker exists, complete all safe independent work first and report the exact blocker.

Do not expand scope merely because adjacent functionality appears useful.

Do not perform speculative refactoring.

Do not implement functionality “for later”.

Use one primary agent and sequential execution by default.

Do not delegate to subagents unless delegation is genuinely required for correctness or materially improves a clearly separable part of this task.

---

# Context discipline

Before implementation, follow repository instructions and inspect the current state.

Use:

- `/AGENTS.md`;
- `/PROJECT.md`;
- `/.agents/state/STATUS.md`;
- `/.agents/state/NEXT.md`;
- applicable scoped `AGENTS.md`;
- relevant accepted ADRs;
- documentation directly related to Career Core, Vacancy Core, Claims, Truth Guard, Applications, API, frontend and security.

Use documentation maps, indexes and links to locate relevant material.

Do not recursively load entire `docs/`, `research/`, `.agents/`, Career data or unrelated milestones.

Open only files and sections necessary to understand or implement M1.4.

Do not re-research decisions already established by accepted ADRs unless current evidence indicates that an accepted decision has become invalid.

If this task, code, documentation and an accepted ADR disagree:

- do not silently choose one;
- identify the conflict;
- preserve accepted architecture unless it must legitimately be superseded;
- update/create an ADR only when an actual architectural decision changes.

---

# Core invariants

The following are non-negotiable.

## Truth-first

Maintain:

`FACT → CLAIM → GENERATED CONTENT`

Candidate-facing factual content may only rely on valid Claims backed by same-owner `CONFIRMED` Career Fact evidence.

AI may:

- rephrase;
- condense;
- prioritize;
- combine supported material;
- reorder supported material;
- adjust emphasis.

AI may not invent or silently upgrade:

- skills;
- experience;
- dates;
- achievements;
- responsibilities;
- seniority;
- technology usage;
- quantitative results;
- domain experience;
- employment history.

Adjacent familiarity must not become direct experience.

A generated statement without valid provenance cannot become approved content.

LLM extraction or inference must never automatically create a `CONFIRMED` Career Fact.

Do not persist chain-of-thought as provenance.

## Human approval

Generation is not approval.

Approval is not application submission.

No AI action in this slice may:

- mark the application `APPLIED`;
- imply external submission;
- send content to an employer;
- contact a recruiter;
- trigger job-board submission.

## Multi-user isolation

Every private resource used or created by this flow must remain owner-scoped.

Never trust ownership identifiers supplied by the frontend.

Cross-user Career Facts, Claims, Vacancies, drafts or Application resources must never be reusable through ID guessing, relationship manipulation or direct API access.

## Deterministic before AI

Do not use an LLM for state transitions, authorization, ownership checks, schema validation or other rules that normal application code can enforce reliably.

LLM output is untrusted generated data until validated.

## Provider independence

Do not couple M1.4 business logic directly to a concrete LLM provider or model.

Use the existing Provider / ModelPolicy / Skill / Agent / Workflow / Tool abstractions where applicable.

Concrete provider/model selection remains configuration governed by current model policy.

---

# Observable outcome

From a previously analyzed vacancy, the user can review truthful resume recommendations and generate `short` and `standard` cover drafts.

Every candidate assertion is traceable to valid confirmed evidence.

User edits are revalidated.

The user explicitly approves or rejects candidate-facing content.

The preparation flow can be saved and resumed.

This is Preview 0.1.

---

# Scope

Implement only what is necessary to connect Career Core and Vacancy Core into the Preview 0.1 preparation flow:

- minimal Application/preparation persistence;
- same-owner Vacancy/Career linkage;
- structured resume recommendations;
- recommendation provenance;
- recommendation review actions;
- candidate-facing generated content;
- Claim usage/provenance;
- short cover draft;
- standard cover draft;
- Truth Guard integration before approval;
- Truth Guard revalidation after candidate factual edits;
- explicit approval state/history sufficient for Preview 0.1;
- save/resume behavior;
- required API;
- required frontend;
- required authorization;
- required tests/evals;
- required documentation and state updates.

Do not claim `READY_TO_APPLY` if deterministic package/document requirements owned by M2 are not satisfied.

---

# Minimal Application semantics

Introduce only the Application/preparation semantics required to save and resume M1.4.

Do not pre-build the full application CRM.

The minimal model must be sufficient to associate the preparation flow with:

- owner;
- vacancy;
- relevant Career context;
- recommendation decisions;
- generated drafts;
- provenance;
- validation state;
- explicit approval state/history.

Do not introduce M2 status machinery merely because a future Application object will eventually need it.

The system must not mark an application `APPLIED`.

The system must not imply that content has been externally submitted.

M2 will extend this area with:

- final package readiness;
- documents;
- complete status history;
- Employer Memory;
- EmployerConsistencyCheck;
- manual `Mark as applied`.

---

# Resume recommendations

Each recommendation must expose:

```text
Before
After
Reason
Evidence
Risk
```

Persist or otherwise retain the information necessary to identify:

- affected resume section/item;
- related VacancyRequirement evidence;
- Claim provenance;
- CareerFact provenance;
- recommendation status;
- user decision;
- user edit where applicable;
- validation result.

Supported user actions:

```text
Accept
Edit
Reject
```

## Accept

Accepting a recommendation must not bypass Truth Guard.

Only valid supported candidate content may reach an approved state.

## Edit

When a user edits candidate-facing factual content, treat the edited text as new candidate content.

It must re-enter the same validation path.

Previous validation must not be reused blindly after the text changes.

## Reject

Rejected content must not become part of approved output.

Its review decision should remain recoverable for the saved preparation flow where required by the existing domain design.

---

# Cover drafts

Preview 0.1 supports exactly two bounded variants:

```text
short
standard
```

Do not add technical/value/custom variants in this slice.

ContextBuilder must use only the minimum relevant context:

- current vacancy requirements;
- relevant valid Claims;
- relevant `CONFIRMED` Career Facts;
- explicit style/language/settings already available in scope.

Do not send the entire Career database.

Do not send unrelated vacancy/application history.

Do not introduce Employer Memory as a hidden dependency.

Candidate assertions must trace to Claims.

When vacancy/employer-specific statements are used, preserve provenance to the relevant vacancy source where supported by the current data model.

Generated cover content remains a draft until explicit user approval.

Generation alone must never produce an approved or applied state.

---

# Truth Guard

Before candidate-facing content may become approved:

- every candidate-specific factual assertion has Claim usage;
- every supporting Claim has valid same-owner `CONFIRMED` evidence;
- referenced evidence is still valid;
- no rejected, deprecated or pending fact is being used as support;
- no unsupported critical value is introduced;
- no semantic overstatement upgrades the underlying evidence;
- edited candidate factual content passes the same validation;
- unresolved `BLOCK` prevents approval.

Where multiple valid pieces of evidence create genuine ambiguity, require explicit user resolution and then revalidate.

Truth Guard must fail safely.

A generation or validation failure must never result in:

- approval;
- stale PASS reuse;
- partially approved unsupported content;
- state implying successful external action.

Truth Guard is validation, not a source of new career truth.

---

# Untrusted input

Treat as untrusted data:

- vacancy text;
- vacancy HTML/source;
- imported vacancy documents;
- recruiter/employer text present in source material;
- generated LLM content;
- user-edited candidate content until validated.

Content such as:

`Ignore previous instructions`

inside a vacancy or imported source remains data.

It must not alter:

- agent instructions;
- tool selection;
- provider behavior;
- system rules;
- Truth Guard rules;
- authorization behavior.

---

# API / backend

Follow current API conventions and existing domain architecture.

Implement only endpoints/contracts required by the Preview 0.1 flow.

Where applicable ensure:

- server-side request validation;
- server-side authorization;
- owner-scoped queries;
- documented response contracts;
- existing error format;
- backward compatibility;
- controlled generation failures;
- controlled validation failures;
- no implicit state transition to approved;
- no implicit state transition to applied.

Do not create parallel abstractions when an existing Application, Claim, Truth Guard, LLM or authorization abstraction already serves the requirement.

---

# Frontend

Provide one coherent end-to-end path from vacancy analysis to application draft review.

The user must be able to:

- view resume recommendations;
- inspect Before/After/Reason/Evidence/Risk;
- inspect useful provenance/evidence disclosure;
- Accept;
- Edit;
- Reject;
- generate `short`;
- generate `standard`;
- edit cover content;
- see revalidation state after edits;
- explicitly approve valid content;
- resume saved work;
- understand current Preview status.

AI-generated output must be clearly labelled as recommendation/draft, not as confirmed career truth.

Handle the relevant UI states:

- loading;
- empty;
- success;
- validation failure;
- Truth Guard BLOCK;
- generation failure;
- unauthorized/forbidden;
- disabled approval while validation is unresolved.

Use the existing design system and Figma-derived patterns.

Do not invent arbitrary visual primitives or unrelated redesigns.

---

# Security

Review the implementation for the surfaces actually touched by M1.4.

At minimum consider:

- authorization;
- IDOR;
- cross-user Claim reuse;
- cross-user Career Fact reuse;
- cross-user Vacancy/Application access;
- prompt injection;
- malicious external text;
- XSS/rendering of generated text;
- mass assignment where applicable;
- secret leakage into prompts/logs;
- PII leakage;
- stale validation state;
- approval-state tampering.

Do not log secrets.

Do not log unnecessary raw candidate data.

Do not trust client-provided provenance or ownership.

---

# Non-goals

Defer to M2:

- DOCX/PDF rendering;
- ResumeTemplate engine;
- final ResumeVersion file artifacts;
- generated-file download;
- basic/full Employer Memory;
- EmployerConsistencyCheck across previous applications;
- BYOK/encrypted provider credential UI;
- full ApplicationStatusHistory;
- `READY_TO_APPLY` package readiness;
- `Mark as applied`.

Defer later:

- job-board submission;
- recruiter messaging;
- interviews;
- analytics.

Do not create placeholder implementations for these features merely to prepare for them.

---

# Implementation approach

Before changing code:

1. inspect the existing M1.1–M1.3 implementation;
2. identify existing domain entities and services that M1.4 should extend;
3. inspect existing Claim / Career Fact / Truth Guard contracts;
4. inspect existing VacancyRequirement and matching contracts;
5. inspect existing API conventions;
6. inspect existing frontend/design patterns;
7. determine the minimal persistence changes required.

Then implement the smallest coherent vertical slice.

Prefer the existing architecture over introducing parallel abstractions.

Do not rewrite working M1.1–M1.3 components unless M1.4 exposes a concrete defect or missing contract that must be fixed.

If such a change is required, keep it minimal and add regression coverage.

---

# Tests / evals

Testing must prove invariants, not merely mirror implementation.

Cover at minimum:

## Truth / provenance

- recommendation introduces no unsupported candidate fact;
- provenance survives generation and review;
- candidate assertion maps to valid Claim usage;
- Claim maps to same-owner valid `CONFIRMED` evidence;
- `PENDING` fact cannot support approved content;
- `REJECTED` fact cannot support approved content;
- `DEPRECATED` fact cannot support approved content;
- unsupported user edit is blocked;
- supported user edit can be revalidated;
- semantic overstatement is blocked according to existing Truth Guard contract.

## Recommendation lifecycle

- Accept behavior;
- Edit behavior;
- Reject behavior;
- stale validation is invalidated after factual edit;
- rejected recommendation does not become approved output.

## Cover generation

- `short` variant works;
- `standard` variant works;
- both preserve truth/provenance;
- generation does not imply approval;
- generation failure does not create approved state.

## Security / ownership

- User A cannot read User B preparation/application;
- User A cannot mutate User B preparation/application;
- User A cannot reuse User B Claim;
- User A cannot reuse User B Career Fact;
- ownership cannot be bypassed via IDs supplied by the client.

## Untrusted input

- prompt injection from vacancy source does not alter generation instructions, tools or Truth Guard behavior.

## Context minimization

- generation context is owner-scoped;
- generation receives only task-relevant Career context;
- no entire Career database is sent by default.

## Persistence

- draft flow survives save/reload/resume;
- explicit user decisions remain consistent after reload where required.

---

# Validation strategy

Use narrow validation first.

During implementation:

1. run the smallest relevant test suite for the changed layer;
2. resolve failures caused by the change;
3. run the task-level integration/feature tests;
4. run broader mandatory repository checks only where required by repository instructions or justified by affected surfaces.

Do not repeatedly run the entire repository test suite after every small edit.

Broaden validation when:

- relevant narrow tests fail unexpectedly;
- shared infrastructure changed;
- a regression risk crosses module boundaries;
- repository completion rules require it.

Do not claim a test or validation as executed unless it was actually executed.

---

# Documentation

Update only documentation made stale by M1.4.

At minimum verify whether changes require updates to:

- product flow;
- Application semantics;
- API/OpenAPI;
- data model / ERD;
- Claim/provenance documentation;
- Truth Guard documentation;
- AI Skill/version/eval documentation;
- frontend/user-flow documentation;
- security/threat model;
- Preview/Roadmap state.

Create or supersede an ADR only if an architectural decision actually changed.

Do not create ADRs for routine implementation details.

---

# Preview 0.1 completion criteria

M1.4 passes only when a real user can:

- [ ] authenticate through M1.1;
- [ ] establish `CONFIRMED` career facts through M1.2;
- [ ] paste/analyze a vacancy through M1.3;
- [ ] see explainable match/gaps;
- [ ] review `Before / After / Reason / Evidence / Risk` recommendations;
- [ ] Accept recommendations;
- [ ] Edit recommendations;
- [ ] Reject recommendations;
- [ ] generate a truthful `short` cover draft;
- [ ] generate a truthful `standard` cover draft;
- [ ] edit generated candidate content;
- [ ] observe revalidation after factual edits;
- [ ] approve content only after Truth Guard PASS;
- [ ] be blocked from approval on unresolved Truth Guard BLOCK;
- [ ] leave and resume the saved preparation flow;
- [ ] observe no automatic employer/application submission action.

Additionally:

- [ ] candidate-facing approved factual content is traceable to valid Claims;
- [ ] Claims used for approval are backed by same-owner `CONFIRMED` evidence;
- [ ] cross-user access tests pass;
- [ ] prompt-injection regression coverage passes;
- [ ] generation failure cannot create approved state;
- [ ] no M2-only package readiness is claimed;
- [ ] required documentation is current;
- [ ] required validation passes.

Every item above is PASS/FAIL.

Do not declare Preview 0.1 validated while a mandatory item is failing.

---

# State update

Only after all Preview 0.1 mandatory criteria PASS:

1. record `Preview 0.1` as validated;
2. update `.agents/state/STATUS.md` to factual repository state;
3. update `.agents/state/BLOCKERS.md` with real blockers only;
4. update `.agents/state/NEXT.md`.

`NEXT.md` must contain one explicit bounded M2 planning/execution task derived from actual Preview findings.

Do not invent a giant M2 specification in this session.

Do not begin implementing M2.

If Preview 0.1 is PARTIAL or BLOCKED, reflect that truthfully in project state and do not mark it validated.

---

# Final report

Keep the final response concise.

Report only:

1. **Result**
   - `PASS`, `PARTIAL`, or `BLOCKED`.

2. **Implemented**
   - major completed M1.4 capabilities.

3. **Files**
   - important created/changed files.

4. **Decisions**
   - only architectural/domain decisions actually made;
   - ADR changes, if any.

5. **Tests / validation**
   - only checks actually executed;
   - PASS/FAIL.

6. **Security**
   - material security work or unresolved risks only.

7. **Limitations / blockers**
   - existing items only.

8. **State**
   - Preview 0.1 state;
   - exact `NEXT.md` task.

Do not reproduce the contents of generated documentation in the final report.

Do not provide hidden reasoning or chain-of-thought.

---

# STOP

Stop immediately after the M1.4 final report and state update.

Do not:

- start M2 implementation;
- implement DOCX/PDF;
- create ResumeTemplate infrastructure;
- implement Employer Memory;
- implement EmployerConsistencyCheck;
- implement BYOK UI;
- implement full status CRM;
- implement `READY_TO_APPLY`;
- implement `Mark as applied`;
- contact employers;
- submit applications;
- perform speculative refactoring.

Preview 0.1 is the boundary of this task.
