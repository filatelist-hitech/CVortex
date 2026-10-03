---
title: Documentation Map
status: accepted
owner: product-owner
created: 2026-09-12
updated: 2026-10-03
tags: [documentation, moc, obsidian]
related:
  - "[[CVortex]]"
  - "[[User-Guide|User Guide]]"
  - "[[Process-Map|Process Map]]"
  - "[[../01-Product/Vision|Vision]]"
  - "[[../01-Product/Scope|Scope]]"
  - "[[../01-Product/Roadmap|Roadmap]]"
  - "[[../03-ADR/INDEX|Architecture Decision Index]]"
---

# Documentation Map

`docs/` is the Git-tracked project documentation and an Obsidian-compatible vault. Markdown is canonical; every document should remain readable in GitHub and other Markdown viewers.

## User and operator documentation

- [[CVortex|CVortex home]] — concise description of the product as it exists now.
- [[User-Guide|User Guide]] — sign-in, Career Facts, vacancy analysis, drafts, privacy and feature limits.
- [[Process-Map|Process Map]] — the implemented Preview workflow, provenance, local runtime and diagnostics; planned M2 scope is marked separately.
- [[../10-Operations/Local-Development|Local Development]] — requirements, setup, environment, start/stop/update and troubleshooting for a local installation.
- [[../10-Operations/M0-Runbook|M0 Runbook]] — service health and operator diagnosis.
- [[Error-Center-User-Guide|Error Center user guide]] — actions for users and administrators when an operation fails.
- [[../10-Operations/M1-Access-Core|Access Core]] — first administrator, invitations, registration and access operations.
- [[../10-Operations/Logging-and-Diagnostics|Logging and Diagnostics]] — operator diagnostics and log fallback.
- [[../10-Operations/MCP-Gateway-Validation|MCP Gateway validation]] — optional advanced integration validation; MCP is disabled by default.

## Product and implementation references

- [[../01-Product/Vision|Vision]], [[../01-Product/Principles|Principles]], [[../01-Product/Scope|Scope]] and [[../01-Product/Glossary|Glossary]] — product intent, constraints and terminology. Scope and Roadmap describe direction, not proof that a feature exists.
- [[../01-Product/Roadmap|Roadmap]] — canonical implementation sequence and status.
- [[../02-Architecture/Architecture-Baseline|Architecture Baseline]], [[../02-Architecture/Phase-06-System-Design|Phase 06 System Design]] and [[../02-Architecture/M0-Runtime|M0 Runtime]] — boundaries, accepted system design and local topology.
- [[../04-Data/M1-2-Career-Core|M1.2 Career Core]], [[../04-Data/M1-3-Vacancy-Core|M1.3 Vacancy Core]] and [[../04-Data/M1-4-Application-Draft|M1.4 Application Draft]] — implemented domain/API contracts and validation boundaries.
- [[../04-Data/Diagnostics|Diagnostics data model]] and [[../05-API/diagnostics.openapi.yaml|Diagnostics API contract]].
- [[../06-AI/Phase-06-AI-Design|AI design]], [[../08-Security/Threat-Model|Threat Model]] and [[../07-Design/Design-Foundation|Design Foundation]].
- [[../07-Design/Figma-Handoff|Figma Handoff]] — design source, handoff process and known review boundary.
- [[../03-ADR/INDEX|Architecture Decision Index]] — authoritative accepted architecture decisions.

## Documentation areas

| Directory | Purpose | State |
|---|---|---|
| `00-Home/` | Product home, user guide and documentation map | Active |
| `01-Product/` | Vision, principles, scope, product design and roadmap | Active; roadmap is execution authority for product sequencing |
| `02-Architecture/` | Architecture views and runtime topology | Active |
| `03-ADR/` | Accepted, proposed and superseded architecture decisions | Active; see its index |
| `04-Data/` | Data ownership, provenance and implemented domain contracts | Active |
| `05-API/` | API contracts | Active |
| `06-AI/` | Provider/model, skills, workflow and evaluation design | Active |
| `07-Design/` | Design system, tokens and Figma handoff | Active |
| `08-Security/` | Threat model and security controls | Active |
| `09-QA/` | Dedicated QA documentation | Reserved; executable checks live with apps/scripts/CI |
| `10-Operations/` | Local operation, deployment and observability | Active |
| `11-Research/` | Promoted research summaries | Reserved |
| `99-Archive/` | Deprecated or superseded documents | Reserved |

## Navigation rules

- User steps belong in the User Guide or operator guides; product intent and future plans stay in Product and Architecture references.
- A planned feature is not available because it appears in Scope, a design, an ADR or a roadmap target. Check the User Guide and current roadmap status.
- Material architecture decisions require ADR coverage. Do not silently contradict an accepted decision.
- Obsidian wiki links may aid navigation, but GitHub-relative Markdown links remain available where they help readers outside Obsidian.
- Current task authority comes from `.agents/state/NEXT.md`, blockers and an authorized task—not from this map or a roadmap milestone.
