---
title: Deterministic PostgreSQL migration testing
status: implemented
created: 2026-10-12
updated: 2026-10-12
tags: [qa, postgresql, migrations, isolation]
---

# Duplicate vacancy migration harness

Run from a checkout with Docker and Python 3 available. Build the current backend
image with locked dependencies first (`docker compose build backend`) if it is
missing or outdated. The harness uses its immutable local image ID and current
tracked backend source, including local edits. It does not install dependencies.

```bash
bash scripts/test-vacancy-postgres-duplicate-migration.sh
bash scripts/test-vacancy-postgres-duplicate-migration.sh --regression-controls
python3 -m unittest discover -s scripts -p 'test_vacancy_migration_harness.py'
```

Do not export `DB_*`, `POSTGRES_*`, `PG*` or `COMPOSE_*` selectors for these commands:
they are rejected before Docker is invoked. There is no external database option.
Repository `.env` and Compose files are not read by the test stack.

Every invocation creates a unique `qa002_<random>` Compose project, PostgreSQL
18.6 container, internal network, database, admin role/password and PostgreSQL/vendor
volumes. No ports are published. Runner source is copied to a private temporary
directory; `.env`, stored files, signing keys, cached configuration and shared vendor
volumes are excluded. The runtime grant role is local to this instance and NOLOGIN;
this harness exercises migration behavior through the administrative role, not
runtime RLS access. Runtime RLS tests remain separate.

Before provisioning, resource names must be unused. After initialization and before
fixture/migration writes, the harness verifies container/project labels, owned volume
mounts, no foreign container using those volumes, empty public schema, database/role
identity, ephemeral runner credentials and effective Laravel connection. PostgreSQL
initialization itself only targets the freshly owned container/volume. Existing
development/production containers are never migration targets.

## Boundary and sequence

1. Apply sorted migration files through
   `2026_09_19_000008_create_vacancy_core_tables`, one explicit `--path` at a time.
   Require the exact legacy ledger. Assert seven Vacancy tables, validated owner
   foreign keys, version/chronology columns and version uniqueness; no remediation,
   owner/source unique index, snapshot immutability trigger or enabled Vacancy RLS.
2. Seed the original synthetic history: one user, eight vacancies, ten snapshots,
   five analyses and three extraction runs. No candidate/employer facts are used.
3. Apply named repairs, individually:
   `2026_09_19_000009_remediate_vacancy_core_review_findings`,
   `2026_09_21_000010_allow_reappearing_vacancy_snapshot_content`,
   `2026_09_24_000011_reconcile_vacancy_aggregate_state`,
   `2026_09_24_000012_recover_completed_extraction_without_analysis`.
4. Assert convergence to five vacancies; preserve all snapshot/analysis/run rows
   and their payload/history fingerprints. Check chronological versions, latest
   status/error codes, owner relationships, partial unique `(owner_id, source_url)
   `WHERE source_url IS NOT NULL`, immutable trigger and forced RLS/policies.
5. Roll back **only** those four named repairs, in reverse order. Resolve each
   migration's actual batch; require it to be the last recorded migration and the
   only member of that batch. Pass explicit `--batch` and `--path`; verify the ledger
   removes exactly that name and fixture fingerprints stay unchanged. No step count.
6. Assert the restored pre-remediation schema, then reapply the same four paths.
   `000009.down()` removes RLS/policies/trigger/index; `000010`–`000012.down()` retain
   corrected data intentionally. Duplicate aggregates are not resurrected.
7. Apply all remaining migrations in Laravel's normal sorted order and verify the
   complete ledger, schema and fixture history again. No rollback after this step.

The control run copies an unrelated `2099` migration into **only** the temporary
source before setup; it remains absent at legacy seed and runs during final upgrade.
It also creates an incompatible unique index inside a transaction and requires the
legacy assertion to fail with nonzero psql exit; disconnect rolls that transaction
back. This checks real catalog state rather than migration records alone.

## Failure and cleanup

Every violated invariant returns nonzero and identifies the failing stage/schema.
SQL comparisons use NULL-safe assertions. PASS is printed only after cleanup passes.
On success, failure, SIGINT or SIGTERM, cleanup checks ownership again and removes
only this project's containers, volumes and network. It verifies their absence.
An ownership mismatch or cleanup failure returns FAIL/BLOCKED and retains the
private temporary directory for diagnosis; no broad prune or database DROP is used.
SIGKILL/host failure cannot run cleanup: inspect the recorded project and ownership
before manual recovery. Temporary Compose credentials must never be committed.

This fixes [QA-002](FUNCTIONAL-DEFECT-REGISTER.md). Other PostgreSQL harnesses and
QA findings are separate scopes; this change does not validate or repair their
fixed-count rollback behavior. Historical audit logs/accounting remain immutable.
