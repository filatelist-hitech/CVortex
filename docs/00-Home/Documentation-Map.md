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
  - "[[../01-Product/Phase-06-Product-Design|Phase 06 Product Design]]"
  - "[[../02-Architecture/Architecture-Baseline|Architecture Baseline]]"
  - "[[../02-Architecture/Phase-06-System-Design|Phase 06 System Design]]"
  - "[[../03-ADR/INDEX|Architecture Decision Index]]"
  - "[[../07-Design/Design-Foundation|Design Foundation]]"
  - "[[../07-Design/Figma-Handoff|Figma Handoff]]"
---

# Documentation Map

`docs/` is both the Git-tracked engineering documentation tree and the Obsidian vault for CVortex. Markdown remains the canonical portable format; Obsidian is a navigation and authoring interface, not a separate knowledge database.

## Core documents

- [[CVortex]] — project home and stable product summary.
- [[../01-Product/Vision|Vision]] — product problem, value, and desired outcome.
- [[../01-Product/Principles|Principles]] — non-negotiable product and engineering principles.
- [[../01-Product/Scope|Scope]] — current scope, deferred work, and explicit non-goals.
- [[../01-Product/Glossary|Glossary]] — canonical terminology used across product and engineering work.
- [[../01-Product/Phase-06-Product-Design|Phase 06 Product Design]] — authoritative PRD, requirements, flows, MVP scope and roadmap.
- [[../02-Architecture/Architecture-Baseline|Architecture Baseline]] — frozen Phase 05 system boundaries and invariants.
- [[../02-Architecture/Phase-06-System-Design|Phase 06 System Design]] — C4 views, component boundaries, data flows and deployment design.
- [[../04-Data/Phase-06-Data-Design|Phase 06 Data Design]] — conceptual ERD, ownership, provenance and audit model.
- [[../06-AI/Phase-06-AI-Design|Phase 06 AI Design]] — provider-independent AI contracts and Truth Guard design.
- [[../08-Security/Threat-Model|Phase 06 Threat Model]] — threats, controls and validation strategy.
- [[../03-ADR/INDEX|Architecture Decision Index]] — authoritative inventory and status of architecture decisions.
- [[../07-Design/Design-Foundation|Design Foundation]] — Phase 07 visual, interaction and accessibility baseline; Git DTCG tokens are canonical machine-readable source.
- [[../07-Design/Figma-Handoff|Figma Handoff]] — deterministic Figma bootstrap, review and Git/Figma drift procedure.

## Documentation areas

| Directory | Purpose | Current state |
|---|---|---|
| `00-Home/` | Home pages and maps of content | Active |
| `01-Product/` | Vision, principles, scope, requirements, flows, roadmap | Active |
| `02-Architecture/` | Architecture views and system diagrams | Active; Phase 05 baseline accepted |
| `03-ADR/` | Architecture decision records and canonical index | Active; Phase 05 decisions accepted |
| `04-Data/` | Conceptual data model, entities, provenance, ownership | Active; Phase 06 completed, independent re-review PASS |
| `05-API/` | API conventions and contracts | Reserved |
| `06-AI/` | Provider abstraction, model policy, skills, agents, prompts, evals | Active; Phase 06 completed, independent re-review PASS |
| `07-Design/` | Design system, tokens, Figma integration | Active; Phase 07 completed, independent review PASS |
| `08-Security/` | Threat model, security requirements, controls | Active; Phase 06 completed, independent re-review PASS |
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
