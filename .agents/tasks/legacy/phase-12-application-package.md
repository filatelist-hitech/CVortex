---
title: CVortex Phase 12 — Application Package
status: ready
phase: 12-application-package
owner: project
created: 2026-09-12
updated: 2026-09-12
tags:
  - task
  - applications
  - resume
  - cover-letter
  - employer-memory
  - truth-first
related:
  - ../../PROJECT.md
  - ../state/STATUS.md
  - ../../docs/03-ADR/INDEX.md
---

# CVortex — Phase 12: Application Package

## Goal

Реализовать end-to-end Application Package workflow: от выбранной вакансии и explainable match до human-approved ResumeVersion, CoverLetter, Employer Memory consistency checks и состояния `READY_TO_APPLY`, без автоматической отправки работодателю.

Phase 12 должна превратить Truth-first Career Foundation и Vacancy Matching в безопасный, traceable и согласованный application package.

Ключевой invariant:

```text
Generated Content
→ Claim usage
→ Claim
→ CONFIRMED Career Fact(s)
```

Любой confirmed contradiction для одного работодателя должен `BLOCK` generation или требовать explicit `USER_RESOLUTION_REQUIRED`.

## Execution mode

Следуй `/AGENTS.md`, scoped instructions, resource policy, accepted ADR, Phase 06 AI/Data/Security design и фактическим implementation contracts Phase 10–11.

Default:

- один агент;
- sequential implementation;
- deterministic rules before AI;
- minimal context per bounded subtask;
- Employer Memory context minimized to relevant company/application facts;
- no subagents unless correctness genuinely requires them;
- narrow tests during iteration;
- final provenance/consistency/document-rendering suite before completion.

## Minimum context

Прочитай:

1. `PROJECT.md`.
2. current state.
3. accepted ADR for Truth Guard, provider independence, deterministic document rendering, multi-user ownership, untrusted content and storage.
4. Phase 06 Product/Application flow, Data/AI/Truth Guard/Employer Memory design.
5. Phase 10 CareerFact/Claim/Truth Guard implementation.
6. Phase 11 Vacancy/Requirement/Match/Should-I-Apply contracts.
7. current document/rendering research and accepted document pipeline decisions only where implementation requires it.
8. current OpenAPI/UI design foundation.

Не перечитывай unrelated research or every historical application artifact.

# Core invariants

## Human approval

CVortex does not automatically send applications.

System may prepare package and mark it `READY_TO_APPLY`, but actual external submission remains manual/user-confirmed unless a later separately accepted phase changes product scope.

No hidden auto-submit/background apply.

## Truth-first

Candidate-facing generated statements must use valid Claims backed by `CONFIRMED` Career Facts.

No generated resume/cover letter/recruiter-facing content may introduce unsupported candidate facts.

## Consistency-first

Before generation for a company, relevant prior employer context must be checked.

Confirmed contradiction is not a cosmetic warning.

Outcomes:

```text
PASS
BLOCK
USER_RESOLUTION_REQUIRED
```

## Provenance

Every candidate-specific generated statement/change must be traceable through Claim usage to confirmed evidence.

## Deterministic rendering

DOCX/PDF artifacts must be generated through the accepted deterministic document pipeline and validated. LLM does not directly «render» binary documents.

# Scope

Implement:

- Company;
- Application;
- ApplicationStatusHistory;
- ResumeTemplate foundation as required by document architecture;
- ResumeVersion;
- ResumeChange/recommendation representation;
- CoverLetter;
- Claim usage;
- Employer Memory;
- EmployerConsistencyCheck;
- application package workflow;
- resume recommendation API/UI;
- cover letter generation/review;
- deterministic DOCX rendering;
- LibreOffice DOCX → PDF conversion;
- PDF/render validation to accepted level;
- provenance/Truth Guard enforcement;
- relevant tests/evals/OpenAPI/docs/state.

# Non-goals

НЕ реализовывать:

- automatic employer/job-board submission;
- browser automation clicking Apply;
- recruiter messaging send action;
- interview module beyond interfaces strictly needed to preserve consistency data;
- broad analytics;
- native mobile;
- fine-tuning;
- generic CRM;
- speculative multi-company organization accounts;
- fake ATS score;
- auto-confirmation of new Career Facts inferred during tailoring;
- hidden mutation of original resume;
- post-Phase-12 features unless strictly required by accepted MVP workflow.

# Company Domain

## Company

Implement company/employer entity according to Phase 06 data model.

Requirements:

- ownership/shared semantics exactly as designed;
- normalized identity without over-merging ambiguous companies;
- source names/aliases where needed;
- links to vacancies/applications/research/contacts only when Phase 12 owns them;
- Employer Memory boundary;
- cross-user isolation for private notes/context.

Do not assume two similarly named employers are identical without deterministic/source evidence or explicit user resolution.

# Application Domain

## Application

Application is user-owned record tying together company, vacancy and application package lifecycle.

Minimum concerns:

- owner;
- company;
- vacancy;
- CareerTrack/profile context used;
- match result/version used;
- current status;
- selected ResumeVersion;
- selected CoverLetter;
- package readiness;
- timestamps;
- provenance/audit references;
- manual applied-at confirmation.

Do not mark `APPLIED` merely because package was generated.

## Application Status History

Status transitions must be explicit, validated and historical.

Define state machine according to Phase 06 requirements. At minimum support states needed for:

- drafting/preparing;
- blocked/user resolution;
- ready to apply;
- manually confirmed applied;
- later outcome statuses only if already in MVP requirements.

Every transition:

- authorized server-side;
- actor/timestamp recorded;
- previous state retained;
- invalid transitions rejected.

No LLM decides state transitions directly.

# Application Preparation Workflow

Implement core flow:

```text
vacancy
→ requirements
→ match / should-i-apply
→ create/select Application
→ gather relevant CONFIRMED Career Facts + valid Claims
→ EmployerConsistencyCheck
→ resume recommendations
→ Truth Guard
→ user review/approval
→ ResumeVersion
→ cover letter generation
→ Truth Guard + EmployerConsistencyCheck
→ user review/approval
→ package validation
→ READY_TO_APPLY
→ manual external application
→ user confirms applied
→ status history
```

Failures/blocks must be explicit and resumable.

# Resume Base / Templates

Use existing resume/Career foundation source according to product design.

Do not mutate source resume in place.

## ResumeTemplate

Implement only template capabilities needed for deterministic MVP rendering.

Requirements:

- versioned template identity;
- deterministic placeholders/sections;
- no hidden AI instructions embedded in document template;
- compatible with accepted DOCX generation strategy;
- clear relationship to generated ResumeVersion;
- stable enough for render regression fixtures.

Do not build a generic WYSIWYG document editor unless MVP explicitly requires it.

# Resume Recommendations

Resume tailoring must produce explicit proposed changes, not silently rewrite user content.

Required representation/UI/API fields:

```text
Before
After
Reason
Evidence
Risk
```

Additionally preserve:

- affected section/item;
- Claim(s) used;
- Career Fact evidence;
- source vacancy requirement(s);
- recommendation status;
- user action/edit history;
- Truth Guard result.

## Recommendation actions

Required actions:

```text
Accept
Edit
Reject
```

`Edit` content must pass Truth Guard before becoming approved candidate-facing content.

Reject must not disappear from history if audit/product design requires retaining decision context.

## Recommendation generation

AI may:

- rephrase supported content;
- prioritize supported skills/achievements;
- reorder/emphasize relevant sections within template rules;
- propose deletion/condensing of irrelevant material;
- map vacancy wording to supported Claims without semantic upgrade.

AI may NOT:

- invent skill/experience/date/achievement;
- convert adjacent familiarity into direct production experience;
- claim responsibility/leadership not confirmed;
- alter salary/availability/location statements without evidence/user action;
- use PENDING facts as established evidence.

# ResumeVersion

Approved output creates immutable/logically versioned ResumeVersion rather than mutating history.

Minimum metadata:

- owner/application;
- base/source resume/template version;
- approved recommendations/change set;
- Claim usage snapshot;
- generated structured resume payload;
- Prompt/Skill/Workflow versions where generation used;
- model run references;
- Truth Guard result;
- approved_by/approved_at;
- generated document file versions;
- hash/version identifiers.

Historical ResumeVersion must remain traceable to evidence valid at generation time.

Later CareerFact edits must not rewrite past ResumeVersion history.

# ResumeChange

Represent changes sufficiently for explainability and audit.

Minimum semantics:

- operation/type;
- before;
- after;
- reason;
- vacancy requirement evidence;
- candidate Claim/evidence;
- risk/uncertainty;
- recommendation/user-edited status;
- approval actor/time.

Do not store unnecessary chain-of-thought.

# Cover Letter

Implement full and short variants only if product requirements specify them; otherwise implement accepted MVP variants.

Cover letter generation must use:

- current Application/Vacancy requirements;
- only relevant approved/valid Claims;
- Employer Memory consistency context;
- user style/language constraints from confirmed/settings sources;
- no unsupported company claims unless backed by company/vacancy research evidence and suitable for use.

## Cover letter provenance

Candidate-specific assertions trace to Claims.

Employer-specific assertions trace to vacancy/company source where applicable.

Generated text has Prompt/Skill/ModelRun references according to AI architecture.

## Human approval

Cover letter must be reviewed/approved before package readiness.

User edits must be revalidated by Truth Guard for candidate-specific factual claims.

# Claim Usage

Implement first-class record of where Claims were used.

Need to answer:

- which Claim appeared in this ResumeVersion/CoverLetter?;
- which exact/semantic content fragment did it support?;
- which confirmed facts supported the Claim at generation time?;
- was the content user-edited?;
- which company/application context used it?;
- was it later invalidated/deprecated?

Claim usage is necessary for Employer Memory and consistency checks.

# Employer Memory

Employer Memory is scoped context for one user + employer/company identity.

It should derive from authoritative/history records, not become an uncontrolled freeform AI memory dump.

Relevant memory may include:

- previous applications;
- Claim usage;
- ResumeVersions;
- CoverLetters;
- recruiter messages when future/imported data exists;
- salary expectations stated to this employer;
- location/work-format statements;
- interview answers/history if available later;
- confirmed corrections/resolutions;
- explicit employer-specific user notes if in scope.

## Context minimization

Do not pass all historical content to LLM by default.

ContextBuilder selects only employer-relevant facts/claims/statements necessary for current generation/consistency check.

Employer Memory must respect user ownership and PII minimization.

# EmployerConsistencyCheck

Implement as explicit validation/orchestration boundary.

It should combine deterministic checks and bounded semantic conflict detection.

## Deterministic checks

Where structured values exist, compare with code/rules:

- salary expectations;
- location;
- work format;
- availability/start date;
- language level;
- dates/durations;
- previously used Claim IDs/status;
- explicit user corrections;
- other structured critical statements.

## Semantic checks

Use semantic validator only where equivalent/contradictory wording cannot be reliably compared deterministically.

Examples:

- «руководил командой» vs prior «не было people management»;
- direct vs adjacent technology experience;
- scope/seniority wording contradictions.

Semantic checker must output structured conflict evidence and uncertainty, not freeform authority.

## Outcomes

```text
PASS
BLOCK
USER_RESOLUTION_REQUIRED
```

Confirmed contradiction cannot downgrade to informational warning.

## Resolution

When `USER_RESOLUTION_REQUIRED`:

- show conflicting statements/evidence;
- user explicitly resolves/corrects source facts or chooses permitted statement;
- resolution is recorded/audited;
- never silently pick the more favorable statement.

# Truth Guard Integration

Truth Guard must validate candidate-facing content at minimum:

- each candidate-specific statement has valid Claim provenance;
- supporting facts `CONFIRMED` and applicable;
- no semantic overstatement;
- dates/durations consistent;
- role/responsibility/achievement supported;
- employer consistency passes;
- critical structured user values not silently changed;
- user edits are revalidated.

Missing valid provenance:

```text
BLOCK
```

Ambiguous conflict:

```text
USER_RESOLUTION_REQUIRED
```

Do not expose internal chain-of-thought to user or persist it as provenance.

# Runtime AI Workflows

Implement using Phase 06 provider-independent architecture.

Likely Skills/Workflows:

- Resume Recommendation Skill;
- Resume Rewrite/Section Generation Skill where needed;
- Cover Letter Skill;
- Employer Consistency Semantic Check Skill;
- Application Preparation Workflow;
- Truth Guard semantic validator where already designed.

Do not collapse everything into one giant prompt.

## ContextBuilder

For each generation select minimum context:

- current vacancy requirements;
- relevant confirmed Career Facts/Claims;
- application/company context;
- relevant Employer Memory statements;
- approved style/language/settings;
- stable trusted system instructions.

Do not send entire career database or all applications to model.

## ModelPolicy

Use logical policies/capabilities from architecture.

Routine structured transformation should not automatically use strongest model. Escalate only on validation failure/semantic complexity according to ModelRouter.

Concrete model mapping remains configuration.

# Application Readiness

Define deterministic readiness criteria for `READY_TO_APPLY`.

At minimum:

- application/vacancy valid;
- selected ResumeVersion approved and document generation valid;
- required CoverLetter approved if application requires it;
- Truth Guard PASS;
- EmployerConsistencyCheck PASS;
- no unresolved blocking recommendation/conflict;
- required generated files available/valid;
- no unsupported pending fact dependency.

LLM cannot set readiness directly.

# Manual Application Confirmation

After package ready, user performs external application manually.

Provide explicit action like `Mark as applied`/equivalent according to UX design.

Record:

- actor;
- timestamp;
- optional external/source metadata if user provides it;
- selected package versions at submission;
- status history.

Do not infer application occurred from opening a URL or downloading PDF.

# Document Pipeline

Use accepted deterministic pipeline:

```text
Structured Resume
→ deterministic DOCX renderer/template
→ DOCX artifact
→ LibreOffice headless
→ PDF artifact
→ validation
```

LLM produces/assists structured content only, not binary rendering.

## Structured Resume

Define schema sufficient for deterministic rendering.

Schema validation must run before DOCX generation.

Do not let arbitrary generated HTML/OOXML become rendering source without controlled template/escaping.

## DOCX

Requirements:

- template/version recorded;
- deterministic mapping from structured fields;
- escaping/safe text insertion;
- no macros/active content;
- reproducible file naming/storage;
- owner/application relation;
- hash/version metadata.

## LibreOffice conversion

Use pinned/validated runtime from accepted implementation decisions.

Requirements:

- headless execution with bounded timeout/resources;
- isolated working directory;
- no user-controlled command arguments/path traversal;
- clear conversion error handling;
- output existence/format validation;
- logs without candidate-content overexposure where possible.

## PDF validation

At minimum validate:

- output generated/non-empty;
- expected PDF signature/type;
- page/render sanity using repository-selected validation strategy;
- no obvious conversion failure;
- artifact linked to source DOCX/version.

If visual/render regression fixtures exist or are required by document research, implement them at the smallest meaningful level.

Do not claim pixel-perfect validation if not actually performed.

# Generated Files / Storage

Every generated document is user-owned/private unless explicitly exported/downloaded.

Requirements:

- server-generated path/name;
- storage abstraction;
- no public predictable URL to private file;
- authorization on download;
- version/hash metadata;
- relation to ResumeVersion/Application;
- safe content-disposition filename;
- no path traversal;
- retention/deletion behavior documented.

# API

Implement/document endpoints for bounded Phase 12 workflows, e.g.:

- companies/applications required by MVP;
- create application from vacancy;
- application detail/status history;
- generate/list resume recommendations;
- accept/edit/reject recommendation;
- build/approve ResumeVersion;
- generate/edit/approve CoverLetter;
- consistency check/resolution;
- package readiness;
- generated document download;
- manual applied confirmation.

Actual endpoint naming follows repository conventions and OpenAPI.

Async generation/rendering should expose explicit operation/status semantics.

Every resource owner enforced server-side.

# Frontend

Implement MVP application preparation experience.

Minimum flows:

## Application workspace

Show:

- vacancy/company;
- match summary;
- package status;
- blocking issues;
- current ResumeVersion/CoverLetter;
- history relevant to decision.

## Resume recommendations

For each:

```text
Before
After
Reason
Evidence
Risk
```

Actions:

```text
Accept
Edit
Reject
```

Show Truth Guard/Employer consistency block clearly.

## Cover letter

- generation state;
- editable draft;
- provenance/evidence affordance where useful;
- approval action;
- blocking validation state.

## Package ready

User can:

- download DOCX/PDF as supported;
- view package versions;
- see readiness criteria;
- manually mark application as submitted/applied.

No auto-submit button disguised as normal action.

Use Phase 07 design foundation and accessibility rules.

# Status / History

ApplicationStatusHistory is append-oriented history.

Do not overwrite previous status event to «fix» history.

Status changes should capture:

- previous/new status;
- actor;
- timestamp;
- reason/metadata only when useful and non-sensitive;
- correlation to package/version if relevant.

# Audit

Audit minimum:

- Application created;
- important status transition;
- recommendation accepted/edited/rejected as required by design;
- ResumeVersion approved;
- CoverLetter approved;
- consistency conflict resolved;
- READY_TO_APPLY reached;
- manually marked applied;
- document artifact generated/replaced where security/audit requires.

No secrets/raw provider credentials in audit.

# Multi-user Authorization

Mandatory ownership checks for:

- Companies where private/user-scoped;
- Applications;
- status history;
- ResumeVersions/Changes/Templates if private;
- CoverLetters;
- Employer Memory;
- Claim usage;
- consistency check runs/results;
- generated files;
- generation/render jobs.

User A cannot access Application package of User B through direct ID, nested route, file download, job/result endpoint or indirect company relationship.

# Security

## Prompt injection

Vacancy/company/recruiter/external research content remains untrusted data.

Employer Memory content is historical data, not system instruction.

Generation prompts must separate trusted instructions from external/historical content.

## XSS

Generated/user-edited rich text and imported vacancy/company content must render safely.

Do not trust model-generated HTML.

## Files

- no user-controlled filesystem path;
- safe filename/content-disposition;
- private authorization;
- renderer process bounded;
- no macros/active payload generation;
- no command injection into LibreOffice invocation.

## PII

ContextBuilder sends only needed candidate/employer information.

Logs/evals should use IDs/metadata where raw content unnecessary.

## Secrets

Provider credentials handled through Phase 09 secure foundation/runtime provider architecture.

No credentials in document output, prompts beyond required provider transport, audit or logs.

# Deterministic Before AI

Must be deterministic where possible:

- ownership;
- status transitions;
- readiness criteria;
- provenance existence/linkage;
- fact status checks;
- exact structured contradictions;
- salary/date/location/work-format comparison when structured;
- Claim validity status;
- schema validation;
- recommendation approval state;
- document template mapping;
- file paths/hashes;
- DOCX/PDF pipeline orchestration;
- audit events;
- token/cost accounting.

LLM used for:

- semantic relevance/prioritization;
- supported rephrasing;
- cover-letter prose;
- semantic contradiction detection when rules insufficient;
- explanation generation constrained by evidence.

# Migrations

Phase 12 may add domain migrations.

Review minimum:

- user ownership FKs/indexes;
- Company identity/alias semantics;
- Application status/current version constraints;
- history append semantics;
- ResumeVersion immutability/versioning;
- ResumeChange relation;
- CoverLetter versions/approval;
- Claim usage evidence relation;
- Employer Memory derived/source relations;
- consistency check result/version;
- GeneratedFile/FileVersion linkage;
- cascade/restrict/deletion/retention implications;
- rollback/backward compatibility.

Do not delete historical provenance through casual cascade choices.

# Tests

Automated tests/evals are mandatory.

## Application workflow

- create Application from owned vacancy;
- invalid/unowned vacancy rejected;
- status transitions valid/invalid;
- cannot mark READY_TO_APPLY before criteria pass;
- manual applied confirmation creates history;
- no automatic APPLIED transition after generation/download.

## Resume recommendations

- recommendation uses only valid Claims;
- pending/rejected fact cannot support After content;
- Before/After/Reason/Evidence/Risk present;
- Accept creates approved change state;
- Edit revalidates content;
- Reject excluded from final version;
- no silent mutation of source resume;
- recommendation retry/version behavior stable.

## Truth Guard

Fixtures minimum:

- supported content → PASS;
- invented skill → BLOCK;
- invented duration/date → BLOCK;
- leadership overstatement → BLOCK;
- pending fact dependency → BLOCK;
- missing provenance → BLOCK;
- user edit introducing unsupported fact → BLOCK;
- ambiguous semantic case → USER_RESOLUTION_REQUIRED where designed.

## Employer Consistency

Fixtures minimum:

- no prior conflict → PASS;
- same supported Claim reused → PASS;
- structured salary conflict → BLOCK/USER_RESOLUTION_REQUIRED per design;
- office/remote statement conflict;
- date/experience contradiction;
- semantic responsibility/seniority contradiction;
- confirmed correction supersedes earlier statement according to rules;
- cross-company statements do not incorrectly block unrelated employer unless globally contradictory fact rules apply.

## Cover letter

- only valid Claims used;
- employer-specific claims trace to source where required;
- approval required;
- edited draft revalidated;
- Prompt/Skill/ModelRun provenance recorded;
- output language/style constraints honored in eval fixtures.

## Document pipeline

Test:

- structured schema validation;
- deterministic DOCX generation fixture;
- no macro/unsafe path behavior;
- LibreOffice conversion success;
- conversion failure/timeout;
- PDF basic validation;
- file ownership/download authorization;
- server-generated path;
- same ResumeVersion reproducibility expectations according to renderer design.

## Cross-user

User A cannot read/mutate/download user B:

- applications;
- resumes;
- cover letters;
- Employer Memory;
- consistency results;
- generated files;
- generation/render job outputs.

Test nested and direct IDs.

## AI evals

Use schema/invariant/regression fixtures, not exact-string prose as primary success criterion.

Evaluate:

- factual support;
- semantic overstatement;
- employer consistency;
- language constraints;
- recommendation usefulness/relevance at bounded rubric level;
- cost/latency/model-policy comparison where existing eval framework supports it;
- prompt injection fixtures.

# Validation

Run actual:

- backend tests;
- frontend tests/type/lint/build if changed;
- migrations validation;
- queue/job tests;
- Truth Guard/eval suite;
- EmployerConsistencyCheck suite;
- document renderer/conversion tests;
- generated file auth tests;
- OpenAPI validation;
- project required lint/test commands;
- final cross-user negative suite.

If LibreOffice/document environment cannot run, Phase 12 cannot be declared fully complete if deterministic PDF pipeline is mandatory completion criterion.

Do not claim render validation that did not execute.

# Documentation

Update:

- Company/Application domain docs;
- application workflow/status model;
- resume recommendation/approval semantics;
- Claim usage/provenance docs;
- Employer Memory/Consistency docs;
- AI Skills/Workflows/Prompt Registry references;
- OpenAPI;
- document generation/rendering docs;
- security/file handling docs;
- QA/eval fixtures;
- architecture/data-flow diagrams if implementation adds material detail;
- documentation map/index;
- state.

If implementation reveals architecture conflict, do not silently drift from ADR. Use explicit ADR process.

# Completion Criteria

Phase 12 PASS only if:

## Domain

- Company implemented to MVP need;
- Application implemented;
- ApplicationStatusHistory implemented;
- ResumeVersion implemented;
- ResumeChange/recommendation implemented;
- CoverLetter implemented;
- Claim usage implemented;
- Employer Memory implemented;
- EmployerConsistencyCheck implemented.

## Workflow

- Vacancy → Application Preparation flow works;
- recommendations generated/explained;
- user Accept/Edit/Reject works;
- approved ResumeVersion produced;
- CoverLetter generation/review/approval works where required;
- Truth Guard and EmployerConsistencyCheck gate readiness;
- `READY_TO_APPLY` deterministic;
- external submission remains manual;
- applied status requires explicit user confirmation.

## Truth-first

- every candidate-specific final content traces to valid Claim;
- Claim traces to `CONFIRMED` Career Facts;
- pending/rejected/deprecated invalid evidence cannot silently pass;
- user edits revalidated;
- semantic overstatement fixtures blocked;
- missing provenance blocked.

## Consistency-first

- relevant prior company context loaded minimally;
- structured conflicts deterministic;
- semantic conflicts bounded/evidence-linked;
- confirmed contradiction BLOCK or USER_RESOLUTION_REQUIRED;
- user resolution explicit/audited.

## Documents

- structured Resume schema valid;
- deterministic DOCX generation works;
- LibreOffice conversion works;
- PDF validation runs;
- generated file ownership secure;
- versions/hashes/provenance linked.

## Security

- prompt injection separation preserved;
- generated/imported content rendered safely;
- file path/command injection protections in renderer;
- cross-user tests pass;
- PII/context minimized;
- secrets not leaked.

## Validation

- workflow tests pass;
- Truth Guard suite passes;
- Employer Consistency suite passes;
- document pipeline tests pass;
- authorization tests pass;
- OpenAPI/project checks pass;
- no validation falsely claimed.

## Scope

- no automatic application submission;
- no browser automation auto-apply;
- no unsupported future modules implemented.

# State update

After PASS:

- `STATUS.md`: Phase 12 completed, application package capabilities, document pipeline, tests/evals, limitations;
- `NEXT.md`: set only to the actual next bounded phase if repository roadmap defines one; do not invent Phase 13;
- `BLOCKERS.md`: real blockers only.

If no canonical next phase exists, document that planning/review is required instead of fabricating a name.

# Final Report

Concise:

1. Result.
2. Files/migrations/endpoints/Skills/Workflows changed.
3. Application package workflow outcome.
4. Truth Guard/Employer Consistency behavior.
5. DOCX/PDF pipeline actually validated.
6. Tests/evals/security checks actually executed.
7. Blockers/limitations.
8. Exact next task/phase only if repository defines it.

# STOP

After Phase 12 stop.

Do not auto-apply to employers.
Do not create a Phase 13 by assumption.
Do not begin unrelated analytics/interview/native-mobile work in this session.
Do not expand scope after completion criteria pass.