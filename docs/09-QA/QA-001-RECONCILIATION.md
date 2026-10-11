---
title: QA-001 — B01 manifest integrity reconciliation
status: fixed-locally-awaiting-review
owner: project
created: 2026-10-12
updated: 2026-10-12
tags: [qa, design, integrity, provenance]
---

# QA-001 current defect status — 2026-10-12

**FIXED / local validation PASS; independent PR review pending.**
Base stage: `5cfe18d401c056eefb179f722d875c3b1acf7522`.
Branch: `fix/qa-001-b01-manifest-integrity`.
[Task lifecycle](../../.agents/tasks/b01-current-manifest-integrity-reconciliation.md):
`awaiting-review`, not independent review PASS. No spec existed at launch;
the owner's explicit QA-001 prompt authorized this bounded task and Git delivery.

This is the current QA-001 status record. The
[historical defect register](FUNCTIONAL-DEFECT-REGISTER.md#qa-001--ofl-provenance-byte-mismatch)
is covered by the immutable 2026-10-11 evidence manifest. It retains its original
OPEN/FAIL and diagnosis; neither that register nor its manifest/raw logs is rewritten.
QA-002–QA-004 remain open; historical audit totals are unchanged.

## Verified cause

The fresh stage checkout reproduces `manifest source/hash drift`. Independent
hashing of Git blob, worktree and a newly downloaded pinned upstream confirms:

| Representation | Bytes | CRLF | SHA-256 |
| --- | ---: | ---: | --- |
| Pinned upstream | 4495 | 93 | `564ce565c371c5e5bbf286006565a7c9aa55a9f56e7ca58d56e05d649dd61a72` |
| Upstream after Git LF conversion only | 4402 | 0 | `c6dec685825f73b18c20926fddc65e8315642e12986f15db0699170940a09efc` |
| Tracked OFL / worktree / current manifest asset hash | 4401 | 0 | `18a4de52385f6b988782639d5d0cc1326e5a8c2de9a7f01d7b20d9aedcc60943` |

Root `.gitattributes` applies `text=auto eol=lf`; `git ls-files --eol` reports
`i/lf w/lf`. No clean/smudge filter or core newline override is configured in
this checkout. Git clean replaces CRLF but **does not** remove trailing whitespace.
One trailing space after `bundled, embedded,` was removed separately; the actor
of that historical edit is not established. All other license text matches exactly
under the documented whitespace normalization. The old manifest recorded the
upstream digest in a field intended to verify the repository asset bytes.

[Fresh provenance verification](evidence/qa-001-2026-10-12/provenance-verification.json).
[Pinned upstream source](https://raw.githubusercontent.com/floriankarsten/space-grotesk/03507d024a01282884232081fc6011c09ff4e849/OFL.txt)
was fetched again on 2026-10-12; offline checks use its
[base64 fixture](evidence/qa-001-2026-10-12/upstream-ofl.json).

## Byte policy decision

| Option | Effect | Decision |
| --- | --- | --- |
| A: normalized LF storage + separate upstream provenance | Preserves current OFL and root Git policy; upstream and stored digests have explicit separate meanings | Selected |
| B: exact upstream bytes + narrow Git attribute exception | Would restore CRLF and one trailing space, changing the tracked asset and adding an exception | No accepted contract requires it |

Policy A is `lf-trim-trailing-horizontal-whitespace`: CRLF → LF, strip only
end-of-line spaces/tabs. The license file is unchanged. No terms, permissions,
font binary, tokens, rendering or visual baseline change.

Existing `font-source.json` `sha256`/`bytes` retain their **upstream** meanings;
new `stored` metadata identifies the repository digest/length/policy and fixture.
The checker validates raw decoded fixture hash/length/URL, exact normalization,
and stored bytes **before both check and write**. Tests pin upstream revision/hash
independently. Base64 preserves the original CRLF/space payload through Git without
an attribute exception or a network dependency at validation time.

Current `manifest.generated.referenceAssetSha256` still hashes actual assets.
The current manifest changes only four hashes: `assets/OFL.txt`,
`assets/font-source.json`, `build-reference.cjs`, `build-reference.test.cjs`.
The latter three follow justified metadata/checker/test edits. All other manifest
fields and asset hashes, source/token/reference hashes and tokens.css are unchanged.
[Pre-fix hash inventory](evidence/qa-001-2026-10-12/baseline.json) anchors regressions;
it is a hash-only snapshot, not a regenerated visual baseline.

## Executed validation

- `node docs/07-Design/reference/build-reference.cjs --write` — PASS;
  followed by the real canonical `--check` — PASS.
- `node --test docs/07-Design/reference/build-reference.test.cjs` — **10/10 PASS**:
  existing two resolver/unit tests and all eight required regressions below.
- `python3 scripts/qa_evidence.py` — PASS: historical 88-file manifest, JSON/XML,
  accounting and QA Markdown links intact; historical FAIL preserved.
- `python3 -m unittest discover -s scripts -p 'test_qa_evidence.py'` — **8/8 PASS**.
- Documentation local-link/frontmatter checks, Node syntax, manifest diff,
  `git diff --check`, tracked-byte checks and scoped secret scan — PASS.

[Validation record](evidence/qa-001-2026-10-12/validation-results.json).
CI adds real canonical/Node checks in a fresh checkout before existing Quality steps.
Remote CI and PR contract are recorded in the PR checkpoint with the actual head;
local tests do not imply remote CI success or independent review.

| Regression | Result |
| --- | --- |
| QA001-01 actual current canonical manifest | PASS |
| QA001-02 tracked/worktree OFL and explicit stored policy | PASS |
| QA001-03 pinned upstream digest/revision, corrupted fixture rejected by check/write | PASS |
| QA001-04 exact text equality except documented 93 CRLF and one trailing space | PASS |
| QA001-05 one-byte OFL mutation rejected by check/write in temporary fixture | PASS |
| QA001-06 missing OFL and unrelated font fail in temporary fixtures | PASS |
| QA001-07 unrelated manifest/asset/source/token/reference hashes unchanged | PASS |
| QA001-08 temporary Git index fresh export with autocrlf=true passes same checker | PASS |

Negative tests never modify real source assets. A post-commit fresh detached checkout
is also checked before push; its result belongs to the Git delivery checkpoint.

## Changed files

- `.github/workflows/quality.yml` — canonical/Node regression CI step.
- `docs/07-Design/reference/build-reference.cjs` — offline license/provenance guard.
- `docs/07-Design/reference/build-reference.test.cjs` — eight deterministic regressions.
- `docs/07-Design/reference/assets/font-source.json` — separate stored-byte metadata.
- `docs/07-Design/reference/manifest.json` — four justified current hashes.
- `docs/07-Design/reference/README.md` — integrity/provenance contract.
- `docs/09-QA/QA-001-RECONCILIATION.md` — this current status/report.
- `docs/09-QA/evidence/qa-001-2026-10-12/{baseline,upstream-ofl,provenance-verification,validation-results}.json` — new dated evidence.
- `.agents/tasks/b01-current-manifest-integrity-reconciliation.md` — bounded task/lifecycle.
- `.agents/tasks/README.md`, `.agents/state/{STATUS,NEXT,BLOCKERS}.md` — task index/current outcome/next gate.
- `docs/00-Home/{Documentation-Map,CURRENT-PROJECT-STATE}.md` — current-status navigation.

## Review, remaining gates and rollback

Independent review is pending; no self-review substitutes for it. No merge,
cloud review request, provider/user content transmission, product/runtime or DB
change. B01/B02 owner design decisions remain intact. Manual full matrix/K1/Z1/A1
remain **NOT TESTED / OPEN**, production release blocked under ADR-0025.
Preview/provider/M2 and QA-002–QA-004 remain separate unresolved work.

NEXT is `b01-manual-accessibility-release-gate` after QA-001 review/integration;
it is not executed here. Rollback is a scoped revert through normal PR governance;
it restores the prior integrity FAIL, not a different license or visual baseline.
STOP at QA-001 delivery.
