---
title: Scope
status: accepted
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags:
  - product
  - scope
  - mvp
related:
  - "[[../00-Home/CVortex|CVortex]]"
  - "[[Vision]]"
  - "[[Principles]]"
  - "[[Glossary]]"
---

# Scope

This document records the approved product direction at the beginning of the project. It is intentionally conceptual. Detailed PRD, architecture, data model, API, AI contracts, and implementation sequencing belong to later phases.

## In scope for the product direction

### Career context

- maintain a master career profile and Career Fact Base;
- support multiple Career Tracks without creating contradictory biographies;
- import existing resume material;
- extract potential facts for manual confirmation;
- retain provenance for important facts and generated claims.

### Vacancy workflow

- accept vacancy input from pasted text and files, with URL-based ingestion where later research permits it;
- preserve raw vacancy content alongside normalized data;
- extract and classify vacancy requirements;
- compare requirements against confirmed candidate facts;
- identify fit, gaps, and risks by meaningful dimensions rather than a fake universal ATS percentage;
- produce a reasoned apply / maybe / skip style recommendation.

### Application package

- recommend resume changes with rationale, evidence, and risk;
- support user accept / edit / reject actions;
- create tailored resume versions;
- generate short and full cover-letter variants;
- support Russian and English content;
- render structured resume content to DOCX and PDF through a deterministic document pipeline.

### Application history and employer context

- track companies, vacancies, applications, statuses, and status history;
- preserve the exact resume version and generated materials associated with an application;
- store recruiter and employer conversation context;
- maintain Employer Memory of claims, salary expectations, work-format statements, and interview context;
- detect contradictions before generating new employer-facing content.

### Interview and outcomes

- prepare for interviews from the actual application history;
- store interview questions and answer context;
- record outcomes such as offer, rejection, withdrawal, expiration, or ghosting;
- provide analytics over the job-search process.

### Research knowledge

- maintain versioned research sources, findings, and rules;
- distinguish evidence from recommendations;
- use current research for recruiting, ATS, hiring-practice, integration, and technical decisions.

### Platform foundations

- invite-only multi-user accounts;
- MVP roles: `admin` and `user`;
- strict per-user data isolation;
- API-first architecture;
- initial local deployment on Mac through Docker Compose;
- responsive web UI / PWA for desktop and mobile web;
- provider-independent runtime AI architecture;
- support both system-managed and user-provided LLM credentials, with secure secret handling;
- Git/Markdown documentation usable through Obsidian;
- Figma-based design system.

## Explicitly out of scope for the initial product behavior

- automatic submission of job applications;
- autonomous messaging to employers without user approval;
- fabrication or inflation of candidate experience;
- opaque universal ATS scores presented as objective truth;
- AI-detector bypass features whose purpose is deception.

## Deferred until there is demonstrated need

- native mobile applications;
- browser extension implementation;
- Kubernetes;
- microservice decomposition of the core backend;
- Kafka or similar event-stream infrastructure;
- standalone vector database;
- GraphQL;
- fine-tuning;
- specialized Go services unless a concrete technical need is demonstrated.

## Pending later phases

The following are intentionally not frozen in Phase 01:

- exact dependency and framework versions;
- concrete authentication package or implementation approach;
- final ERD and database schema;
- detailed API contracts;
- final queue topology;
- component primitive library;
- design-token synchronization mechanism;
- job-board integration methods and restrictions;
- concrete LLM models and pricing;
- detailed model-routing thresholds;
- final canonical location of runtime product AI Skills;
- exact research-derived hiring recommendations.
