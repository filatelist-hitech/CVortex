---
title: CVortex Functional Defect Register
status: open-findings
created: 2026-10-11
updated: 2026-10-12
tags: [qa, defects, reproduction]
---

# Functional defect register

Tested baseline `f06a6dba058589823bf3d3c8b693cb625170bc4b`. Исторический аудит зафиксировал четыре открытых находки; исправление QA-004 от 2026-10-12 записано отдельно ниже. Severity описывает влияние и уверенность, а не наличие security exploit. Документы/evidence относятся к QA worktree; primary checkout не изменён.

| ID | Категория | Severity | Status | Проблема |
|---|---|---|---|---|
| QA-001 | Reference integrity | MEDIUM / P2 | OPEN / reproduced | B01 OFL.txt SHA-256 mismatch |
| QA-002 | Test infrastructure | MEDIUM / P2 | OPEN / reproduced | Legacy migration harness rolls back wrong migrations |
| QA-003 | Test infrastructure | MEDIUM / P2 | OPEN / reproduced | Timestamp-tie fixture conflicts with exception deduplication |
| QA-004 | Product functional UI | HIGH / P1 | FIXED / locally verified, awaiting review | Explicit Career evidence selection implemented |

## QA-001 — OFL provenance byte mismatch

**Reproduction:** from the tested QA checkout, `node docs/07-Design/reference/build-reference.cjs --check` exits 1, `manifest source/hash drift`. Original and recovery runs agree. The only saved asset mismatch is `assets/OFL.txt`.

Expected SHA-256: `564ce565c371c5e5bbf286006565a7c9aa55a9f56e7ca58d56e05d649dd61a72`.
Actual: `18a4de52385f6b988782639d5d0cc1326e5a8c2de9a7f01d7b20d9aedcc60943`.
Pinned upstream fixture: 4495 bytes, 93 CRLF sequences; tracked asset: 4401 bytes, no CRLF. Text matches after newline/trailing-space normalization. [Evidence](evidence/full-2026-10-11/b01-provenance.json), [diff](evidence/full-2026-10-11/b01-license.diff), [reproduction](evidence/full-2026-10-11/b01-recovery-repro.log).

**Root cause:** byte-integrity metadata retains upstream-byte hash, while repository `.gitattributes` sets `text=auto eol=lf`; tracked copy has LF and one removed trailing space. [Attributes](evidence/full-2026-10-11/asset-attributes.txt). This is byte/provenance drift, not evidence of changed license terms. Both stored fixture hashes independently recomputed during recovery; no new upstream fetch performed.

**Impact / affected checks:** `build-reference.cjs --check` fails; canonical source manifest cannot be declared intact. Build-reference node tests PASS do not override the real asset mismatch. No demonstrated application runtime failure.

**Recommended fix:** explicitly choose and document asset-byte policy. With LF-normalized repository bytes, retain separate pinned upstream hash and normalized stored-asset hash/provenance, then update the current manifest with reviewable evidence. If exact upstream bytes are required, add a narrowly scoped Git attribute exception and restore exact bytes. Do not rewrite historical evidence or merely disable hash verification.

**Regression criteria:** checker PASS in a fresh checkout; independently verifiable upstream/stored hashes; byte-policy documented; license text unchanged; no other asset/source drift; node reference tests remain PASS.

## QA-002 — fixed-count rollback no longer reaches legacy schema

**Reproduction:** historical isolated run of `bash scripts/test-vacancy-postgres-duplicate-migration.sh` exits 3. At lines 25–27 it migrates all current migrations and rolls back `--step=4`. The four reverted migrations are the October ChatGPT migrations, so September vacancy remediation `000009` remains active. Legacy duplicate seed then fails on `vacancies_owner_source_url_unique`.

[Full log](evidence/full-2026-10-11/vacancy-postgres-duplicate-migration.log) records migration order, rolled-back names and unique-constraint failure. Script also uses fixed rollback count near line 103 for re-up. Its cleanup drops its own disposable DB; **the script was not rerun during this continuation**, because destructive setup/cleanup is outside the recovery restriction. No product migration defect is inferred.

**Root cause:** pre-remediation state is selected by number of newest migrations rather than semantic migration boundary. Adding unrelated migrations silently changes the starting schema.

**Impact / affected tests:** `test-vacancy-postgres-duplicate-migration.sh` cannot exercise reconciliation of legacy duplicate aggregates or preservation through rollback/re-up. Independent current-schema vacancy concurrency/revalidation scripts PASS; they do not close this exact legacy-upgrade gap.

**Recommended fix:** build a disposable pre-`2026_09_19_000009` schema from an explicit migration allowlist/boundary; assert relevant schema and absence of the uniqueness constraint before seeding legacy duplicates. Upgrade through the intended migrations, verify reconciliation/counts, then test only named rollback/reapply migrations where valid. Keep owned-DB cleanup guard, never point the harness at an existing data-bearing DB.

**Regression criteria:** pass with today's complete migration set and an additional unrelated later migration; seeded duplicate fixture exists before remediation; duplicate aggregates reconcile correctly; counts/history/owner links preserved; named rollback/re-up succeeds; unexpected schema fails early with a clear diagnostic.

## QA-003 — same exception object is deduplicated before tie fixture exists

**Reproduction:** `DiagnosticsPostgresTest::test_runtime_role_uses_id_to_break_latest_occurrence_timestamp_ties` fails at line 107, expected 2 occurrences, actual 1. Reproduced in the 10-case PostgreSQL suite and earlier isolated run at unchanged SHA. [Suite](evidence/full-2026-10-11/backend-postgres.log), [isolated reproduction](evidence/full-2026-10-11/diagnostics-isolated-repro.log). No successful test repeated during recovery.

**Root cause:** lines 101–103 construct one `LlmProviderException` and call `IncidentRecorder::record` twice with the same object. `IncidentRecorder` uses a static `WeakMap<Throwable, bool>` and returns early for a previously recorded object (lines 13–26). The intended two-occurrence timestamp tie is never constructed. The actual controller sorts by `created_at DESC, id DESC`; this failing fixture does not demonstrate incorrect sort/filter behavior.

**Impact / affected tests:** one failed case in `DiagnosticsPostgresTest`; latest-occurrence tie filtering is not validated by this run. Three other PostgreSQL Diagnostics cases and 57 SQLite Diagnostics cases PASS. This is a fixture defect, not established product dedup or sorting defect.

**Recommended fix:** construct two distinct exception objects with the same fingerprint/component to create two occurrences, then set equal timestamps and verify the greater ID wins. Retain separate same-object dedup coverage; do not remove dedup to satisfy this test.

**Regression criteria:** two distinct exceptions create two occurrences in one incident; identical timestamps choose greater ID for list provider/search and detail; repeated recording of the same object creates only one occurrence; admin-only/rollback/redaction tests remain PASS.

## QA-004 — ambiguous Claim cannot be resolved in Career UI

**Reproduction:** create manual `skill` fact `Built Laravel APIs.`; mock extraction returns `experience` with the same assertion; explicitly confirm it. Claim becomes `USER_RESOLUTION_REQUIRED`. Career view renders the Claim status badge and statement but provides no choice of evidence/resolution action. Main browser generation only proceeds after owner-authenticated `PATCH /api/v1/career/claims/{id}/resolve` with a same-owner confirmed `career_fact_id`, followed by reanalysis. This fixture is synthetic.

**Evidence:** original generation checkpoint [browser-generation-original.json](evidence/full-2026-10-11/browser-generation-original.json); saved `CAREER-AMBIGUITY-resolution-API` PASS in [browser.json](evidence/full-2026-10-11/browser.json); [action inventory](evidence/full-2026-10-11/career-actions.json). Source: `apps/frontend/src/app/career-workspace.tsx`, final Claim list maps badge/statement only; controller resolution route exists. The current DB is already resolved, so the resumed inspection observes action absence/source, not an unresolved badge screenshot. Earlier positive API outcome is retained rather than recreating the fixture.

**Root cause:** backend resolution operation and frontend Claim status projection are implemented, but frontend has no resolution control or request path.

**Impact / affected coverage:** a legitimate manual+extracted confirmation flow can block downstream generation without a self-service remedy. Truth Guard correctly fails closed; no unsafe approval observed. Affects Career-to-Application first-value browser path when evidence is ambiguous; happy path currently uses an API workaround. Requirement context: M1.4 requires explicit user resolution before revalidation.

**Recommended fix:** separately authorize bounded Career UI resolution: expose supporting same-owner confirmed evidence choices, submit explicit selection, show validation errors and refresh/revalidate dependent context. Preserve human choice and owner/Truth Guard guards; no automatic selection.

**Regression criteria:** reproduce ambiguity in browser, choose an eligible supporting fact, observe PASS then complete generation/approval without console/API helper; reject foreign, pending, rejected and deprecated evidence; ambiguous/unresolved content stays unapprovable; keyboard/accessibility coverage for the new control.

### QA-004 bounded correction — 2026-10-12

**Current result:** FIXED / locally verified, awaiting PR review/integration.
Career now exposes Russian explanation, linked confirmed supporting facts with
source/type/excerpt, explicit radio selection and confirmation, busy/result/error
states, preserved selection and safe manual retry. It uses the existing PATCH
contract and displays only the backend's actual truth_status; HTTP 200/BLOCK is
not success. No new facts, automatic confirmation, resolution or application approval.

**Validation:** frontend 131 tests PASS, ESLint/TypeScript/build PASS;
Career API 52 tests / 417 assertions and Application 19 tests / 253 assertions PASS
(SQLite in-memory); targeted Pint/PHPStan PASS. Real browser against isolated
PostgreSQL/runtime role + local mocked provider: 15 targeted scenarios PASS,
including keyboard resolution, persistence, vacancy revalidation, generation,
explicit acceptance/approval, CSRF419 and foreign Claim/approval404.
Responsive smoke at 320/390/768/1440 PASS. [Evidence and reproducible runner](evidence/qa004-2026-10-12/README.md).

Historical audit records above and their API-workaround evidence remain unchanged.
No full QA audit or live provider verification. Preview 0.1 and manual accessibility
release gates remain open; QA-001–003 are outside this correction. NEXT is unchanged
under explicit owner override. Integration/independent review remain separate.

## Coverage gaps and non-defects

- External OpenAI/ChatGPT delivery: BLOCKED by explicit no-live-AI boundary; mock adapter metadata is not real-provider proof.
- Real-user Preview acceptance, A1 audible screen reader, Z1 native 200% zoom and full manual state matrix: BLOCKED; unchanged repository release gates.
- One exact PostgreSQL `VacancyCoreTest` case: BLOCKED because its RefreshDatabase setup is destructive; equivalent FK and runtime RLS probe PASS with rollback.
- QA backend belongs to non-internal `edge`: network-wide no-egress is not enforced. URL/key/mock controls are verified; historical packets unavailable. Recommended environment hardening in a separate QA setup task; not an observed exfiltration defect.
- M2 package/export and full employer consistency: NOT_RUN outside implemented/authorized scope. No fake MVP PASS.
- Early injection-vacancy timeout: expected semantic rejection misused as positive fixture; safe synthetic vacancy subsequently PASS. Early environment-sensitive SQLite failures are retained, superseded by canonical same-SHA PASS; their precise initial effective config is unavailable.
- Full WCAG/contrast, every state keyboard traversal and production-scale/SLO testing are not covered by semantic/responsive/performance smoke.

Recommended bounded next task: repair QA-001–QA-003 proof gates only under a separate authorization. Product fix QA-004 and human gates remain subsequent work; no remediation is part of this audit.

## PR #46 evidence correction — 2026-10-12

QA-001–QA-004 above remain unchanged. Historical Career review concurrency has an additional verifier proof failure: the original log throws while its wrapper exits 0 and prints PASS. This is REVIEW-002, not an established product race defect. The historical ledger/F48 now record FAIL; helper repair and fresh isolated evidence are documented in [PR46 remediation](PR46-REMEDIATION.md). No historical failed verification is replaced by a later successful run.
