---
title: CVortex M1.3R — Vacancy Core Remediation Review
status: ready
milestone: m1-first-value
slice: m1-3-vacancy-core
owner: independent-reviewer
created: 2026-09-19
updated: 2026-09-23
tags: [task, review, vacancy, security, concurrency]
execution:
  workflow: review
related:
  - m1-3-vacancy-core.md
  - ../../docs/04-Data/M1-3-Vacancy-Core.md
---

# CVortex M1.3R — Vacancy Core Remediation Review

Independently review the M1.3 remediation without changing repository files. Do not assume implementation claims or prior validation are correct.

Verify all four findings against executable behavior:

- runtime PostgreSQL role is neither superuser nor BYPASSRLS; forced RLS isolates every user-owned Vacancy table and fails closed without server-derived owner context;
- concurrent same-owner/same-URL changed-content imports converge on one aggregate with unique monotonic snapshot versions;
- system/assistant/developer, recommendation manipulation, fake JSON/XML and ignore/override instruction families cannot become requirements, while legitimate system/API/prompt-engineering requirements survive;
- VacancySnapshot updates fail through Eloquent and raw PostgreSQL while new content creates a new snapshot.

Re-run relevant full regressions, disposable PostgreSQL migration rollback/re-up, role/RLS inspection and the real concurrency harness. Confirm existing matching, provenance, stale-analysis, URL-metadata-only and no-ATS invariants remain intact.

Return one evidence-backed `PASS` or `CHANGES REQUIRED` verdict and STOP. Only an independent `PASS` may authorize M1.4.
