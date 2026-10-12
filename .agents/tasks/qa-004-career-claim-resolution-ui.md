---
title: QA-004 — Career Claim Resolution UI
status: DONE_VERIFIED_LOCAL / AWAITING_REVIEW
execution:
  workflow: IMPLEMENT
---

# QA-004

Explicit owner authorization: pasted QA-004 request, 2026-10-12. One agent,
sequential execution. Override NEXT `b01-current-manifest-integrity-reconciliation`
for this bounded defect only; NEXT/BLOCKERS and Preview/manual accessibility gates
remain unchanged. Base/target: stage; branch: fix/qa-004-career-claim-resolution-ui.
Commit, normal push and PR authorized; merge prohibited.

Implement explicit Russian Career UI evidence selection for USER_RESOLUTION_REQUIRED
using GET /career fact_ids and PATCH /career/claims/{id}/resolve career_fact_id.
No auto-selection, auto-resolution, new facts or approval. Backend remains authority.
Preserve selection/errors, block duplicate requests, support keyboard and 320/390/768/desktop.
Validate QA004-01–15 components, relevant Career/Truth Guard API regressions,
isolated synthetic browser Career → Application workflow with deterministic local
provider, frontend test/lint/typecheck/build. No development DB writes/live AI.
Update defect/workflow/state/evidence; scoped diff/security review and CI verification.
STOP at QA-004; QA-001–003, lifecycle advancement and release excluded.

Evidence: docs/09-QA/evidence/qa004-2026-10-12/README.md. Local automated criteria PASS;
PR integration/independent review and existing human/provider gates remain separate. STOP.
