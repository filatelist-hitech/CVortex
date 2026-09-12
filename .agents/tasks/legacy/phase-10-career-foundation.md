---
title: CVortex Phase 10 — Career Foundation
status: ready
phase: 10-career-foundation
owner: project
created: 2026-09-12
updated: 2026-09-12
tags:
  - task
  - career
  - provenance
  - truth-first
  - ai
related:
  - ../../PROJECT.md
  - ../state/STATUS.md
  - ../../docs/03-ADR/INDEX.md
---

# CVortex — Phase 10: Career Foundation

## Goal

Реализовать Career Foundation как truth-first основу CVortex, на которой последующие vacancy matching, resume tailoring, cover letters и Employer Memory смогут работать без выдумывания фактов о кандидате.

Phase 10 должна реализовать:

- CareerProfile;
- CareerTrack;
- EmploymentHistory;
- CareerFact;
- provenance;
- lifecycle `PENDING / CONFIRMED / REJECTED / DEPRECATED`;
- resume import foundation;
- fact extraction;
- human confirmation workflow;
- Truth Guard foundation;
- Claim Registry foundation;
- invariant/eval fixtures.

Ключевой результат: candidate-specific statement не может стать допустимым generated claim без traceability к `CONFIRMED` Career Facts.

## Execution mode

Следуй `/AGENTS.md`, scoped backend/frontend instructions, resource policy, accepted ADR и Phase 06 Truth/Data/AI design.

Default:

- один агент;
- sequential implementation;
- deterministic validation first;
- minimal context for each implementation block;
- LLM only where semantic extraction is required;
- no broad provider/model research unless current integration capability genuinely blocks implementation;
- targeted tests during iteration, final invariant suite before completion.

## Minimum context

Прочитай:

1. `PROJECT.md`.
2. current state.
3. accepted ADR for Truth Guard/provenance, provider independence, multi-user ownership, untrusted content, storage/API.
4. Phase 06 data model, AI architecture, Truth Guard, provenance and security docs.
5. Phase 09 ownership/auth implementation conventions.
6. current OpenAPI/data docs and relevant runtime AI Skills canonical structure.
7. only technical research needed for current parser/provider capability choices.

Не загружай все Phase 03–04 artifacts.

# Core invariants

Эти invariants обязательны и имеют приоритет над convenience implementation:

```text
SOURCE
→ EXTRACTED CANDIDATE FACT (PENDING)
→ HUMAN CONFIRMATION
→ CONFIRMED CAREER FACT
→ CLAIM
→ GENERATED CONTENT
```

## Invariant 1 — AI cannot confirm facts

LLM/extractor может создать только `PENDING` fact candidate.

Ни один model output, confidence score, parser result или tool result не может автоматически перевести CareerFact в `CONFIRMED`.

## Invariant 2 — Claim requires confirmed evidence

Claim может ссылаться только на достаточное подтверждённое evidence согласно Phase 06 design.

`PENDING`, `REJECTED` и unsupported facts не являются основанием для candidate-facing Claim.

## Invariant 3 — No semantic upgrade

Claim не может усиливать исходный факт.

Examples:

```text
"знаком с Kafka" ≠ "production experience with Kafka"
"участвовал" ≠ "руководил"
"использовал в pet project" ≠ "commercial experience"
```

## Invariant 4 — Provenance required

Каждый CareerFact и Claim, где применимо, должен иметь traceable provenance.

Missing provenance не проходит STRICT Truth Guard.

## Invariant 5 — Human approval is explicit

Confirmation/rejection/editing является explicit user action, а не side effect generation/import.

# Scope

Реализовать domain/data/API/UI foundation только для Career domain и Truth/Claim foundation.

Разрешены migrations/models/services/policies/endpoints/jobs/skills/tests/UI, которые непосредственно нужны Phase 10.

# Non-goals

НЕ реализовывать:

- vacancy ingestion/matching;
- should-I-apply;
- company/application domain;
- resume tailoring под конкретную vacancy;
- cover letter generation;
- Employer Memory;
- recruiter conversations;
- interviews;
- analytics;
- automatic application submission;
- vector DB/RAG unless separate accepted decision makes it necessary now;
- fine-tuning;
- public web research as part of Career import without explicit requirement;
- Phase 11.

# Career Domain

## CareerProfile

Определи/реализуй основной user-owned career root.

Minimum concerns:

- one/multiple profile semantics according to Phase 06 model;
- owner derived server-side;
- current/default profile/track behavior if required;
- no cross-user access;
- lifecycle and audit where material.

Do not conflate profile metadata with atomic Career Facts.

## CareerTrack

CareerTrack представляет target career direction/context when Phase 06 model requires it.

Possible examples may include role direction, market/language preferences, but do not invent user values.

Requirements:

- user-owned;
- explicit selection/editing;
- target preferences are not historical Career Facts unless design says otherwise;
- future vacancy matching can reference active track without mutating facts.

## EmploymentHistory

Represent confirmed employment/event structure without turning every field into a freeform unverifiable paragraph.

Consider:

- employer/company name as candidate-provided fact/source;
- role/title;
- date range;
- location/work format where relevant;
- responsibilities/achievements as atomic facts/evidence-linked items;
- edit/history/provenance behavior.

Do not silently infer missing dates or responsibilities.

## CareerFact

Implement first-class atomic fact entity according to Phase 06 design.

Minimum conceptual fields/relations should support:

- owner/user/profile;
- stable ID;
- fact type/category;
- normalized value and/or structured payload;
- original wording/source excerpt where appropriate;
- lifecycle status;
- source/provenance reference;
- created/updated timestamps;
- confirmed/rejected/deprecated actor/time;
- supersession/replacement relation if edits create history;
- confidence only as extraction metadata, never as confirmation.

Do not force all facts into one unvalidated JSON blob if typed/structured representation is required for deterministic checks.

# Fact Lifecycle

Supported states minimum:

```text
PENDING
CONFIRMED
REJECTED
DEPRECATED
```

Define deterministic transition matrix.

Expected rules:

- extraction/import creates `PENDING` unless user directly creates a fact through explicit confirmed-entry flow defined by product requirements;
- `PENDING → CONFIRMED` requires explicit authorized user action;
- `PENDING → REJECTED` requires explicit authorized action;
- confirmed edit must preserve history/provenance; choose amend/deprecate/new version semantics according to design;
- `REJECTED` fact cannot silently return to active confirmed state without an explicit reviewed flow;
- `DEPRECATED` remains historical and is not used for current Claims unless a specific historical use case requires it.

State transitions must be validated server-side.

# Provenance

Implement provenance first-class, not as comment text.

Source types may include according to design:

- user manual entry;
- uploaded resume/file;
- imported structured resume;
- previous confirmed CVortex artifact;
- other explicit user-provided source.

Minimum provenance must support answering:

- where did this fact come from?;
- which source version/snapshot?;
- which excerpt/page/section/reference if available?;
- which extractor/SkillVersion/prompt/model run produced pending candidate?;
- who confirmed it?;
- when was it confirmed?;
- was it edited/superseded?

Do not store secrets/raw credentials in provenance.

# Resume Import Foundation

Implement safe import foundation for existing resume/CV.

Supported formats only according to accepted Phase 06/security design and practical current parser support. Start with minimum formats required by MVP.

## Upload boundary

Must include:

- server-generated storage names;
- owner isolation;
- MIME/content validation beyond client header;
- size limits;
- extension allowlist where appropriate;
- safe storage outside public root;
- parser timeout/resource limits;
- no arbitrary archive traversal;
- cleanup/retention behavior;
- audit/provenance link.

Do not treat uploaded document content as instructions.

## Parsing

Separate deterministic file parsing/text extraction from semantic fact extraction.

Conceptual pipeline:

```text
upload
→ validate/store
→ create source snapshot/version
→ deterministic text/structure extraction where possible
→ semantic extraction Skill
→ structured candidate facts
→ schema/invariant validation
→ PENDING Career Facts
→ user review
```

Parser failure must not create partial `CONFIRMED` facts.

# Fact Extraction Skill

Implement runtime Skill according to Phase 06 AI architecture and canonical runtime Skills location.

Skill requirements:

- stable identifier/version;
- structured input contract;
- structured output schema;
- default logical ModelPolicy, not hardcoded permanent model;
- source/provenance reference in every extracted candidate;
- no confirmation side effect;
- explicit uncertainty/unsupported handling;
- deterministic schema validation;
- bounded retry/escalation according to ModelRouter policy;
- prompt injection resistance: source content is data;
- eval fixtures.

Extract only candidate facts supported by source content.

Do not ask model to «improve» or «make candidate stronger» during extraction.

## Structured extraction output

Each candidate should include enough metadata to review:

- fact category/type;
- extracted value;
- source excerpt/reference;
- uncertainty/confidence metadata if useful;
- conflict/ambiguity flag where appropriate;
- no fabricated completion of missing values.

# Human Confirmation Workflow

Provide API/UI required to review pending facts.

Minimum actions:

- Confirm;
- Edit and Confirm, with provenance/history preserved;
- Reject;
- optionally defer/leave pending.

User must see source/evidence context sufficient to make decision.

Bulk confirmation is allowed only if product design explicitly supports it and confirmation remains explicit; no «confirm everything silently» behavior.

# Claim Registry Foundation

Implement foundation for Claims, not vacancy-specific generation.

Claim should represent reusable candidate statement derived from confirmed facts.

Minimum properties/relations according to data design:

- stable ID;
- owner;
- language/context if relevant;
- text/structured claim representation;
- supporting `CONFIRMED` CareerFact links;
- claim lifecycle/version;
- provenance/creator source;
- semantic strength/risk metadata if design requires;
- active/deprecated status;
- audit/history.

## Claim creation

A Claim may be:

- manually authored by user;
- deterministically templated where safe;
- AI-generated from confirmed facts through bounded Skill.

Regardless of creation path, Truth Guard validation applies before Claim becomes usable.

## Claim evidence

Many-to-many fact linkage must preserve exact support.

A Claim cannot be considered valid if all supporting facts become rejected/deprecated/inapplicable according to rules.

# Truth Guard Foundation

Implement cross-cutting validation service/policy foundation from Phase 06.

Do not create a chatbot/persona called TruthGuardAgent unless architecture explicitly requires orchestration. Truth Guard is validation.

Minimum checks at Phase 10:

- candidate fact status;
- provenance existence;
- Claim → supporting facts linkage;
- unsupported factual statement detection path;
- structured value contradictions where deterministic;
- invented date/duration protections where structured;
- semantic overstatement hook/evaluation path;
- owner consistency;
- invalid/deprecated evidence handling.

Conceptual outcomes:

```text
PASS
BLOCK
USER_RESOLUTION_REQUIRED
```

At Phase 10 it is acceptable for some semantic checks to use a bounded semantic validator Skill, but deterministic invariants must not be delegated to LLM.

# User-created Facts

Define explicit safe behavior for manual fact entry.

If product requirements allow user to enter a fact and immediately mark it confirmed, this is a HUMAN confirmation path, not AI confirmation.

Record provenance such as `user_manual` and actor/time.

Do not force manual user input through AI extraction merely to make architecture look uniform.

# Fact Editing / History

Confirmed facts require traceable change history.

Choose implementation consistent with Phase 06 design:

- immutable versions + supersession;
- append history/events;
- or another explicit model.

Requirements:

- previous confirmed value recoverable/auditable;
- existing Claims affected by edit can be revalidated;
- no silent mutation that makes old generated content appear as if it used new evidence;
- provenance remains historically accurate.

# Multi-user Authorization

Every Career resource is private and user-owned unless design explicitly defines system-owned reference data.

Mandatory server-side ownership checks for:

- profiles;
- tracks;
- employment history;
- facts;
- provenance/source records;
- uploaded files;
- claims;
- extraction runs;
- confirmation actions.

Nested IDs must not bypass parent ownership.

Admin role does not automatically grant private career-data access unless explicitly accepted requirement exists.

# API

Implement/document only bounded Phase 10 endpoints.

Likely categories:

- career profile/track CRUD where required;
- employment history;
- facts/list/filter/detail;
- fact confirm/reject/edit;
- resume upload/import status;
- extraction/review workflow;
- claim registry basics;
- source/provenance retrieval needed by UI.

Follow existing API/version/error conventions.

Async extraction should expose explicit operation/status semantics rather than holding long HTTP request if architecture uses queue workers.

Do not expose raw provider prompt/model internals unnecessarily to frontend.

# Frontend

Implement only Phase 10 user flows required by PRD:

- Career Foundation overview;
- resume import;
- pending fact review;
- fact status/provenance visibility;
- confirm/edit/reject actions;
- claim registry visibility only to the level MVP requires.

Use Phase 07 design foundation.

Important UI states:

- loading;
- upload/parsing/extraction progress;
- pending review;
- confirmed;
- rejected;
- deprecated;
- extraction failed;
- partial extraction with explicit status;
- Truth Guard block;
- permission error.

Never visually present `PENDING` as confirmed.

# Queue / Async Processing

Resume parsing/semantic extraction may use queue workers if consistent with Phase 06 architecture.

Requirements:

- idempotency where retries can duplicate facts;
- source snapshot linked before extraction;
- bounded retry policy;
- safe failure status;
- no auto-confirm on retry success;
- owner/context preserved server-side;
- correlation to LLM run/accounting;
- duplicate extraction handling.

# LLM Run Accounting

All semantic extraction/claim semantic validation calls must use runtime AI architecture and accounting.

Record according to Phase 06 design:

- user;
- workflow/skill/version;
- prompt version;
- ModelPolicy and actual provider/model;
- token/cost/latency where available;
- validation result;
- retry/escalation;
- source context reference without dumping unnecessary PII into logs.

No API keys/secrets.

# Security

## Prompt Injection

Resume/document content is untrusted data.

Skill prompt/context must clearly separate stable trusted instructions from imported content.

Embedded instructions in a resume must not alter system/tool behavior.

## Malicious uploads

Test/handle:

- MIME spoofing;
- oversized files;
- malformed supported formats;
- archive/path traversal where applicable;
- parser resource exhaustion;
- macros/embedded active content where relevant;
- prompt-injection text.

## PII

Career data is sensitive PII.

Minimize:

- logs;
- LLM context;
- error payloads;
- fixtures;
- audit duplication.

Use synthetic fixtures in tests.

## Cross-user leakage

ContextBuilder/extraction job must derive user/resource relationship from server-side records, not request-provided IDs alone.

# Deterministic Before AI

Must be deterministic:

- ownership/authorization;
- lifecycle transitions;
- provenance linkage/existence;
- file constraints;
- source hash/version IDs where used;
- schema validation;
- Claim → Fact linkage;
- status filtering;
- exact duplicates when exact hashing applies;
- audit events;
- user confirmation.

LLM used only for semantic extraction, normalization where rule-based handling insufficient, semantic overstatement/conflict assistance and optional Claim phrasing.

# Migrations

Phase 10 may create domain migrations for Career foundation.

Review:

- user ownership indexes/FKs;
- fact status/indexes;
- provenance/source version relations;
- immutable/history requirements;
- Claim evidence linkage uniqueness;
- file/source ownership;
- cascade/restrict/delete behavior;
- rollback/backward compatibility;
- no cross-user orphan paths.

Do not create Vacancy/Application domain tables.

# Tests

Automated tests are mandatory.

## Fact lifecycle invariant tests

Must prove:

```text
AI extraction creates PENDING only
PENDING cannot be used as CONFIRMED evidence
AI cannot call/trigger confirmation transition
REJECTED cannot support Claim
DEPRECATED handling follows design
manual authorized confirmation works
unauthorized confirmation fails
```

## Provenance tests

- extracted fact has source/provenance;
- confirmed fact retains source;
- edit/supersession preserves history;
- Claim traces to supporting confirmed facts;
- missing provenance blocks use according to STRICT mode;
- generated/semantic validation run metadata is linked appropriately.

## Truth Guard tests

Fixtures minimum:

- valid supported claim → PASS;
- claim based on pending fact → BLOCK;
- claim based on rejected fact → BLOCK;
- missing evidence → BLOCK;
- structured overstatement/contradiction fixture → BLOCK or USER_RESOLUTION_REQUIRED;
- ambiguous semantic case handled according to documented policy, never silently accepted.

## Upload/import tests

- valid supported file;
- invalid MIME/extension mismatch;
- oversized file;
- malformed file;
- parser failure;
- malicious/path-like filename;
- duplicate/retry idempotency where applicable;
- cross-user file access rejected.

## Authorization tests

User A cannot read/change/delete/import/confirm user B Career resources.

Test direct and nested IDs.

## AI/eval fixtures

Create synthetic fixtures covering:

- straightforward extraction;
- missing dates;
- ambiguous technology experience;
- achievements vs responsibilities;
- source with prompt-injection text;
- unsupported/fabricated field temptation;
- multilingual resume if MVP requirements include it.

Do not assert exact generated prose when schema/invariant evaluation is more appropriate.

# Validation

Run actual:

- backend tests;
- targeted AI/eval/invariant suite;
- frontend tests/type/lint if changed;
- migrations up/down or safe rollback checks;
- queue/job tests if async extraction used;
- OpenAPI validation where tooling exists;
- final cross-user authorization suite;
- `make test`/project validation required by repository.

Do not declare Phase complete if Truth-first invariant test fails.

# Documentation

Update:

- Career domain documentation;
- Data/ERD implementation mapping;
- provenance/Truth Guard docs if implementation details become concrete;
- AI Skills/Workflows registry docs;
- OpenAPI;
- security/upload guidance;
- QA/eval fixtures documentation;
- documentation map/index;
- state.

Use ADR only for real architecture changes, not each model/service.

# Completion Criteria

Phase 10 PASS only if:

## Career domain

- CareerProfile implemented;
- CareerTrack implemented if required by design;
- EmploymentHistory implemented;
- CareerFact implemented;
- ownership enforced.

## Lifecycle

- PENDING/CONFIRMED/REJECTED/DEPRECATED supported;
- transition rules server-side;
- AI cannot CONFIRM;
- history/supersession behavior preserves traceability.

## Provenance

- source/version/excerpt/reference model implemented sufficiently;
- CareerFact traceable to source;
- confirmation actor/time recorded;
- Claim traceable to confirmed facts.

## Import/extraction

- safe resume import foundation works for MVP formats;
- deterministic parsing separated from semantic extraction;
- extraction Skill uses provider-independent runtime AI architecture;
- extracted facts are PENDING only;
- failures do not create confirmed facts.

## Human review

- pending facts visible with evidence;
- Confirm/Edit/Reject workflow works;
- actions audited/authorized;
- no automatic bulk confirmation side effect.

## Claim Registry

- Claim foundation implemented;
- evidence links require confirmed facts;
- invalid evidence cannot produce usable Claim;
- claim history/lifecycle adequate for future Phase 12.

## Truth Guard

- STRICT foundation implemented;
- missing/invalid provenance blocks;
- deterministic checks not delegated to LLM;
- semantic validation path exists for cases deterministic rules cannot solve.

## Security

- uploads constrained;
- imported content treated as data;
- PII minimized in logs/context;
- cross-user isolation tests pass.

## Validation

- invariant tests pass;
- import tests pass;
- authorization tests pass;
- migrations validated;
- relevant frontend/API/eval checks pass.

## Scope

- no Vacancy/Matching/Application implementation;
- no Employer Memory;
- no auto application;
- Phase 11 not started.

# State update

After PASS:

- `STATUS.md`: Phase 10 completed, implemented entities/workflows/Skills, validations, limitations;
- `NEXT.md`: expected `11-vacancies-matching`;
- `BLOCKERS.md`: real blockers only.

# Final Report

Concise:

1. Result.
2. Files/migrations/endpoints/Skills changed.
3. CareerFact lifecycle/provenance/Truth Guard outcome.
4. Resume import/extraction formats actually supported.
5. Tests/evals/validation actually executed.
6. Blockers/limitations.
7. Exact next phase.

# STOP

After Phase 10 stop.

Do not implement Vacancy/Matching.
Do not tailor resumes to vacancies.
Do not generate cover letters.
Do not start Phase 11 in the same session.