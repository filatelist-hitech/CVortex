---
title: Documentation Map
status: accepted
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags:
  - documentation
  - moc
  - obsidian
related:
  - "[[CVortex]]"
  - "[[../01-Product/Vision|Vision]]"
  - "[[../01-Product/Principles|Principles]]"
  - "[[../01-Product/Scope|Scope]]"
  - "[[../01-Product/Glossary|Glossary]]"
---

# Documentation Map

`docs/` is both the Git-tracked engineering documentation tree and the Obsidian vault for CVortex. Markdown remains the canonical portable format; Obsidian is a navigation and authoring interface, not a separate knowledge database.

## Current Phase 01 documents

- [[CVortex]] — project home and stable product summary.
- [[../01-Product/Vision|Vision]] — product problem, value, and desired outcome.
- [[../01-Product/Principles|Principles]] — non-negotiable product and engineering principles.
- [[../01-Product/Scope|Scope]] — current scope, deferred work, and explicit non-goals.
- [[../01-Product/Glossary|Glossary]] — canonical terminology used across product and engineering work.

## Documentation areas

| Directory | Purpose | Phase 01 state |
|---|---|---|
| `00-Home/` | Home pages and maps of content | Active |
| `01-Product/` | Vision, principles, scope, requirements, flows, roadmap | Active |
| `02-Architecture/` | Architecture views and system diagrams | Reserved for later design phases |
| `03-ADR/` | Accepted architecture decision records | Reserved; no new ADR created in Phase 01 |
| `04-Data/` | Conceptual data model, entities, provenance, ownership | Reserved |
| `05-API/` | API conventions and contracts | Reserved |
| `06-AI/` | Provider abstraction, model policy, skills, agents, prompts, evals | Reserved |
| `07-Design/` | Design system, tokens, Figma integration | Reserved |
| `08-Security/` | Threat model, security requirements, controls | Reserved |
| `09-QA/` | Testing strategy, quality gates, evaluation strategy | Reserved |
| `10-Operations/` | Local operations, deployment, observability | Reserved |
| `11-Research/` | Research summaries promoted from raw research | Reserved |
| `99-Archive/` | Deprecated or superseded documentation | Reserved |

## Documentation rules

- Significant architecture decisions belong in ADRs rather than being silently embedded in prose.
- Research-dependent facts must be verified in the research phases before becoming architecture facts.
- Accepted project decisions are not changed silently. Conflicts must be called out explicitly.
- Concrete library versions, external API capabilities, current prices, and LLM model mappings must not be assumed.
- Mermaid is preferred for engineering diagrams where practical.
- Documents should remain readable in Git viewers without requiring Obsidian.
