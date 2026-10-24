---
title: QA-001 — B01 current manifest integrity reconciliation
status: awaiting-review
owner: project
created: 2026-10-12
updated: 2026-10-12
tags: [task, design, integrity, qa]
execution:
  workflow: implementation
  orchestration: native
  agents: 1
  parallelism: false
---

# QA-001 bounded contract

No task specification existed at launch on stage `5cfe18d`. The owner's explicit
QA-001 prompt authorizes this IMPLEMENT task and its scoped commit, ordinary push,
PR to stage and required checkpoint comments. NEXT already named this task;
no user override is required. This file durably records that authorized scope.

Outcome: reconcile OFL upstream provenance, tracked byte policy and the canonical
manifest without altering license text, tokens, rendering, other assets or
historical evidence. Compare LF-normalized storage against exact upstream bytes;
retain a separately verifiable pinned upstream SHA-256 and deterministic fixture.

Validation: canonical `build-reference.cjs --check`, Node tests QA001-01…08,
Git byte/fresh-checkout proof, unchanged unrelated hashes, historical QA evidence
verification, documentation links, whitespace and secret scan. Negative tests
use temporary fixtures only. Add equivalent canonical/Node checks to CI.

Technical implementation and local validation are complete; lifecycle is
**awaiting-review**, not independently reviewed/PASS. Evidence and current defect
status: [QA-001 reconciliation](../../docs/09-QA/QA-001-RECONCILIATION.md).
Do not rewrite the defect register covered by the immutable historical QA manifest;
use the linked dated current-status record and documentation index.

Delivery: one scoped fix branch from current stage, complete PR metadata/checkpoints,
PR contract and current-head CI. No merge or cloud-review request. Independent
review remains required; self-inspection is not a replacement.

After review/integration, the next intended task is the existing manual/accessibility
release gate. All manual NOT TESTED/OPEN outcomes and ADR-0025 release restrictions
remain intact. No B03, Preview/M2, live provider/user payload, release or data work.
STOP after QA-001 delivery; do not start the next task.
