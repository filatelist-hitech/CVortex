---
title: CVortex Research Index
status: draft
owner: project
created: 2026-09-12
updated: 2026-09-12
tags:
  - research
  - index
  - cvortex
related:
  - README.md
  - RESEARCH-PLAN.md
  - PHASE-04-SUMMARY.md
---

# CVortex Research Index

## Status vocabulary

```text
PLANNED
IN_PROGRESS
EVIDENCE_COLLECTED
SYNTHESIZED
REVIEWED
SUPERSEDED
```

Research result `REVIEWED` не означает автоматически `accepted architecture decision`.

---

# Phase 03 — Technical Research

| Area | IDs | Planned report | Status |
|---|---|---|---|
| Backend/runtime | R01-* | `technical/backend-runtime.md` | PLANNED |
| Laravel ecosystem | R02-* | `technical/laravel.md` | PLANNED |
| PostgreSQL | R03-* | `technical/postgresql.md` | PLANNED |
| Redis/Horizon | R04-* | `technical/redis-horizon.md` | PLANNED |
| Auth | R05-* | `technical/auth.md` | PLANNED |
| API architecture | R06-* | `technical/api-architecture.md` | PLANNED |
| Files/documents | R07-* | `technical/files-documents.md` | PLANNED |
| OpenAI API/models/pricing | R08-* | `technical/openai-api.md` | PLANNED |
| Provider abstraction | R09-* | `technical/provider-abstraction.md` | PLANNED |
| Prompt caching/batch | R10-* | `technical/prompt-caching-batch.md` | PLANNED |
| Figma/MCP/design tokens | R11-* | `technical/figma-design-tokens.md` | PLANNED |
| Security technical baseline | R14-* | `security/technical-threats.md` | PLANNED |
| Testing/tooling | R15-* | `technical/testing-tooling.md` | PLANNED |
| PWA/frontend | R16-* | `technical/frontend-pwa.md` | PLANNED |
| Technical licensing | R17-* | `technical/licensing.md` | PLANNED |

Expected synthesis: `technical/DECISION-CANDIDATES.md`.

**Repository check 2026-09-12:** Phase 03 completion artifacts/commit were not found on `stage`. Keep these rows `PLANNED` until technical research is actually executed/reconciled.

---

# Phase 04 — Product / Integrations / Hiring Research

Phase 04 was executed by explicit current task instruction even though Phase 03 is not evidenced as complete. This is recorded as a sequencing conflict and blocks Phase 05 execution until reconciled.

| Area | IDs | Actual report | Status |
|---|---|---|---|
| Unified vacancy sources | R12-01..07 | `integrations/vacancy-sources-matrix.md` | REVIEWED |
| ATS-hosted career platforms | R12-04, R12-06 | `integrations/ats-career-platforms.md` | REVIEWED |
| Import/fallback strategies | R12-06..07 | `integrations/integration-strategies.md` | REVIEWED |
| Platform Terms / access restrictions | R12-*, R17-04..06 | `integrations/platform-terms.md` | REVIEWED |
| ATS parsing / matching / screening | R13-01..03 | `recruitment/ats-and-screening.md` | REVIEWED |
| Recruiting AI | R13-02..03, R14-* | `recruitment/recruiting-ai.md` | REVIEWED |
| Resume practices | R13-01, R13-03, R13-05, R13-07 | `recruitment/resume-practices.md` | REVIEWED |
| Cover letters | R13-04..05 | `recruitment/cover-letters.md` | REVIEWED |
| Technical hiring | R13-06 | `recruitment/technical-hiring.md` | REVIEWED |
| RU/EU/US/UK comparison | R13-05 | `recruitment/market-comparison.md` | REVIEWED |
| Fintech/startup/enterprise comparison | R13-06, R13-08 | `recruitment/employer-type-comparison.md` | REVIEWED |
| Integration/external content threats | relevant R14-* | `security/external-content-threats.md` | REVIEWED |
| Phase synthesis | R12-*, R13-*, relevant R14/R17 | `PHASE-04-SUMMARY.md` | REVIEWED |

Earlier Phase 02 planned filenames such as `integrations/headhunter.md`, `integrations/linkedin-indeed.md`, `recruitment/regional-practices.md` and `security/integration-threats.md` were consolidated into the reviewed artifacts above to avoid file sprawl. Their research questions are not dropped; they are covered by the mapped reports.

---

# Research sequence

Canonical intended sequence remains:

```text
Phase 02 Research Plan
→ Phase 03 Technical Research
→ Phase 04 Product / Integrations / Hiring Research
→ Phase 05 Architecture Decision Freeze
```

Observed repository state differs:

```text
Phase 02 merged
Phase 03 completion NOT FOUND
Phase 04 completed by explicit task instruction
```

Do not hide this discrepancy by marking Phase 03 complete.

---

# P0 blocking queue

Initial P0 technical questions from Phase 02 remain applicable, including runtime/framework versions, auth, document security, OpenAI API/model/pricing, provider boundaries, Figma capabilities, security baseline, testing and frontend baseline.

Phase 04 has closed the product/integration P0 evidence for:

```text
R12-01
R12-02
R12-06
R12-07
R13-01
relevant R14 integration threats
R17-05
R17-06
```

This does not close Phase 03 technical items.

---

# Decision gates

## Gate A — Runtime

Still blocked by Phase 03 runtime research. Do not freeze PHP/Laravel/PostgreSQL/Redis versions from Phase 04.

## Gate B — Authentication

Still blocked by Phase 03 auth/security research.

## Gate C — LLM architecture

Still blocked by Phase 03 OpenAI/provider/caching research. Concrete model names remain configuration/research, not permanent architecture.

## Gate D — Document pipeline

Still blocked by Phase 03 document/security/licensing research.

## Gate E — Design foundation

Still blocked by Phase 03 Figma/token/licensing research.

## Gate F — Vacancy URL ingestion

Phase 04 provides source-specific API/Terms/fallback evidence. A future source adapter still requires:

```text
API capability
Terms / API Terms
allowed retrieval
security implications
freshness re-check
```

Generic URL fetching additionally requires the technical SSRF architecture work from Phase 03/05.

---

# Phase 04 completion validation

Verified in the reviewed artifacts:

- all requested named Vacancy Sources appear in the unified matrix;
- public, partner-only and customer-only APIs are distinguished;
- documented APIs are separated from internal/undocumented endpoints;
- OAuth is not treated as useful when it belongs to a different vendor use case;
- undocumented rate limits are marked `UNKNOWN` / `NOT PUBLICLY DOCUMENTED`;
- ToS, API Terms, robots and project interpretation are separated;
- fallback strategy is defined per source class;
- ATS parsing, automated screening, recruiting AI and matching are researched;
- resume/cover/technical hiring practices are researched;
- RU/EU/US/UK are compared without pretending they are homogeneous;
- fintech/startup/enterprise claims remain LOW confidence where evidence was insufficient;
- external-content threats are recorded;
- no fake universal ATS score is introduced;
- no product code or accepted ADR is created by Phase 04.

---

# Current index state

```text
Phase 02: MERGED / completed by repository evidence
Phase 03: NOT VERIFIED / remains PLANNED
Phase 04: REVIEWED / completed out of sequence by explicit task
Product code: none introduced by Phase 04
Accepted architecture decisions created by Phase 04: none
```

## Next phase candidate

```text
05-architecture-decision-freeze
```

**BLOCKED:** do not start Phase 05 until missing Phase 03 technical research is completed or repository evidence is found and state is reconciled.
