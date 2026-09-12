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

All inputs are authorized at the API/worker boundary using authenticated server identity; no component accepts frontend `user_id` as ownership evidence. `Owned data` is private unless marked system-owned. Events follow durable transactions; queue work carries server-derived owner scope. LLM output is untrusted candidate output until deterministic validation completes.

| Component | Responsibility | Deterministic / AI boundary |
|---|---|---|
| Career | Profile and fact lifecycle | Code enforces lifecycle/ownership/human confirmation; LLM may extract candidates, never confirm |
| Vacancies | Source capture, snapshots, requirements | Code enforces source/schema; LLM may classify/extract, never grant trust |
| Companies | Shared reference/private employer context | Code enforces reference/private boundary; LLM never merges private contexts |
| Applications | Application lifecycle/status history | Code enforces transitions and ownership; LLM does not decide state |
| Resume | Versioned resume/recommendations | Code enforces provenance/approval; LLM proposes structured content, never approves/renders |
| Cover Letters | Employer-specific letter versions | Code enforces provenance/consistency/approval; LLM drafts only |
| Employer Memory | Employer-scoped approved history | Code scopes/blocks contradictions; LLM may summarize approved context |
| Conversations | Recruiter conversation import | Source remains untrusted; LLM may extract/summarize, never confirm or obey source instructions |
| Interviews | Preparation/history | Code owns history; LLM may prepare questions, never assert new facts |
| Research | Versioned research sources/findings/rules | Source/provenance policy is deterministic; LLM synthesis is bounded |
| Documents / Files | Private files and deterministic rendering | LLM has no binary/filesystem authority |
| AI Orchestration | Provider/model/Skill/workflow routing | Policy/schema/routing deterministic; LLM only for designated semantic work |
| Truth Guard | Validate candidate-facing content | Fact→Claim→Content/provenance outcomes deterministic; LLM may assist semantic detection only |
| Audit | Append security/business events | No LLM |
| Auth / Authorization | Resolve actor and allow/deny | No LLM |

No component can bypass Truth Guard for employer-facing output or use cross-user Claim, CareerFact, context or artifact.

## Key flows

```mermaid
flowchart LR
  X[Untrusted vacancy] --> S[Source policy/raw snapshot] --> N[Parse/normalize] --> Q[Requirements] --> M[Match confirmed facts] --> R[Recommendation]
  CS[Source] --> E[Extraction] --> P[PENDING fact] --> HR[Human review] --> CF[CONFIRMED fact]
  CF --> CL[Claim] --> CB[Context selection] --> GC[Generated content] --> TG[Truth Guard] --> HA[Human approval]
  R --> AP[Application] --> RV[Approved resume/cover] --> READY[READY_TO_APPLY] --> MAN[Manual application] --> SH[Status history]
  EM[Prior claims/applications/conversations] --> CB
```

## Deployment

Local MVP is a Mac-hosted Docker Compose topology: Nginx, Next.js, Laravel API, PostgreSQL, Redis/Horizon workers, private local storage and an isolated LibreOffice converter when the document milestone activates it. PostgreSQL/storage persistence is access-controlled and backed up/restored as the deployment matures. Secrets are environment-specific and outside Git/images. Provider connectivity is outbound only from the adapter/worker boundary.

A VPS/cloud move replaces host storage/secret/backup operations and may replace the storage adapter, but preserves API, PostgreSQL authority, queues and domain boundaries. Kubernetes is not part of this design.
