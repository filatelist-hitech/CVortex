---
title: CVortex Research Index
status: reviewed
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

| Area | IDs | Actual report | Status |
|---|---|---|---|
| Backend/runtime/PostgreSQL | R01-*, R02-*, R03-* | `technical/01-BACKEND-RUNTIME-DATA.md` | REVIEWED |
| Laravel auth/security | R02-*, R05-*, R14-* | `technical/02-LARAVEL-AUTH-SECURITY.md` | REVIEWED |
| Redis/queues/Horizon | R04-* | `technical/03-REDIS-QUEUES-HORIZON.md` | REVIEWED |
| OpenAI API/models/cost controls | R08-*, R10-* | `technical/04-OPENAI-API-MODELS.md` | REVIEWED |
| PHP/OpenAI provider integration | R09-* | `technical/05-OPENAI-PHP-INTEGRATION.md` | REVIEWED |
| PWA/frontend | R16-* | `technical/06-FRONTEND-STACK.md` | REVIEWED |
| Component primitives | R16-* | `technical/07-COMPONENT-PRIMITIVES.md` | REVIEWED |
| Testing/tooling | R15-* | `technical/08-TESTING-STACK.md` | REVIEWED |
| Files/documents | R07-* | `technical/09-DOCUMENT-PIPELINE.md` | REVIEWED |
| Figma/MCP/design tokens | R11-*, R17-* | `technical/10-DESIGN-TOKENS-FIGMA.md` | REVIEWED |
| Decision synthesis | R01-*..R17-* | `technical/DECISION-CANDIDATES.md` | REVIEWED |
| MCP Gateway Foundation (2026-09-25) | bounded feature research | `technical/11-MCP-GATEWAY-FOUNDATION.md` | EVIDENCE_COLLECTED |
| Source register | R01-*..R17-* | `technical/SOURCES.md` | REVIEWED |

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

**Repository reconciliation 2026-09-12:** Phase 03 artifacts were subsequently added to `stage`, reviewed and reconciled before Phase 05. The reports remain research evidence; their recommendations became architecture only where an accepted Phase 05 ADR says so.

---

# Phase 04 — Product / Integrations / Hiring Research

Phase 04 was originally executed before Phase 03 artifacts were present. The sequence was later reconciled on `stage` before Phase 05; the original finding remains documented in `PHASE-04-SUMMARY.md` as historical context.

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

Historical repository sequence observed during Phase 04:

```text
Phase 02 merged
Phase 03 completion was not present at that time
Phase 04 completed by explicit current task
```

This was later reconciled: Phase 03 reports are now present and reviewed, Phase 05 evaluated them together with Phase 04 evidence, and the accepted decisions are indexed in `docs/03-ADR/INDEX.md`.

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

Phase 04 collected evidence for the R12/R13/integration-related R14/R17 items above. Phase 03 technical questions were closed separately by the reviewed reports under `research/technical/`; neither research phase accepted architecture decisions directly.

---

# Decision gates

These were the evidence gates used before Phase 05. Phase 03/04 research satisfied the gates needed for the accepted architecture baseline. Implementation-specific and freshness-sensitive checks remain deferred as recorded in the ADRs.

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
Phase 03: REVIEWED / technical evidence complete and reconciled
Phase 04: REVIEWED / product, integration, hiring and security evidence complete
Phase 05: COMPLETED / accepted decisions are indexed in docs/03-ADR/INDEX.md
Architecture decisions created by research phases: none directly
Product code created by research phases: none
```

## Next bounded phase

```text
06-product-data-ai-security-design
```

No active research prerequisite blocks Phase 06. Phase 06 must work inside the accepted Phase 05 ADR boundaries.
