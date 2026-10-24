---
title: QA-003 — PostgreSQL timestamp-tie fixture repair evidence
status: local-verified-pr-review-pending
created: 2026-10-12
updated: 2026-10-12
tags: [qa, diagnostics, postgresql, evidence]
---

# QA-003

Explicit bounded owner IMPLEMENT override of
`NEXT=b01-current-manifest-integrity-reconciliation`; one agent, sequential.
Base/target `stage`; branch `fix/qa-003-diagnostics-fixture`. NEXT/BLOCKERS preserved.
No production behavior, schema, dependencies or other QA fixes changed; no merge.

Baseline SHA: `5cfe18d401c056eefb179f722d875c3b1acf7522`.
Validated test/code SHA: `10d9b575b63cd44ad183af8287c322d4ff804191`.
The subsequent documentation/evidence commit does not change tested PHP sources.
[Manifest](manifest.json) pins source/evidence SHA-256;
[runs](runs.json) stores exact argv, timestamps, SHA, environment, role and exit code.

## Contract and root cause

An incident has a ULID primary key and a unique SHA-256 fingerprint of
`code | component | safe exception class | operation`. Occurrences have distinct
ULID primary keys and an incident foreign key. Provider, request ID, attempt,
retry delay and stack location do not separate incident fingerprints.
WeakMap tracks Throwable **object identity** and returns early when that object
was already recorded. Different equivalent exceptions represent different events.
List latest-provider projection/filter and incident detail order occurrences by
`created_at DESC, id DESC`; reference search may match any retained occurrence.
Only admins may read/update incident records. Status writes and audit writes share
one transaction. Redaction precedes both log and database persistence.

The baseline fixture reused one exception twice; PostgreSQL reproduced the original
`expected 2, actual 1` at line 107, exit 1, before reaching the tie assertions.
[Fresh baseline failure](baseline-exact-ready.log).
The repair constructs two RATE_LIMITED exceptions with identical fingerprint inputs,
asserts one incident/count 2, equal timestamps, and both occurrence IDs in detail.
A separate PostgreSQL test checks same-object recording remains count 1.
The USER regression now also denies detail and status update.

## Environment and isolation

- Worktree: `/Users/filatelist/.codex/worktrees/qa003-diagnostics-fixture/CVortex`.
- PostgreSQL image `postgres:18.6-bookworm`; PHP 8.4.25; Laravel 13.31.0;
  PHPUnit 12.5.35. [Pinned image IDs](images.log).
- New owned container/network `cvortex-qa003-20261012`, database `qa003_isolated`;
  storage is tmpfs at `/var/lib/postgresql`, no published ports, network internal.
  [Container](container-isolation.log), [network](network-isolation.log).
- Generated ephemeral credentials in private mode-0600 env files outside Git;
  credentials are omitted from evidence and removed after cleanup.
- Runtime-role suite uses `cvortex_app`, NOSUPERUSER/NOBYPASSRLS;
  [runtime identity](runtime-identity.log). DDL migrations use `qa003_admin` only
  in this owned instance. All 28 migrations ran. [Migration](migration-final.log).
- `DiagnosticsTest` uses RefreshDatabase and therefore runs as the disposable
  instance admin, on PostgreSQL. It is not additional runtime-role/RLS proof.
- `APP_ENV=testing`, `DB_CONNECTION=pgsql`, `DB_URL=`; array cache/session,
  sync queue, AI_PROVIDER=none, blank OPENAI_API_KEY, CHATGPT_PLAN_ENABLED=false.
  Internal-network containers cannot reach external provider endpoints.
- Existing `cvortex_backend_vendor` is mounted read-only; application/runtime-ai
  sources come from the isolated worktree. No development DB/service is a target.
- Zero users/incidents/occurrences/audit before tests and after each suite;
  [before](isolation-ready.log), [runtime rollback](rollback-after-postgres.log),
  [targeted rollback](rollback-after-targeted.log). 28 migrations remain recorded.
- [Owned container cleanup](postgres-cleanup.log) and
  [owned network cleanup](network-cleanup.log) exit 0. No persistent DB volume used.

## Executed validation

Commands below execute inside `cvortex-backend` with the mounts/env/network in
[runs.json](runs.json). Each PASS below is at the validated code SHA and exit 0.
No PostgreSQL checks were skipped. The exact test is included again in the five-case
suite; totals count unique suite cases, not the repeated exact invocation.

| Run | Exact PHPUnit/static command | Role | Result |
|---|---|---|---|
| Exact failing case | `php vendor/bin/phpunit tests/Feature/DiagnosticsPostgresTest.php --filter=test_runtime_role_uses_id_to_break_latest_occurrence_timestamp_ties --display-warnings --colors=never --log-junit storage/logs/qa003-exact.xml` | runtime | PASS, 1 test / 17 assertions |
| PostgreSQL suite | `php vendor/bin/phpunit tests/Feature/DiagnosticsPostgresTest.php --display-warnings --colors=never --log-junit storage/logs/qa003-postgres.xml` | runtime | PASS, 5 tests / 50 assertions |
| Targeted Diagnostics | `php vendor/bin/phpunit tests/Feature/DiagnosticsTest.php --display-warnings --colors=never --log-junit storage/logs/qa003-targeted-debug.xml` | admin | PASS, 57 tests / 575 assertions |
| Pint | `php vendor/bin/pint --test tests/Feature/DiagnosticsPostgresTest.php` | runtime | PASS, 1 file |
| PHPStan | `php vendor/bin/phpstan analyse --memory-limit=1G --no-progress --no-ansi` | runtime | PASS, no errors |
| Diff | `git diff --check origin/stage...HEAD` | host | PASS |

Exported command output: [exact](exact-pass.log), [PostgreSQL](postgres-suite.log),
[targeted](diagnostics-targeted-debug.log), [Pint](pint.log), [PHPStan](phpstan.log).
Machine-readable results: [exact XML](qa003-exact.xml),
[PostgreSQL XML](qa003-postgres.xml), [targeted XML](qa003-targeted-debug.xml).
Combined unique targeted result: **62 tests / 625 assertions, zero skips**.
PHPStan's configured scope is `app`/`routes`, not tests; it verifies existing
production analysis remains clean. PHPUnit and Pint cover the changed test file.

## Regression mapping

| Criterion | Evidence |
|---|---|
| QA003-01 distinct objects → two occurrences | Runtime timestamp-tie test: count 2 |
| QA003-02 one incident fingerprint | Same test: one incident, incident counter 2, both occurrences under its ID |
| QA003-03 equal created_at → greater ID | Same test: equal timestamp count 2, detail orders both IDs descending |
| QA003-04 latest provider filtering | Same test: newer total 1/latest_provider newer; older total 0 |
| QA003-05 latest request ID | Same test: list search and latest detail request_id |
| QA003-06 retry metadata | Same test: latest attempt 2/retry_after_seconds 42 |
| QA003-07 same object dedup | `test_runtime_role_deduplicates_the_same_throwable_object`: count/counter 1, recorded flag true |
| QA003-08 USER denied | Runtime grouping test: list/detail/PATCH 403; targeted admin/auth tests |
| QA003-09 audit rollback | Runtime audit-failure test: OPEN preserved, audit event absent; targeted rollback tests |
| QA003-10 redaction | Targeted nested-secret/log-channel/stack/response/incident sanitization tests |

## Preserved setup and invocation failures

These are separate from the confirmed baseline fixture failure and remain visible
in runs/logs. No source change was needed to correct these environment issues.

- `migration.log`: exit 125, incorrect backend `.env.example` mount path.
- `migration-ready.log`: exit 125, nested bind-mount creation rejected by Docker.
  The redundant mount was removed; Docker-created empty ignored backend `.env`
  remained local to the new worktree; all effective config came from env files.
- `isolation-before.log` and `baseline-exact.log`: exits 1/2 before migrations
  succeeded; missing tables, not product evidence. SQL uses synthetic test users.
- `diagnostics-targeted.log` / [initial XML](qa003-targeted.xml): exit 2,
  57 tests / 516 assertions, 2 errors/1 failure. LOG_LEVEL=error suppressed INFO
  logs required by redaction/context tests. Rerun with LOG_LEVEL=debug passed on
  the same code SHA. Exact and runtime suite used LOG_LEVEL=error successfully.
- A host inspection helper initially imported its CLI module without argv and
  raised IndexError; the subsequent recorded inspection commands succeeded.

New exported logs strip line-ending spaces and trailing blank lines only; one
final LF is retained. Original logs outside Git are untouched; original and
exported SHA-256 digests are recorded in manifest.json. This avoids treating
CLI padding as a source whitespace defect.

All logs contain synthetic test data/canaries only. Historical audit logs and
failure ledger are preserved, including the original
[QA-003 reproduction](../full-2026-10-11/diagnostics-isolated-repro.log).
This bounded repair does not establish full QA, human acceptance or release readiness.

The historical package manifest also covers the mutable current defect register.
Only that register digest is refreshed; the other historical manifest entries,
logs, results and accounting are unchanged.
[Digest update provenance](register-manifest-update.json).
