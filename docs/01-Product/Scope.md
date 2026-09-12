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
  - "[[Roadmap]]"
---

# Scope

This document records the approved product direction. Detailed architecture, data, API and AI contracts are defined by accepted design documents/ADRs; implementation sequencing is defined by [[Roadmap]].

## In scope for the product direction

### Career context

- maintain a master career profile and Career Fact Base;
- support multiple Career Tracks without contradictory biographies;
- import existing resume material;
- extract potential facts for manual confirmation;
- retain provenance for important facts and generated claims.

### Vacancy workflow

- accept pasted vacancy text as the universal MVP baseline, files later, and URL-based ingestion where research permits it;
- preserve raw vacancy content alongside normalized data;
- extract/classify requirements;
- compare requirements against confirmed candidate facts;
- identify fit, gaps and risks by meaningful dimensions rather than a fake universal ATS percentage;
- produce an explainable apply/maybe/skip-style recommendation.

### Application package

- recommend resume changes with rationale, evidence and risk;
- support Accept / Edit / Reject;
- create tailored resume versions;
- generate short and standard/full cover variants as required;
- support Russian and English content;
- render structured resume content to DOCX/PDF through the deterministic document pipeline.

### Application history and employer context

- track companies, vacancies, applications, statuses and history;
- preserve exact resume/material versions for an application;
- store recruiter/employer conversation context;
- maintain Employer Memory of claims and critical employer-specific statements;
- detect contradictions before new employer-facing content is approved.

### Interview and outcomes

- prepare for interviews from actual application history;
- store interview questions/answer context;
- record offers, rejection, withdrawal, expiration and ghosting;
- provide analytics over real job-search outcomes.

### Research knowledge

- maintain versioned research sources, findings and rules;
- distinguish evidence from recommendations;
- use current research for recruiting, ATS, integrations and freshness-sensitive technical decisions.

### Platform foundations

- invite-only multi-user accounts;
- roles `admin` and `user`;
- strict per-user isolation;
- API-first architecture;
- local Mac Docker Compose deployment;
- responsive web/PWA;
- provider-independent runtime AI;
- system-managed and BYOK LLM credentials with secure secret handling when the product slice requires them;
- Git/Markdown/Obsidian documentation;
- Figma-based design system and Git-held design tokens.

## Explicitly out of scope for initial product behavior

- automatic job application submission;
- autonomous employer messaging without explicit user approval;
- fabrication/inflation of candidate experience;
- opaque universal ATS scores presented as truth;
- AI-detector bypass features whose purpose is deception.

## Deferred until demonstrated need

- native mobile;
- browser extension implementation;
- Kubernetes;
- microservice decomposition of the core backend;
- Kafka/event-stream infrastructure;
- standalone vector DB;
- GraphQL;
- fine-tuning;
- specialized Go services without a concrete technical need.

## Resolved architecture items

The following are no longer pending conceptual questions:

- runtime product AI Skills canonical location is `/runtime-ai/` (ADR-0018);
- Figma is reviewed visual/component authority and Git-held DTCG tokens are machine-readable authority;
- strict Truth Guard, provider-independent AI, shared-schema ownership, API-first and deterministic document rendering are accepted ADR decisions.

Exact dependency versions, provider/model mappings, pricing and implementation-level thresholds remain freshness-sensitive configuration.
