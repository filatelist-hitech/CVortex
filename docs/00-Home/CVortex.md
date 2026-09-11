---
title: CVortex
status: accepted
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags:
  - cvortex
  - home
  - product
related:
  - "[[Documentation-Map]]"
  - "[[../01-Product/Vision|Vision]]"
  - "[[../01-Product/Principles|Principles]]"
  - "[[../01-Product/Scope|Scope]]"
  - "[[../01-Product/Glossary|Glossary]]"
---

# CVortex

**CVortex** is a personal Job Search OS for preparing truthful, relevant, and internally consistent job applications while preserving the context of a candidate's career history and prior employer interactions.

**Tagline:** *Your career, in context.*

## Core outcome

CVortex turns a vacancy plus confirmed career facts and employer context into an application strategy and application package that the user can review and approve.

The system is designed to help with:

- maintaining a canonical Career Fact Base;
- importing and analyzing vacancies;
- matching vacancy requirements against confirmed facts;
- deciding whether an application is worth prioritizing;
- recommending resume changes without inventing experience;
- producing tailored resume versions and cover letters;
- preserving application history and Employer Memory;
- preparing for interviews using the exact history of what was already communicated;
- researching employers and evidence-based hiring practices;
- measuring job-search outcomes over time.

## Source-of-truth chain

```text
Career Facts
    ↓
Claims
    ↓
Generated Content
```

Any statement about the candidate must remain traceable back to confirmed Career Facts. AI-extracted potential facts are not automatically treated as truth.

## Product boundaries

- Human approval is mandatory for important generated content and application actions.
- CVortex does not automatically submit job applications.
- Deterministic code, schemas, SQL, validation, and rules are preferred where they solve the problem reliably.
- Runtime AI is provider-independent at the business-logic level.
- External vacancy text, recruiter messages, web content, and imported documents are untrusted input.
- The product begins local-first and API-first, while remaining portable to hosted infrastructure later.

## Approved technology direction

The current project direction is:

- Laravel backend;
- Next.js / React / TypeScript frontend;
- PostgreSQL as the primary relational database;
- Redis and Laravel Horizon for queue/cache operations;
- Nginx as the web entry point;
- Docker Compose for the initial local deployment;
- structured document generation to DOCX with LibreOffice-based PDF conversion;
- responsive PWA for the first mobile experience;
- Figma as the visual design source of truth;
- Markdown in Git as documentation, usable as an Obsidian vault.

Specific dependency versions and concrete LLM model mappings are intentionally not fixed in Phase 01. They require the research and decision phases.

## Current documentation

Start at [[Documentation-Map]]. Product intent is captured in [[../01-Product/Vision|Vision]], governing rules in [[../01-Product/Principles|Principles]], boundaries in [[../01-Product/Scope|Scope]], and shared terminology in [[../01-Product/Glossary|Glossary]].
