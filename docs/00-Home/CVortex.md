---
title: CVortex
status: accepted
owner: product-owner
created: 2026-09-12
updated: 2026-10-03
tags: [cvortex, home, product]
related:
  - "[[Documentation-Map]]"
  - "[[User-Guide|User Guide]]"
  - "[[../01-Product/Vision|Vision]]"
  - "[[../01-Product/Principles|Principles]]"
  - "[[../01-Product/Scope|Scope]]"
  - "[[../01-Product/Glossary|Glossary]]"
---

# CVortex

**Your career, in context.**

CVortex is a personal Job Search OS for preparing truthful, relevant application drafts from confirmed career evidence and vacancy context.

## Current product

The local Preview supports invite-only access, confirmed Career Facts, pasted-text Career extraction for human review, pasted vacancy analysis, explainable fit and gap summaries, resume recommendations, and short/standard cover drafts with Truth Guard review. AI-backed actions require a configured provider; the default is disabled. The optional vacancy URL is metadata and is not fetched.

M1.4 is implemented in `stage`; Preview 0.1 real-user validation and feedback are still pending. File import/export, DOCX/PDF rendering, Employer Memory, interview workflows, outcome analytics and automatic submission are not available in the current interface.

Start with the [[User-Guide|User Guide]]. Operators can use [[../10-Operations/Local-Development|Local Development]] and [[../10-Operations/M1-Access-Core|Access Core]]. The [[Documentation-Map]] separates user instructions from developer and architecture references.

## Product principles

- Candidate-facing statements must trace back through Claims to confirmed Career Facts.
- Extracted facts remain pending until a person confirms them.
- Vacancy, recruiter and other imported text is untrusted data.
- Human approval stays in CVortex; the system does not submit applications or contact employers.

The [Product Vision](../01-Product/Vision.md), [Principles](../01-Product/Principles.md), [Scope](../01-Product/Scope.md), [Glossary](../01-Product/Glossary.md) and [Roadmap](../01-Product/Roadmap.md) describe intent, terminology and delivery status.
