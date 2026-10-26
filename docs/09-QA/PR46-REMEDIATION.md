---
title: PR 46 evidence remediation
status: validated-candidate
owner: project
created: 2026-10-12
updated: 2026-10-12
tags: [qa, evidence, remediation]
---

# PR #46 remediation

The explicit owner task authorizes IMPLEMENT on the existing PR branch, verification-helper changes, independent read-only review, commit, normal push and PR metadata correction. This overrides the NEXT pointer for this bounded block. No product code, migrations, ADRs, existing data or QA-001–QA-004 are changed. NEXT remains `b01-current-manifest-integrity-reconciliation`; release/Preview/M2 gates stay open.

| Finding | Root cause and correction | Reproduction / protection |
|---|---|---|
| REVIEW-001 MAJOR | Manifest hashed mixed-newline working bytes before Git's LF clean filter. Original build log: 1262 bytes; published blob: 1259. Normalize exactly three CRLF sequences; hash published bytes after generation. | Canonical `qa_evidence.py` refuses pre-export CRLF; byte verifier rejects missing, corrupted and unlisted payload; `--index` checks actual staged Git blobs; original/export hashes in export-provenance.json. |
| REVIEW-002 MAJOR | Confirm/reject race permits either winner; original verifier wrongly required CONFIRMED. Uncaught exception in standalone Laravel helper did not fail wrapper. | Winner-bound invariant handles confirm+one valid owned Claim or reject+zero Claims; explicit catch/exit 1; wrapper requires zero worker exits and exact final marker. Historical control is FAIL even though checks.json records exit 0. |
| REVIEW-003 MAJOR | Feature list omitted local ChatGPT/chat/analysis approval/Diagnostics/health workflows. | Eight new explicit rows; every API v1 endpoint and frontend page mapped. Existing local fake test execution supports bounded coverage; external/human gaps remain. |
| REVIEW-004 MINOR | PR body/checkpoints predate #45 merge and #46 ready state. | Corrected metadata prepared for independent review; apply only after reviewed commit is pushed. Prior comments remain dated history. |
| REVIEW-005 MINOR | Export removed a mock error-detail log without recording original/export hashes; some historical command envelopes missing. | Original 2102-byte provider and sanitized 2047-byte export compared; 55-byte console.log removal recorded. Original embedded hash retained. Unknown exact command/time/exit fields remain UNAVAILABLE. Safe reproduction templates are not passed off as historical commands. |
| REVIEW-006 MINOR | Documentation Map still treated QA as reserved. | Map now links audit, matrix and defect register. |

## Accounting

Canonical machine records: [ledger](evidence/full-2026-10-11/scenario-ledger.json), [inventory](evidence/full-2026-10-11/function-inventory.json), [accounting](evidence/full-2026-10-11/accounting.json). Run `python3 scripts/qa_evidence.py --write` to regenerate derived summaries/matrix and manifest after intentional updates, then `python3 scripts/qa_evidence.py` to verify without writing and `python3 scripts/qa_evidence.py --index` after staging. Quality CI runs the verifier and both Python regression suites. The generator never rewrites historical checks/log outcomes.

Historical correction: 432 records / 539 weighted units; 525 PASS / 5 FAIL / 7 BLOCKED / 2 NOT_RUN, replacing 526/4/7/2 solely because Career's final verifier failed. The new run is separate and never replaces that failed historical observation. 370 unique named backend/QA identities, 369 executed; frontend reports 108 cases but individual identities were not exported. Controls/DB/a11y observations are not independent user E2E tests. Eight feature mappings change inventory to 67 rows, 59 checked; 54 PASS / 4 FAIL / 7 BLOCKED / 2 NOT_RUN. Counts do not imply a product readiness percentage.

Raw historical whitespace is preserved except the explicitly documented CRLF export normalization. The full PR whitespace check still reports historical raw log/diff/captured-text formatting; changed source/Markdown whitespace must pass. The manifest verifies actual exported bytes, not historical invocation authenticity.

## Reproduction and safety

Regression commands: `python3 -m unittest discover -s scripts -p 'test_qa_evidence.py'`, `python3 -m unittest discover -s scripts -p 'test_career_review_harness.py'`, backend `vendor/bin/phpunit tests/Unit/CareerReviewInvariantTest.php`.

Actual PostgreSQL verification uses a new isolated Compose project with new generated credentials, a new PostgreSQL volume, internal-only backend network and read-only existing dependency volume. `AI_PROVIDER=none`, empty provider credential, no Horizon/browser/provider invocation. The harness creates only randomly named disposable race databases in that isolated PostgreSQL instance, then drops only those owned databases. Primary application services and data-bearing databases are not targeted. Command templates require an operator-provided private env file and isolated Compose override; never reuse production env/volumes.

```bash
COMPOSE_PROJECT_NAME=<owned-isolated-project> \
COMPOSE_FILE="$PWD/compose.yaml:<private-isolated-override>" \
COMPOSE_ENV_FILES=<private-generated-env> \
bash scripts/test-career-review-postgres-concurrency.sh
```

New command times, exact exit codes, source hashes, log hashes and results are stored in [remediation-runs.json](evidence/full-2026-10-11/remediation-runs.json). Readable historical envelopes and known gaps: [export-provenance.json](evidence/full-2026-10-11/export-provenance.json). Original reviewed objects remain recoverable at `d53c545455930e282c212d111a5a3f514f144846`.

## Security and limitations

VERIFIED in the changed-helper scope: subprocess argv without shell interpolation in Python regressions; no credential output; owner/winner/Claim invariants; malformed/duplicate JSON and unsafe manifest paths rejected; no new product/network/provider code. Existing historical Truth Guard, approval, isolation, RCV-01 and pinned OFL evidence is preserved.

PARTIALLY VERIFIED: existing artifact security and local fake contracts support bounded conclusions only. NOT VERIFIED: exhaustive IDOR/XSS/SSRF/prompt-injection resistance, historical packet egress, live OpenAI/ChatGPT, human zoom/screen reader and Preview acceptance. NOT APPLICABLE to this change: document upload/rendering and new production interfaces. No new architectural decision is introduced. Independent review and Git delivery records are kept outside the approved candidate tree to avoid changing it after approval.
