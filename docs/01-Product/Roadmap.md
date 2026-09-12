---
title: CVortex Product Roadmap
status: accepted
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags: [product, roadmap, milestones, vertical-slices]
related:
  - "[[Vision]]"
  - "[[Scope]]"
  - "[[Phase-06-Product-Design]]"
  - "[[../02-Architecture/Architecture-Baseline|Architecture Baseline]]"
---

# CVortex Product Roadmap

## Strategy

CVortex development after the completed Foundation Era is value-driven rather than subsystem-driven.

The previous sequence:

```text
Infrastructure → Auth → Career → Vacancies → Matching → Application Package
```

is superseded as an execution roadmap because it delays the first usable end-to-end workflow until multiple large horizontal phases are complete.

The accepted architecture remains unchanged. Laravel stays the control plane; PostgreSQL remains durable truth; Redis/Horizon remains the asynchronous execution layer; the shared API boundary, multi-user isolation, Truth Guard, provider-independent AI, deterministic document rendering, local-first deployment, design-token authority and untrusted-input boundaries remain authoritative through the accepted ADR set.

The change is sequencing and task size:

> Build the thinnest secure vertical slice that produces observable user value, validate it with real usage, then deepen it.

## Foundation Era

Phases `00` through `07` are complete and retained as historical/architectural foundation:

```text
00 AI System Bootstrap
01 Project Knowledge Bootstrap
02 Research Plan
03 Technical Research
04 Product / Integrations / Hiring Research
05 Architecture Decision Freeze
06 Product / Data / AI / Security Design
07 Design Foundation
```

No completed foundation phase is reopened merely because the implementation roadmap changed.

## Active milestone map

```text
M0 Runnable Core
  ↓
M1 First Value
  ├── M1.1 Access Core
  ├── M1.2 Career Core
  ├── M1.3 Vacancy Core
  └── M1.4 Application Draft
  ↓
Preview 0.1
  ↓
M2 Real Application Package
  ↓
MVP 0.2
  ↓
M3 Imports & Integrations
  ↓
M4 Employer Journey
  ↓
M5 Outcomes & Analytics
  ↓
M6 Distribution & Hardening
```

## M0 — Runnable Core

Goal: make CVortex a real locally runnable application before adding business features.

Deliver only the technical baseline needed by subsequent slices:

- Laravel backend;
- Next.js/React/TypeScript frontend;
- PostgreSQL;
- Redis + Horizon;
- Nginx single-origin entry point;
- Docker Compose;
- private local storage baseline;
- design-token consumption;
- health/readiness;
- deterministic Make/developer workflow;
- minimal CI/quality baseline where immediately useful.

M0 is done only when the stack actually starts and the browser/API/dependencies are observable. Infrastructure polishing beyond the acceptance contract is deferred.

User-visible checkpoint: CVortex opens in the browser and the real stack is running.

## M1 — First Value

M1 proves the product's core hypothesis with a secure end-to-end workflow before implementing broad integrations and document automation.

### M1.1 — Access Core

Implement only the access/security surface needed to safely use the first product slices:

- first-party same-origin session authentication;
- invite-only registration;
- `admin` / `user` roles;
- `ACTIVE` / `DISABLED` account state;
- server-side ownership/authorization pattern;
- bootstrap-admin/operator flow;
- mandatory cross-user negative tests.

Defer broad admin tooling, provider-specific credentials UI and generic secret-vault product behavior. Hardened Phase 09 decisions remain reference material and are introduced only when the owning slice requires them.

### M1.2 — Career Core

Goal: establish enough confirmed candidate truth for vacancy matching and generation.

First path:

```text
paste career/resume text
→ semantic extraction
→ PENDING facts
→ Confirm / Edit / Reject
→ CONFIRMED Career Facts
→ valid Claims
```

Implement minimum CareerProfile/CareerFact/provenance/Claim/Truth Guard contracts required by this path.

Do not require DOCX/PDF upload in the first slice. File import belongs to M3.

### M1.3 — Vacancy Core

First ingestion mode is pasted vacancy text.

```text
paste vacancy
→ preserve source snapshot
→ extract requirements
→ match against CONFIRMED facts/valid Claims
→ explain dimensions and gaps
→ STRONGLY_APPLY / APPLY / MAYBE / LOW_PRIORITY / SKIP
```

Required dimensions stay explainable: Technical, Experience, Domain, Language, Location, Work format and Salary where data exists.

Do not implement generic URL fetching, job-board adapters or SSRF-heavy integration infrastructure here. Those belong to M3.

### M1.4 — Application Draft

Connect Career Core and Vacancy Core into the first end-to-end CVortex workflow:

```text
confirmed facts + vacancy
→ match
→ resume recommendations
→ Truth Guard
→ Accept / Edit / Reject
→ short + standard cover draft
→ Truth Guard
→ explicit user approval
```

Resume recommendation UI uses:

```text
Before
After
Reason
Evidence
Risk
```

No automatic application submission. No DOCX/PDF requirement yet.

### Preview 0.1 exit

Preview 0.1 exists when a user can:

1. sign in safely;
2. establish confirmed career facts;
3. paste a real vacancy;
4. receive an explainable match/gap recommendation;
5. review truthful resume recommendations;
6. generate and approve a truthful cover letter.

This is the first mandatory product-value checkpoint.

## M2 — Real Application Package

Turn Preview 0.1 into a practical application-preparation system:

- Company and Application;
- ApplicationStatusHistory;
- ResumeVersion / ResumeChange;
- CoverLetter versions;
- ClaimUsage;
- basic Employer Memory;
- EmployerConsistencyCheck;
- deterministic READY_TO_APPLY criteria;
- Structured Resume → DOCX → LibreOffice → PDF;
- private generated-file storage/download;
- manual `Mark as applied` confirmation;
- system-managed and BYOK credential handling with encrypted-at-rest secret storage.

MVP 0.2 is reached when the user can prepare, download and manually submit a traceable application package while employer contradictions are blocked or explicitly resolved.

## M3 — Imports & Integrations

Automate input only after the core workflow proves useful:

Career side:

- safe DOCX resume import;
- PDF import only with validated parser behavior;
- file provenance and malicious-file controls.

Vacancy side:

- supported URL ingestion;
- policy-approved ATS/job-board adapters;
- source-specific fetching only where research permits it;
- SSRF-safe network boundary;
- raw snapshot/version preservation.

Manual paste remains the universal fallback.

## M4 — Employer Journey

Deepen consistency and context after real applications exist:

- recruiter/employer conversation import;
- conversation fact extraction through the same confirmation rules;
- advanced Employer Memory;
- prior claim/salary/work-format consistency;
- interview preparation;
- interview questions/history;
- explicit conflict resolution.

Employer Memory remains derived, scoped and evidence-backed rather than a freeform AI memory dump.

## M5 — Outcomes & Analytics

Track real search outcomes and learn from them:

- application lifecycle through offer/rejection/withdrawal/ghosting/expiry;
- response rate;
- interview conversion;
- offer conversion;
- source effectiveness;
- Career Track effectiveness;
- resume/cover strategy effectiveness.

Analytics must be based on real recorded outcomes, not decorative dashboards over empty data.

## M6 — Distribution & Hardening

Only after sustained local use:

- VPS/cloud deployment path;
- backup/restore validation;
- production observability;
- retention/deletion operations;
- PWA installability/polish;
- richer admin UX where proven necessary;
- performance/security/operational hardening.

This milestone does not imply Kubernetes, microservices or other deferred complexity.

## Execution rules

1. One task spec should normally produce one observable system or user outcome.
2. Do not create a large subsystem task when a smaller vertical slice can validate the same product risk.
3. Future milestone detail is written just-in-time; avoid pre-writing multi-thousand-line execution contracts far ahead of implementation.
4. Repository policies, accepted ADRs and cross-cutting invariants are referenced rather than copied into every task.
5. Security, authorization, Truth-first, tests and documentation remain completion requirements even when a task spec is short.
6. Research is targeted to a concrete freshness-sensitive decision; accepted architecture is not repeatedly re-researched.
7. Each slice ends in real validation and STOP before the next slice.

## Legacy phase mapping

The previous Phase 08–12 specifications are retained only as requirement/reference inventory and are not executable roadmap authority.

```text
old Phase 08 → M0 Runnable Core
old Phase 09 → M1.1 Access Core + M2 credentials/hardening
old Phase 10 → M1.2 Career Core + M3 resume-file import
old Phase 11 → M1.3 Vacancy Core + M3 URL/source adapters
old Phase 12 → M1.4 Application Draft + M2 Real Application Package + M4 Employer Journey
```

The current authorized task is always defined by `.agents/state/NEXT.md`, not by a legacy task filename.
