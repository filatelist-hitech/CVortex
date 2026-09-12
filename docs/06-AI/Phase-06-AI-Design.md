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

`LlmProvider` translates provider transport, structured-output/tools capability, usage, rate limits and safe errors. `ModelRouter` selects a logical `ModelPolicy` using complexity, semantic risk, context size, latency/cost budget, required structure and validation failures. Policies are `low-cost-extraction`, `standard-semantic`, `high-confidence-reasoning`, `research` and `fallback`; mutable configuration maps policy to provider/model/parameters. Domain code never receives provider SDK types.

`Skill` is a versioned runtime capability with stable ID, input/output schema, default policy, prompt version, deterministic pre/post-processing, retry/escalation, security constraints, eval fixtures and deprecation path. `Agent` exists only where it orchestrates multiple Skills/tools: Vacancy, Career Profile, Resume/Application, Research, Conversation and Interview candidates are workflows first, not mandatory permanent agents. `Workflow` owns sequence, branch, validation and approval for Career Fact extraction, vacancy analysis, application/resume/cover preparation, employer consistency, interview preparation and research.

Tools are bounded deterministic services: owner-scoped retrieval, parser, renderer, source adapter, schema validator and hashing. They never provide arbitrary filesystem, database or network access to an LLM.

## Context, prompts, evals and accounting

ContextBuilder selects minimum owner-scoped context by Workflow/Skill, labels provenance, includes relevant Employer Memory and separates trusted instructions from untrusted data. It excludes unrelated user records and credentials. Prompt Registry is canonical, versioned and deployable from [`/runtime-ai/`](../../runtime-ai/README.md); immutable historical versions remain resolvable, active versions roll forward only with eval evidence, and rollback selects a previous version. Stable instruction prefixes remain separate from dynamic context for provider caching.

Evals use fixtures, output schemas, invariant/security/adversarial tests, regression datasets and policy/prompt/cost/latency comparison—not brittle prose equality. They verify no unsupported fact, automatic confirmation, missing provenance, employer contradiction, changed critical value or cross-user context.

Each LLM run records safe run/user/workflow/agent/skill/skill-version/prompt-version/policy/provider/actual-model identifiers, token categories, cost, latency, retries, validation status/error category and timestamps. Aggregate by user, vacancy/application, workflow, Skill, provider/model and failure/escalation rate. Never record keys or raw secrets.

## Strict Truth Guard

Truth Guard is a deterministic cross-cutting policy, not an AI persona. Candidate content follows `CONFIRMED Fact → Claim → Content`; extraction creates only `PENDING`. In STRICT mode missing provenance, unsupported/overstated fact, invented date/duration/responsibility, or confirmed employer contradiction yields `BLOCK` or `USER_RESOLUTION_REQUIRED`. Semantic detection may use a model, but fact status, linkage, schema, ownership, required fields and contradiction identifiers are ordinary code checks.
