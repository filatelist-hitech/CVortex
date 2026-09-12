---
title: Phase 06 System Design
status: accepted
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, c4, deployment, phase-06]
related: ["[[Architecture-Baseline]]", "[[../03-ADR/INDEX|ADR Index]]", "[[../04-Data/Phase-06-Data-Design|Data Design]]", "[[../06-AI/Phase-06-AI-Design|AI Design]]"]
---

# Phase 06 System Design

## Boundaries and C4 views

CVortex is a modular Laravel control plane behind a shared API. Next.js/PWA owns presentation only; Laravel owns authorization, workflow transitions, Truth Guard and durable transactions. PostgreSQL is durable truth; Redis is cache/queue infrastructure. Imports, research, LLM work and conversion run asynchronously; short validation and review actions may be synchronous.

```mermaid
flowchart LR
  U[User] --> CV[CVortex]
  A[Admin] --> CV
  CV --> LLM[LLM providers]
  VS[Vacancy sources] --> CV
  ES[Documents and web sources] --> CV
  F[Figma reviewed design dependency] -. engineering .-> CV
```

```mermaid
flowchart TB
  N[Nginx] --> FE[Next.js / PWA]
  N --> API[Laravel API and modular control plane]
  API --> PG[(PostgreSQL)]
  API --> R[(Redis)] --> W[Laravel queue workers / Horizon]
  API --> FS[File storage abstraction / local storage]
  W --> DOC[DOCX renderer and LibreOffice headless]
  W --> PA[Provider adapters] --> LLM[External LLM provider]
  W --> EXT[Policy-gated vacancy/research adapters]
```

Trust boundaries: browsers and all external sources are untrusted; API authenticates and scopes requests; storage/converters are private worker boundaries; provider calls receive minimal authorised context. Future clients consume the same API, never direct storage/database access.

## Component contracts

| Component | Owns / outputs | Deterministic responsibility | Permitted LLM role / dependencies |
|---|---|---|---|
| Career | profiles, tracks, facts, achievements | lifecycle, confirmation, ownership | extract candidates only; source parser |
| Vacancies / Companies | snapshots, requirements, company context | source policy, normalization schema | semantic extraction/classification |
| Applications / Resume / Cover Letters | package state, versions, recommendations | state transitions, approval, provenance links | structured recommendations/content |
| Employer Memory / Conversations / Interviews | employer-scoped history | consistency lookup and blocking | summarize/prepare with approved context |
| Research | sources, findings, rules | source policy and provenance | bounded synthesis |
| Documents / Files | file identity, templates, render outputs | paths, hashes, rendering, access | never binary generation |
| AI Orchestration / Truth Guard | runs, policies, validations | routing gates, schemas, Fact→Claim→Content | semantic conflict detection only |
| Auth / Audit | invitation/access and append audit events | identity, authorization, event capture | none |

Each component receives server-derived owner scope, emits domain events or queued work after durable state, and records no secrets. No component can bypass Truth Guard for employer-facing output.

## Key flows

```mermaid
flowchart LR
  X[Untrusted vacancy] --> S[Source policy and raw snapshot] --> N[Parse/normalize] --> Q[Requirements] --> M[Match confirmed facts] --> R[Recommendation]
  CS[Source] --> E[Extraction] --> P[PENDING fact] --> HR[Human review] --> CF[CONFIRMED fact]
  CF --> CL[Claim] --> CB[Context selection] --> GC[Generated content] --> TG[Truth Guard] --> HA[Human approval]
  R --> AP[Application] --> RV[Approved resume/cover] --> READY[READY_TO_APPLY] --> MAN[Manual application] --> SH[Status history]
  EM[Prior claims, applications, conversations] --> CB
```

## Deployment

Local MVP is a Mac-hosted Docker Compose topology: Nginx, Next.js, Laravel API, PostgreSQL, Redis/Horizon workers, private local storage and an isolated LibreOffice converter. PostgreSQL and storage have persistent volumes; backups cover both consistently and are access-controlled and tested for restore. Secrets are environment-specific, outside images/Git, least-privileged and rotated through their owner boundary. Provider connectivity is outbound only from the adapter/worker boundary.

A VPS/cloud move replaces host storage/secret/backup operations and may replace the storage adapter, but preserves API, PostgreSQL authority, queues and domain boundaries. Kubernetes is not part of this design.
