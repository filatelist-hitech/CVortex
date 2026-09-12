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

Expected synthesis:

```text
technical/DECISION-CANDIDATES.md
```

Этот файл содержит только:

- options;
- evidence;
- trade-offs;
- recommendation candidates;
- unresolved questions.

Он не является ADR.

**Repository check 2026-09-12:** Phase 03 completion artifacts/commit were not found on `stage`. Keep these rows `PLANNED` until technical research is actually executed or its artifacts are located and reconciled.

---

# Phase 04 — Product / Integrations / Hiring Research

Phase 04 was executed by explicit current task instruction even though Phase 03 is not evidenced as complete. This sequencing conflict is recorded in project state and blocks Phase 05 execution until reconciled.

| Area | IDs | Actual report | Status |
|---|---|---|---|
| Unified vacancy sources | R12-01..07 | `integrations/vacancy-sources-matrix.md` | REVIEWED |
| ATS-hosted career platforms | R12-04, R12-06 | `integrations/ats-career-platforms.md` | REVIEWED |
| Source/fallback strategy | R12-06..07 | `integrations/integration-strategies.md` | REVIEWED |
| Platform Terms / data use | R12-*, R17-04..06 | `integrations/platform-terms.md` | REVIEWED |
| ATS/resume parsing/screening | R13-01..03 | `recruitment/ats-and-screening.md` | REVIEWED |
| Recruiting AI | R13-02..03 | `recruitment/recruiting-ai.md` | REVIEWED |
| Resume practices | R13-01, R13-03, R13-05, R13-07 | `recruitment/resume-practices.md` | REVIEWED |
| Cover letters | R13-04 | `recruitment/cover-letters.md` | REVIEWED |
| Regional CV practices | R13-05 | `recruitment/market-comparison.md` | REVIEWED |
| Hiring workflows | R13-06 | `recruitment/technical-hiring.md` | REVIEWED |
| Employer-type evidence | R13-06, R13-08 | `recruitment/employer-type-comparison.md` | REVIEWED |
| Integration security extension | relevant R14-* | `security/external-content-threats.md` | REVIEWED |
| Phase synthesis | R12-*, R13-*, relevant R14/R17 | `PHASE-04-SUMMARY.md` | REVIEWED |

Earlier Phase 02 planned filenames such as `integrations/headhunter.md`, `integrations/linkedin-indeed.md`, `recruitment/regional-practices.md` and `security/integration-threats.md` were consolidated into the reviewed artifacts above to avoid meaningless file sprawl. Their research questions remain covered.

---

# Research sequence

Recommended ordering is driven by blockers, not category number.

```text
Wave A — foundation blockers

R01 runtime
R02 Laravel
R08 OpenAI API
R11 Figma MCP
R16 frontend baseline

        ↓

Wave B — architecture inputs

R03 PostgreSQL
R04 Redis/Horizon
R05 Auth
R06 API
R07 Documents
R09 Provider abstraction
R10 Caching/Batch
R15 Testing

        ↓

Wave C — cross-cutting validation

R14 Security
R17 Licensing

        ↓

Phase 03 synthesis

technical/DECISION-CANDIDATES.md

        ↓

Phase 04

R12 Integrations
R13 Recruitment
R14 Integration threats
R17 Platform terms
```

Dependencies may alter this ordering when evidence reveals new blockers.

Observed repository sequence currently differs:

```text
Phase 02 merged
Phase 03 completion NOT FOUND
Phase 04 completed by explicit current task
```

Do not silently mark Phase 03 complete.

---

# P0 blocking queue

Initial P0 questions:

```text
R01-01
R01-02

R02-01
R02-02

R03-01

R04-01

R05-01
R05-02
R05-05

R06-01

R07-01
R07-05

R08-01
R08-02
R08-03
R08-04
R08-08

R09-01
R09-04

R11-01
R11-05

R12-01
R12-02
R12-06
R12-07

R13-01

R14-01
R14-02
R14-03
R14-04
R14-05
R14-06
R14-07

R15-07

R16-01
R16-03
R16-04

R17-01
R17-05
R17-06
```

Priority должна пересматриваться при обнаружении новых dependencies.

Phase 04 has collected evidence for the R12/R13/integration-related R14/R17 items above, but this does not close missing Phase 03 technical questions.

---

# Decision gates

## Gate A — Runtime

Нельзя freeze:

- PHP version;
- Laravel version;
- PostgreSQL version;
- Redis version;

пока соответствующие P0 research items не закрыты.

## Gate B — Authentication

Нельзя freeze auth architecture до завершения:

```text
R05-01
R05-02
R05-05
R14-06
```

## Gate C — LLM architecture

Нельзя freeze provider/model execution architecture до завершения как минимум:

```text
R08-01
R08-02
R08-03
R08-04
R08-08
R09-01
R09-04
R10-01
```

Конкретное имя модели не является постоянным architecture decision.

## Gate D — Document pipeline

Нельзя freeze document implementation до:

```text
R07-01
R07-03
R07-05
R17-02
```

## Gate E — Design foundation

Нельзя freeze Figma/token workflow до:

```text
R11-01
R11-03
R11-05
R11-06
R17-04
```

## Gate F — Vacancy URL ingestion

Нельзя реализовывать конкретный source adapter до проверки:

```text
API capability
Terms of Service
allowed retrieval
security implications
```

для соответствующего source.

Generic URL fetching дополнительно блокируется результатами SSRF research.

Phase 04 now provides source-specific evidence for this gate, but implementation remains prohibited.

---

# Research completion metrics

Tracking вести по вопросам, а не по количеству созданных Markdown-файлов.

Минимально считать:

```text
total questions
planned
in progress
evidence collected
reviewed
blocked
high-confidence
conflicted
```

Не использовать процент прогресса как замену смысловой оценке.

---

# Phase 04 validation

Verified in reviewed artifacts:

- every named Vacancy Source is present in the unified matrix;
- public, partner-only and customer-only APIs are distinguished;
- documented APIs are separated from private/internal endpoints;
- OAuth is not asserted without a relevant confirmed use case;
- undocumented rate limits are marked unknown/not publicly documented;
- ToS, API Terms, robots and CVortex interpretation are separated;
- realistic fallback strategies are defined;
- ATS parsing, automated screening, recruiting AI and skill matching are covered;
- resume practices, cover letters and technical hiring are covered;
- RU/EU/US/UK are compared without claiming homogeneity;
- fintech/startup/enterprise differences remain low-confidence where evidence was insufficient;
- external-content security findings are documented;
- no fake universal ATS score is introduced;
- no product code or accepted ADR is created by Phase 04.

---

# Current index state

```text
Phase 02: MERGED / repository evidence confirms research plan
Phase 03: NOT VERIFIED / remains PLANNED
Phase 04: REVIEWED / completed out of sequence by explicit current task
Architecture decisions created by Phase 04: none
Product code created by Phase 04: none
```

## Next phase candidate

```text
05-architecture-decision-freeze
```

**BLOCKED:** do not start Phase 05 until missing Phase 03 technical research is completed or located and project state is reconciled.
