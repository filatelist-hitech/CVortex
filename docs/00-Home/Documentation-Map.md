---
title: Documentation Map
status: accepted
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags: [documentation, moc, obsidian]
related:
  - "[[CVortex]]"
  - "[[../01-Product/Vision|Vision]]"
  - "[[../01-Product/Principles|Principles]]"
  - "[[../01-Product/Scope|Scope]]"
  - "[[../01-Product/Roadmap|Roadmap]]"
  - "[[../01-Product/Glossary|Glossary]]"
  - "[[../03-ADR/INDEX|Architecture Decision Index]]"
  - "[[../02-Architecture/M0-Runtime|M0 Runtime]]"
  - "[[../10-Operations/Local-Development|Local Development]]"
---

# Documentation Map

`docs/` is both the Git-tracked engineering documentation tree and the Obsidian vault for CVortex. Markdown remains canonical; Obsidian is an interface, not a separate knowledge database.

## Core documents

- [[CVortex]] — project home and stable summary.
- [[../01-Product/Vision|Vision]] — problem, value and desired outcome.
- [[../01-Product/Principles|Principles]] — non-negotiable product/engineering principles.
- [[../01-Product/Scope|Scope]] — current product scope, deferred work and non-goals.
- [[../01-Product/Roadmap|Roadmap]] — **canonical implementation sequence and milestone/value checkpoints**.
- [[../01-Product/Glossary|Glossary]] — canonical terminology.
- [[../01-Product/Phase-06-Product-Design|Phase 06 Product Design]] — accepted PRD, requirements and user-flow baseline; its former embedded roadmap is superseded by [[../01-Product/Roadmap|Roadmap]].
- [[../02-Architecture/Architecture-Baseline|Architecture Baseline]] — accepted system boundaries/invariants.
- [[../02-Architecture/Phase-06-System-Design|Phase 06 System Design]] — accepted C4/component/deployment design.
- [[../02-Architecture/M0-Runtime|M0 Runtime]] — implemented local runtime topology, version baseline, health, persistence and exposure boundaries.
- [[../04-Data/Phase-06-Data-Design|Phase 06 Data Design]] — accepted conceptual data, ownership, provenance and audit model.
- [[../04-Data/M1-2-Career-Core|M1.2 Career Core Implementation]] — implemented Career lifecycle, API, provenance, Truth Guard and runtime-AI boundary.
- [[../06-AI/Phase-06-AI-Design|Phase 06 AI Design]] — accepted provider-independent AI/Truth Guard contracts.
- [[../08-Security/Threat-Model|Phase 06 Threat Model]] — threats, controls and validation strategy.
- [[../03-ADR/INDEX|Architecture Decision Index]] — authoritative architecture-decision inventory.
- [[../07-Design/Design-Foundation|Design Foundation]] — visual/interaction/accessibility baseline and Git token authority.
- [[../07-Design/Figma-Handoff|Figma Handoff]] — Figma bootstrap/review/drift procedure and current capability limitation.
- [[../10-Operations/Local-Development|Local Development]] — clean bootstrap and stable Make interface.
- [[../10-Operations/M0-Runbook|M0 Runbook]] — health diagnosis, recovery and validation commands.

## Documentation areas

| Directory | Purpose | Current state |
|---|---|---|
| `00-Home/` | Home pages and maps | Active |
| `01-Product/` | Vision, principles, scope, PRD, roadmap | Active |
| `02-Architecture/` | Architecture views and diagrams | Active; baseline accepted |
| `03-ADR/` | Architecture decisions | Active; ADR-0001..0018 accepted |
| `04-Data/` | Conceptual data/provenance/ownership | Active; Phase 06 accepted |
| `05-API/` | API conventions/contracts | Reserved; M0 `/api/v1` boundary is documented in M0 Runtime |
| `06-AI/` | Provider/model-policy/skills/workflows/evals | Active; Phase 06 accepted |
| `07-Design/` | Design system/tokens/Figma | Active; Phase 07 completed |
| `08-Security/` | Threat model/security controls | Active; Phase 06 accepted |
| `09-QA/` | Testing/evaluation strategy | Reserved; M0 checks live with applications/CI |
| `10-Operations/` | Local operations/deployment/observability | Active; M0 local runtime documented |
| `11-Research/` | Promoted research summaries | Reserved |
| `99-Archive/` | Deprecated/superseded docs | Reserved |

## Rules

- Significant architecture changes require ADR review; roadmap sequencing changes do not silently rewrite accepted architecture.
- Research-dependent facts are verified before they become architecture/implementation facts.
- Concrete versions, external API capabilities, prices and model mappings are freshness-sensitive.
- Mermaid is preferred for engineering diagrams where practical.
- Documents remain readable in Git without Obsidian.
- Current execution authority comes from `.agents/state/NEXT.md` + blockers + the active task spec, not from legacy phase files.
