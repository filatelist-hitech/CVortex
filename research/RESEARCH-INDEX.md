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

---

# Phase 04 — Product / Integrations / Hiring Research

| Area | IDs | Planned report | Status |
|---|---|---|---|
| HeadHunter | R12-01 | `integrations/headhunter.md` | PLANNED |
| LinkedIn / Indeed | R12-02 | `integrations/linkedin-indeed.md` | PLANNED |
| RU/other job boards | R12-03 | `integrations/job-boards.md` | PLANNED |
| ATS-hosted career platforms | R12-04 | `integrations/ats-platforms.md` | PLANNED |
| Remote/international boards | R12-05 | `integrations/remote-job-sources.md` | PLANNED |
| Generic career pages | R12-06 | `integrations/company-career-pages.md` | PLANNED |
| Source fallback strategy | R12-07 | `integrations/fallback-strategy.md` | PLANNED |
| ATS/resume parsing | R13-01..03 | `recruitment/ats-screening.md` | PLANNED |
| Cover letters | R13-04 | `recruitment/cover-letters.md` | PLANNED |
| Regional CV practices | R13-05 | `recruitment/regional-practices.md` | PLANNED |
| Hiring workflows | R13-06 | `recruitment/hiring-workflows.md` | PLANNED |
| Evidence quality | R13-07..08 | `recruitment/evidence-policy.md` | PLANNED |
| Integration security extension | relevant R14-* | `security/integration-threats.md` | PLANNED |
| ToS/data-use licensing | R17-04..06 | `integrations/platform-terms.md` | PLANNED |

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

# Current index state

```text
Phase: 02 Research Plan

Research questions:
- defined

Research execution:
- not started

Architecture decisions created by Phase 02:
- none

Product code:
- none
```

## Next permitted phase

```text
03-technical-research
```

Phase 03 должен выполнять исследования из этого index, а не начинать реализацию stack.