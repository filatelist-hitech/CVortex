---
title: M1.2 Career Core Implementation
status: implemented
owner: project
created: 2026-09-13
updated: 2026-09-13
tags: [career, provenance, truth-guard, ai, m1]
related:
  - "[[Phase-06-Data-Design|Phase 06 Data Design]]"
  - "[[../06-AI/Phase-06-AI-Design|Phase 06 AI Design]]"
  - "[[../03-ADR/ADR-0009-strict-truth-guard|ADR-0009]]"
  - "[[../../runtime-ai/README|Runtime AI assets]]"
---

# M1.2 Career Core Implementation

M1.2 implements an owner-scoped CareerProfile aggregate with pasted source snapshots, Career Facts, Claims, ClaimEvidence and safe LLM-run metadata. The enforced lifecycle is:

```text
pasted source → provider-neutral extraction → PENDING → human review → CONFIRMED → ClaimEvidence → Claim
```

`Edit and Confirm` retains the extracted assertion and source excerpt in `assertion_original` / `source_excerpt`, while storing the accepted wording separately in `assertion_approved`. Manual facts use explicit `user_manual` provenance and are confirmed by the authenticated user without invoking an LLM.

Truth Guard returns `BLOCK` when evidence is missing, cross-owner, non-confirmed, superseded or invalidly provenanced. `ClaimEvidence::link()` and the database owner-composite foreign keys enforce the same chain:

```text
User → CareerProfile → CareerSource → CareerFact → ClaimEvidence → Claim
```

The bounded `CareerFactType` enum and deterministic semantic validator reject upgrades such as familiarity to experience, participation to leadership, missing dates/seniority, unsupported technology depth and unsupported ownership. Assertions and excerpts must also be literal substrings of the supplied source. The runtime fixtures execute through provider stub → extraction response → schema validation → service → semantic validation; they are not string-comparison documentation.

## API boundary

All routes are under authenticated, active-user `/api/v1/career` scope. Owner identity always comes from the server session.

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/career` | Owner-scoped sources, facts and minimal Claim visibility |
| `GET` | `/career/trusted` | Matching-facing boundary: current same-owner `CONFIRMED` facts with valid provenance and live `PASS` Claims only |
| `POST` | `/career/extractions` | Persist pasted text and start bounded extraction |
| `GET` | `/career/sources/{id}` | Owner-scoped source and safe run metadata |
| `POST` | `/career/facts/manual` | Explicit human-confirmed manual fact |
| `PATCH` | `/career/facts/{id}/review` | `confirm`, `edit_confirm` or `reject` a pending fact |
| `PATCH` | `/career/facts/{id}/deprecate` | Deprecate confirmed evidence and block its Claim |
| `POST` | `/career/facts/{id}/supersede` | Preserve a confirmed historical fact, create its human-confirmed replacement and invalidate stale Claims |
| `PATCH` | `/career/claims/{id}/resolve` | Resolve an explicitly recorded valid-evidence ambiguity |

Repeated extraction of the same source text for one owner reuses its content-hash identity. Completed work is returned without creating duplicate facts or runs; an in-progress source cannot be claimed by another retry. Database uniqueness on owner/source hash and source/candidate hash closes the duplicate-submit race.

`USER_RESOLUTION_REQUIRED` has one narrow meaning: two or more owner-valid, provenance-valid facts support the exact Claim wording but assign different typed fact semantics, so the application cannot choose the intended meaning safely. Missing or invalid evidence remains `BLOCK`. Human resolution selects one of the Claim's still-valid evidence facts, records selected fact/actor/time, clears the active ambiguity and re-evaluates the Claim. Evidence history is preserved.

## Runtime AI boundary

The application depends on the CVortex-owned `LlmProvider` contract and logical `ModelPolicy`. The canonical `career.fact-extraction@1.0.0` manifest, prompt, schema and synthetic adversarial fixtures live under `/runtime-ai/skills/career-fact-extraction/v1/`. A configuration resolver maps logical policy → provider → concrete model outside Career domain/application code. No provider SDK, concrete model invariant, BYOK credential or raw source text is stored in domain/run metadata.

The optional OpenAI Responses adapter is selected only through environment configuration; the model name remains a mutable ModelPolicy mapping. It sends trusted instructions separately from the untrusted source, requests strict JSON Schema output, sets `store: false`, and normalizes transport/output into CVortex-owned types. This follows the current official [Responses structured-output contract](https://platform.openai.com/docs/api-reference/responses-streaming/response/refusal) and [OpenAI data-control guidance](https://developers.openai.com/api/docs/guides/your-data). No SDK type enters application/domain code.

When no system provider is configured, extraction returns a safe provider error while manual fact entry remains available. Provider credentials remain environment-only and are never written to `LlmRun`, logs, responses or runtime assets.

`LlmRun` records Skill/SkillVersion/PromptVersion, logical policy, actual provider/model, request ID, status, token usage when supplied, retry count, latency, validation result, safe error category and configured-computable cost. Missing usage/pricing remains `null`; values are never fabricated. Refusal, incomplete output, malformed structured output, transport and provider failures fail safely and create no trusted fact.

## Existing database reconciliation

`2026_09_13_000004_reconcile_and_harden_career_core.php` is a forward, data-preserving reconciliation for databases where the original M1.2 migration was already recorded but legacy columns (`content`, `value`, `state`, `provenance`) remain. It copies legacy values into the current schema, makes obsolete required columns nullable for forward writes, adds run/resolution metadata and installs owner-composite PostgreSQL constraints. `2026_09_13_000005_enforce_career_source_idempotency.php` adds the source idempotency boundary after safely disambiguating legacy duplicate hashes.

Rollback of the remediation migrations removes the owner-composite foreign keys and owner/source idempotency index that their `down()` methods own, while deliberately retaining reconciled columns, Career rows, typed checks, candidate identity and recorded resolution choices. Re-applying restores the removed boundaries. It is a data-preserving schema rollback, not a promise that legacy application code can resume writing legacy values. Destructive reset/fresh is not the upgrade strategy.

The executable harness is:

```bash
bash scripts/test-career-core-postgres-upgrade.sh
```

It creates only a uniquely named temporary database, reconstructs the representative already-applied legacy schema, verifies preserved legacy data plus extraction/manual writes, checks non-destructive rollback/re-up, exercises cross-owner database failures and removes that temporary database.

## Error and privacy boundary

Career exceptions return stable codes/messages. Operational logging records only route operation and exception class; it does not serialize query bindings, pasted text, assertions, excerpts, prompts or provider failure content. The frontend maps known stable codes to local copy and never renders arbitrary backend exception messages. Audit and `LlmRun` metadata contain no raw Career text or credentials.

## Validation entry points

```bash
docker compose run --rm --no-deps backend php artisan test --filter='CareerCore(Test|RemediationTest)'
bash scripts/test-career-core-postgres-upgrade.sh
bash scripts/test-career-first-value-live.sh
```

The live harness uses synthetic source text and a temporary OpenAI-compatible stub, exercises authenticated extraction/review/trusted-query through local Nginx, restores `AI_PROVIDER=none`, proves manual entry without AI, and deletes only the uniquely identified records/artifacts it created.
