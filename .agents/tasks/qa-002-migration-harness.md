---
title: QA-002 — Deterministic migration boundary repair
status: completed-local-verified
owner: project
created: 2026-10-12
updated: 2026-10-12
execution:
  workflow: implementation
  orchestration: native
  agents: 1
  parallelism: false
---

# QA-002

Explicit owner IMPLEMENT override of `NEXT=b01-current-manifest-integrity-reconciliation`.
Base/target: `stage`; branch: `fix/qa-002-migration-harness`. NEXT is preserved.
Owner authorizes isolated fixture cleanup, commit, normal push and PR; no merge.

Replace fixed rollback counts in the duplicate-vacancy migration harness with
named historical boundaries: legacy through `2026_09_19_000008_create_vacancy_core_tables`,
then `000009_remediate_vacancy_core_review_findings` and Vacancy repairs `000010`–`000012`.
Use native Laravel path/batch operations; preserve production migrations and ADRs.

All mutations must occur in a newly created Compose project, PostgreSQL container,
owned volume and database with ephemeral credentials. Reject external connection
selectors before Docker; assert identity and schema before seeding. Cleanup may
remove only resources created by this invocation, and cleanup failures must fail.

Validate QA002-01–13: real legacy schema and missing remediation; duplicate seed;
reconciliation; snapshots/analyses/LLM history; chronology/status/errors; unique
index/owner relationships; named rollback/reapply; unrelated future migration;
wrong-schema FAIL; non-disposable target BLOCKED before write; owned cleanup.

Run shell/Python static checks, harness unit tests and exact isolated PostgreSQL
runs; record SHA, sequence, schema/counts/exit/cleanup and read-only development
preservation evidence. No full audit, other QA fixes or production DB migration.
Update current QA-002 status, testing docs and STATUS; preserve historical FAIL.
Deliver governed PR to stage, verify CI, STOP. Other existing gates stay open.
