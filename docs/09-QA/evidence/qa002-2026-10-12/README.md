---
title: QA-002 isolated PostgreSQL evidence — 2026-10-12
status: local-verified
created: 2026-10-12
updated: 2026-10-12
tags: [qa, evidence, migration, postgresql]
---

# QA-002 repair evidence

The owner authorized this bounded IMPLEMENT override of NEXT. Base: stage
`5cfe18d401c056eefb179f722d875c3b1acf7522`; one agent, sequential.
NEXT/BLOCKERS, product migrations and other QA fixes are unchanged.
Native Laravel 13.31.0 path/batch semantics were inspected from installed framework
source; PostgreSQL image is `postgres:18.6-bookworm`.

The original audit FAIL is preserved at
[historical log](../full-2026-10-11/vacancy-postgres-duplicate-migration.log).
All 28 migrations ran first, then `--step=4` removed four October ChatGPT migrations.
`000009` and `vacancies_owner_source_url_unique` remained; duplicate seed failed.
This is a harness boundary defect; no production migration defect was established.

New boundary: sorted explicit paths through
`2026_09_19_000008_create_vacancy_core_tables`, with actual schema assertions before
seed. Named `000009`–`000012` repairs run separately, then reverse named batch/path
rollback, same-path reapply, then all later migrations. No fixed migration counts.
Implementation/security details: [testing contract](../../MIGRATION-TESTING.md).

## Executed candidate validation

- [Current migration run](current.log): exit 0, 28/28 Ran, all reconciliation and
  cleanup checks PASS. This is the first successful implementation iteration.
- [Final source control run](controls-final.log): exit 0; all current migrations
  plus unrelated `2099` control Ran. Incompatible schema fails with psql exit 3;
  negative transaction rolls back, positive legacy checks then PASS.
- [Harness unit tests](unit.log): 7 tests PASS, including unsafe connection target
  blocking before any Docker call, foreign mounts and cleanup failure propagation.
- Bash syntax, Python compilation and scoped whitespace checks PASS.
- QA historical manifest/helper regressions are validated separately. Intentional
  current defect-register update requires one manifest digest change;
  [digest record](register-manifest-update.json). Historical logs/results untouched.

Counts: users/vacancies/snapshots/analyses/runs
`1/8/10/5/3 → 1/5/10/5/3`. Payload/history fingerprints prove retained snapshots,
analysis links and LLM metadata; chronology, latest states, failure codes and owner
relationships are asserted. Named rollback/reapply retains corrected data by design.

| Regression | Evidence / result |
|---|---|
| QA002-01 | Exact ledger through 000008 + real legacy catalog assertions PASS |
| QA002-02 | No migration at/after 000009 and no owner/source unique index before seed PASS |
| QA002-03 | Original duplicate fixture inserted, counts 1/8/10/5/3 PASS |
| QA002-04 | Vacancies converge 8 → 5, canonical aggregates retained PASS |
| QA002-05 | 10 snapshots / 5 analyses and history fingerprints retained PASS |
| QA002-06 | 3 LLM runs, IDs/payload/status/error history retained PASS |
| QA002-07 | Interleaved snapshots become versions 1,2,3,4; latest snapshot/status PASS |
| QA002-08 | Actual partial owner/source unique index, RLS/policies and trigger PASS |
| QA002-09 | Only named 000012→000009 rollback; exact ledger delta/fingerprints/reapply PASS |
| QA002-10 | Unrelated later migration copied before setup, absent in legacy, later Ran PASS |
| QA002-11 | Wrong-schema negative control exit 3; cannot produce false PASS |
| QA002-12 | cvortex/cvortex_test/cvortex_dev2 and connection overrides blocked before Docker PASS |
| QA002-13 | Owned resource guards; cleanup absent; existing DB preservation PASS |

## Isolation and preservation

Every log records unique project/database/admin/runtime names, immutable backend
image ID and PostgreSQL container ID. Each run owns its own PostgreSQL/vendor
volumes and internal network; no shared database/volume or host port. Ephemeral
passwords, `.env`, cached config, stored files and signing keys are excluded from
the evidence. Cleanup runs before PASS and confirms owned resources are absent.

[Development preservation](development-preservation.json) compares read-only
schema hashes, counts and row digests for `cvortex`, `cvortex_test`, `cvortex_dev2`
on the existing `cvortex` Compose PostgreSQL container. Before/after identity,
schema and data are identical. These databases were inspected only; fixture writes
ran in different containers, volumes and networks.

## Failures and limitations

The first implementation trial failed before seed because a new PL/pgSQL variable
`table_name` collided with a catalog column. Qualifying that column fixed it;
cleanup passed. Its failure log is retained separately. Documentation validation
initially rejected the changed register digest and its not-yet-created README link;
both were corrected, without altering historical audit outcomes.

New raw logs are preserved as gzip copies; readable exports remove only trailing
line whitespace for Git checks. Export hashes/transformation records accompany the
run metadata. Historical audit logs are byte-for-byte unchanged.

Security review is local/self-review of changed harness code: target selection,
resource ownership, failure propagation, argv/SQL handling and credential exclusion.
Independent PR review remains open. Other migration scripts were not rerun or
repaired. SIGKILL/host crash needs guarded manual cleanup; no production migration,
provider call, full audit, merge or release was performed. STOP.
