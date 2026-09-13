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

Truth Guard returns `BLOCK` when evidence is missing, cross-owner, non-confirmed or invalidly provenanced. ClaimEvidence creation uses the same deterministic checks. Extraction output is additionally constrained to assertions copied from a verbatim source excerpt, preventing unsupported semantic upgrades in this bounded schema.

## API boundary

All routes are under authenticated, active-user `/api/v1/career` scope. Owner identity always comes from the server session.

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/career` | Owner-scoped sources, facts and minimal Claim visibility |
| `POST` | `/career/extractions` | Persist pasted text and start bounded extraction |
| `GET` | `/career/sources/{id}` | Owner-scoped source and safe run metadata |
| `POST` | `/career/facts/manual` | Explicit human-confirmed manual fact |
| `PATCH` | `/career/facts/{id}/review` | `confirm`, `edit_confirm` or `reject` a pending fact |
| `PATCH` | `/career/facts/{id}/deprecate` | Deprecate confirmed evidence and block its Claim |

Repeated extraction of the same normalized source text for one owner reuses its content-hash identity. Completed work is returned without creating duplicate facts or runs; an in-progress source cannot be claimed by another retry.

## Runtime AI boundary

The application depends on the CVortex-owned `LlmProvider` contract and logical `ModelPolicy`. The canonical `career.fact-extraction@1.0.0` manifest, prompt, schema and synthetic adversarial fixtures live under `/runtime-ai/skills/career-fact-extraction/v1/`. No provider SDK, concrete model invariant, BYOK credential or raw source text is stored in domain/run metadata.

The optional OpenAI Responses adapter is selected only through environment configuration; the model name remains a mutable ModelPolicy mapping. It sends trusted instructions separately from the untrusted source, requests strict JSON Schema output, sets `store: false`, and normalizes transport/output into CVortex-owned types. This follows the current official [Responses structured-output contract](https://platform.openai.com/docs/api-reference/responses-streaming/response/refusal) and [OpenAI data-control guidance](https://developers.openai.com/api/docs/guides/your-data). No SDK type enters application/domain code.

When no system provider is configured, extraction returns a safe provider error while manual fact entry remains available. Provider credentials remain environment-only and are never written to `LlmRun`, logs, responses or runtime assets.
