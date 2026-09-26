---
title: M1.4 Application Draft
status: implemented
owner: project
created: 2026-09-24
updated: 2026-09-24
tags: [application, draft, provenance, truth-guard, m1]
related:
  - "[[M1-2-Career-Core|M1.2 Career Core]]"
  - "[[M1-3-Vacancy-Core|M1.3 Vacancy Core]]"
  - "[[../06-AI/Phase-06-AI-Design|Phase 06 AI Design]]"
  - "[[../08-Security/Threat-Model|Threat Model]]"
  - "[[../../runtime-ai/README|Runtime AI assets]]"
---

# M1.4 Application Draft

M1.4 connects the current analyzed Vacancy snapshot to confirmed Career evidence through a saved preparation flow:

```text
current Vacancy analysis + relevant confirmed Claims
→ resume recommendations and short/standard cover drafts
→ deterministic Claim validation + semantic Truth Guard
→ user review, edit/revalidation, rejection or explicit approval
```

This is Preview 0.1. It does not create a final document package, claim `READY_TO_APPLY`, submit an application or contact an employer. Candidate-facing factual wording is conservative: generated and edited factual spans must copy an existing confirmed Claim exactly; unsupported paraphrases remain blocked until the user confirms new Claim wording in Career.

## Persistence and state

`ApplicationPreparation` is owner-scoped to one Vacancy snapshot and Career signature. Opening the same current context resumes its saved preparation. A changed snapshot or confirmed Career signature makes the preparation stale and blocks generation, edits and approval until it is reopened against current analyzed context.

`ApplicationDraftItem` stores resume recommendations or one of exactly two cover variants (`SHORT`, `STANDARD`), its current revision number, content, Before/After, reason/risk, review status and Truth Guard result. `ApplicationDraftRevision` appends each generated or edited content version with its hash, validation result, Claim usage snapshot, actor and timestamp; action events reference the exact revision number. `ApplicationClaimUsage` maps factual text segments to same-owner Claims; response provenance includes the supporting confirmed Career Fact and source excerpt. Truth review v3 batches independent draft-item reviews and must return ordered text segments whose exact concatenation matches each complete candidate item before `PASS` is accepted, preventing omitted text from inheriting a partial review. Code additionally requires every factual span to equal a current Claim statement byte-for-byte and permits only whitespace or punctuation outside those spans; model `PASS` alone cannot extend a Claim. `ApplicationApprovalEvent` records accept/edit/reject/approve actions, the content hash and validation result. `ApplicationLlmRun` stores skill/model-policy identity, provider metadata, validation status and safe error category; it does not store raw prompt, candidate text or credentials.

All six tables have owner-composite relationships and forced PostgreSQL RLS. API ownership comes from the authenticated active user and `db-owner-context`; client-supplied owner or provenance is ignored. PostgreSQL composite foreign keys reject cross-owner Vacancy, requirement, Claim and preparation links.

Review state is `DRAFT`, `ACCEPTED`, `REJECTED`, `APPROVED` or `BLOCKED`. A factual edit invalidates the previous content hash and is revalidated. Accept and edit do not approve. Approval locks the item and current Claim/Career evidence, checks the current context and validated hash, then stores an approval event. There is no `APPLIED` state or employer-facing action.

## API

The optional [MCP Gateway](../02-Architecture/MCP-Gateway.md) can create a validated cover draft through the same preparation and Truth Guard service. Its `PENDING_REVIEW` response maps to the existing persisted `DRAFT` status; review and approval remain exclusive to the CVortex user workflow. This foundation does not change M1.4 Preview acceptance or introduce an `APPLIED` state.

All endpoints are under authenticated, active-user `/api/v1` routes. The JSON resource returns preparation status/staleness and items with current content, complete revision history, recommendation details, Claim/Career provenance and revision-linked approval history.

| Method | Route | Purpose |
|---|---|---|
| `POST` | `/vacancies/{vacancyId}/preparation` | Open or resume a preparation for the current analyzed context |
| `GET` | `/applications/preparations/{id}` | Read the owner-scoped saved preparation |
| `POST` | `/applications/preparations/{id}/generate` | Generate recommendations plus short and standard cover drafts |
| `PATCH` | `/applications/draft-items/{id}` | Accept, edit/revalidate or reject an item |
| `POST` | `/applications/draft-items/{id}/approve` | Explicitly approve only current content with Truth Guard `PASS` |

Generation and semantic review use versioned Skills through the existing provider-independent `LlmProvider`, `ModelPolicy` and `RuntimeSkillRegistry` contracts. Deterministic code owns authorization, context minimization, schema/provenance checks, state transitions, staleness and approval. Vacancy and Career material is sent as labelled untrusted data; only current requirements and relevant valid Claims with confirmed evidence are included.

## Validation

The feature suite covers saved resume, explicit approval, cross-user ID access, foreign Claim rejection, unsupported and supported edits, pending-fact exclusion, vacancy prompt injection as data, staleness and provider failure/retry. PostgreSQL validation uses the repository runtime role to verify forced RLS, owner-scoped reads/writes, cross-owner composite links, revision ownership and migration rollback/re-up while preserving existing user, Career Fact and Vacancy rows.

```bash
make test
make lint
docker compose run --rm --no-deps frontend npm run build
bash scripts/test-application-postgres-boundary.sh
```
