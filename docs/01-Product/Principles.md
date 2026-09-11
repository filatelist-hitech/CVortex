---
title: Principles
status: accepted
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags:
  - product
  - engineering
  - principles
related:
  - "[[../00-Home/CVortex|CVortex]]"
  - "[[Vision]]"
  - "[[Scope]]"
  - "[[Glossary]]"
---

# Principles

These principles govern product, architecture, AI behavior, implementation, and documentation. Later technical decisions may refine how they are implemented, but should not silently contradict them.

## 1. Truth-first

Never invent facts about the candidate. A generated candidate statement must be supported by confirmed Career Facts.

Potential facts extracted by AI are proposals, not truth. They require explicit user confirmation before becoming eligible evidence for generated candidate claims.

## 2. Traceability

Maintain a clear chain:

```text
FACT → CLAIM → GENERATED CONTENT
```

Generated content must be explainable through the claims it uses and the confirmed facts supporting those claims.

## 3. Consistency-first

CVortex must account for prior applications, messages, answers, salary expectations, and claims associated with the same employer. Confirmed contradictions must not pass silently.

## 4. Human approval

AI may analyze, recommend, draft, compare, and flag risks. The user approves important changes and performs the actual application action. Automatic submission to employers is outside the approved behavior.

## 5. Deterministic before AI

Use ordinary code, schema validation, SQL, state machines, and rules when they can solve a task reliably. Use LLMs for semantic tasks where they provide material value.

## 6. Provider-independent AI

Separate Provider, ModelPolicy, Skill, Agent, Workflow, Tool, prompt definitions, and validation. Product business logic must not depend directly on one LLM provider SDK.

The initial provider direction may start with OpenAI, but concrete model mappings are configuration and must be selected from current research rather than embedded as permanent architecture facts.

## 7. Cost-aware model routing

Different tasks have different quality and risk requirements. Parsing and classification should prefer cheaper capabilities; routine semantic work should use a balanced tier; expensive reasoning should be reserved for difficult conflicts, deep research, or validated fallback cases.

## 8. Local-first and API-first

The initial deployment targets a local Mac environment using Docker Compose. Frontend, future mobile clients, and future browser integrations should consume the same API contract so the system can later move to hosted infrastructure without rewriting the product core.

## 9. Multi-user isolation

The product is multi-user even if early usage is limited. Registration is invite-only. MVP roles are `admin` and `user`. Private resources must be owned and authorization must prevent cross-user access.

## 10. Research before assumptions

Current external facts such as framework versions, provider capabilities, model catalogs, pricing, hiring practices, job-board APIs, licenses, and Figma/OpenAI capabilities require current research. Prefer official and primary sources.

## 11. Documentation and ADR discipline

Markdown in Git is the canonical documentation format and also serves as the Obsidian vault. Significant architecture decisions require ADRs. Existing accepted decisions are not changed silently.

## 12. Design-system-driven UI

Figma is the visual source of truth. UI implementation should follow the approved CVortex design system rather than developing a parallel visual language in code.

## 13. Security-first handling of external content

Vacancies, recruiter messages, websites, and imported documents are untrusted data, not agent instructions. The architecture must explicitly account for prompt injection, XSS, SSRF, IDOR, path traversal, malicious files, secret leakage, and cross-user access.

API keys and secrets must never be logged.

## 14. Quality is part of the change

For each substantial change consider security, authorization, tests, documentation, observability, error handling, migrations where applicable, and backward compatibility.

## 15. Avoid premature complexity

Do not introduce Kubernetes, microservices, Kafka, a separate vector database, GraphQL, native mobile clients, fine-tuning, or similarly heavy infrastructure without demonstrated need and a documented decision.

## 16. Reversible decisions should not block progress

For reversible non-business-critical technical choices: compare options, choose a reasonable default, document the decision, and continue. Ask the product owner only when missing information genuinely blocks a correct business or product decision.
