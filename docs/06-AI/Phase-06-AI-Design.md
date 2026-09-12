---
title: Phase 06 AI Architecture
status: accepted
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [ai, truth-guard, skills, model-policy, phase-06]
related: ["[[../03-ADR/ADR-0010-provider-independent-llm|ADR-0010]]", "[[../03-ADR/ADR-0011-logical-model-policy|ADR-0011]]", "[[../03-ADR/ADR-0018-runtime-ai-skills-location|ADR-0018]]"]
---

# Phase 06 AI Architecture

## Contracts and routing

`LlmProvider` translates provider transport, structured-output/tools capability, usage, rate limits and safe errors. `ModelRouter` selects a logical `ModelPolicy` using complexity, semantic risk, context size, latency/cost budget, required structure and validation failures. Policies are logical capabilities; mutable configuration maps them to provider/model/parameters. Domain code never receives provider SDK types.

`Skill` is a versioned runtime capability with stable ID, input/output schema, default policy, prompt version, deterministic pre/post-processing, retry/escalation, security constraints, eval fixtures and deprecation path. `Agent` exists only where orchestration of multiple Skills/tools is truly required. `Workflow` owns sequence, branching, validation and approval.

Tools are bounded deterministic services: owner-scoped retrieval, parser, renderer, source adapter, schema validator and hashing. They never provide arbitrary filesystem, database or network access to an LLM.

## Context, prompts, evals and accounting

ContextBuilder selects minimum owner-scoped context by Workflow/Skill, labels provenance, includes only relevant Employer Memory and separates trusted instructions from untrusted data. It excludes unrelated user records and credentials.

Prompt Registry and runtime Skills are canonical/versioned under `/runtime-ai/`. Historical versions remain resolvable; active versions roll forward with eval evidence and can roll back.

Evals use fixtures, schemas, invariant/security/adversarial tests and regression datasets rather than exact prose equality. They verify no unsupported fact, automatic confirmation, missing provenance, employer contradiction, changed critical value or cross-user context.

Each LLM run records safe user/workflow/skill/prompt/policy/provider/model/usage/cost/latency/retry/validation metadata. Never record keys or raw secrets.

## Strict Truth Guard

Truth Guard is deterministic cross-cutting policy, not an AI persona. Candidate content follows:

```text
CONFIRMED Career Fact → Claim → Generated Content
```

Extraction creates only `PENDING`; an LLM can never confirm a Career Fact.

Before candidate-facing content is eligible for approval/employer use, the guard verifies every statement's Claim, ClaimEvidence, confirmed-fact state, ownership, provenance and consistency identifiers.

Fail closed:

```text
missing provenance
OR invalid provenance
OR Claim without valid CONFIRMED Career Fact evidence
= BLOCK
```

`USER_RESOLUTION_REQUIRED` is reserved for valid-evidence ambiguity/conflict, not missing evidence. After explicit user resolution the content re-enters Truth Guard.

| Condition | Outcome | May LLM decide? |
|---|---|---|
| Missing/invalid provenance; unsupported/overstated fact; invented date/duration/responsibility | `BLOCK` | No |
| Valid confirmed evidence with semantic ambiguity/conflicting confirmed value | `USER_RESOLUTION_REQUIRED`, then revalidate | Assist detection/explanation only |
| Owner-scoped valid confirmed evidence and no unresolved contradiction | `PASS` | No |

Semantic detection may use a model; status, linkage, provenance validity, ownership, required fields and resolution state remain ordinary code checks.
