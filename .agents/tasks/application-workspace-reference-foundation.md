---
title: Application Workspace — Reference Foundation
status: awaiting-review
owner: project
created: 2026-10-08
updated: 2026-10-10
tags: [task, design, tokens, reference]
related: [docs/07-Design/Design-Handoff.md, docs/07-Design/Application-Workspace.md, docs/07-Design/Concept-B-Reconciliation.md, docs/03-ADR/ADR-0024-git-design-reference-workflow.md]
execution:
  workflow: implementation
  orchestration: native
  agents: 1
  parallelism: false
git:
  write: true
  base: stage
  target: stage
  branch: auto
---

# Application Workspace — Reference Foundation (B01)

## Observable outcome

The owner can open one normalized Application Workspace HTML reference locally, inspect required states on desktop/mobile, and see the exact canonical token revision, scenario and review status. It is an executable design reference with an explicit human review record, not a production screen migration.

## Milestone / scope authority

Cross-cutting design foundation, `roadmap:unversioned` if separately authorized for PR delivery. NEXT names this bounded task by explicit reconciliation request. Preview 0.1 acceptance and M2 gates are unchanged. The frontend roadmap is a design breakdown, not permission to implement every row.

## Inputs

- `PROJECT.md`, current state and repository implementation/Git workflows.
- `docs/03-ADR/ADR-0024-git-design-reference-workflow.md`, accepted ADR-0014.
- `docs/07-Design/{Design-Foundation,Application-Workspace,Design-Handoff,Concept-B-Reconciliation}.md`.
- `brand/tokens/cvortex.tokens.json`, existing Concept B and synthetic research fixtures.

## Included

- `docs/07-Design/reference/`: one small normalized runnable reference and a manifest; isolated synthetic fixtures, local assets, loopback-only preview.
- A minimal deterministic reference token derivation/check in that directory or a narrowly named script; resolve canonical aliases, check missing/cyclic references and preserve DTCG units/types.
- Add only justified component-role aliases listed in the reconciliation ledger if the reference needs them; document consumed roles. Preserve existing token values; no speculative color/spacing palette or full production transformer.
- Demonstrate opportunity selection, workspace header/local navigation, evidence inspector and mobile levels. Show normal/loading/empty/failed/stale/blocked/pending/approval-distinct states without backend requests.
- Record state/viewport/source/token hash and actual automatic checks. Capture synthetic screenshots and prepare a concrete human review surface.

Owner feedback on 2026-10-10 authorizes bounded reference polish: an isolated demonstration account menu with user/admin/guest modes, prominent fixture salary, stronger use of existing color roles, local decorative artwork and canonical iconography. This remains B01 reference work. It does not authorize production authentication, a new admin panel, token changes or B02.

## Excluded

Changes to `apps/`, backend/API/database/migrations, dependencies/lockfiles, deployment, live AI/context transmission, employer communications, full Employer Memory/tracking/export, route deletion, mass component coding, Storybook installation, Figma and subagents. Do not replace the original research prototype or its evidence. Keep reference-only capabilities labelled synthetic/unavailable for production.

## Requirements and validation

- Follow existing brand/state/type/space/layer/accessibility contracts; normalize prototype-only literals.
- Missing provenance, pending evidence and confirmed conflicts must not appear approvable in the demonstration. Accept, content approval and application submission are distinct.
- Human review status starts `draft`; mark `reviewed` only with a named human review, date, outcome and state/viewport scope. An agent's screenshot is not human approval. If owner input is required, finish the concrete review surface and record awaiting review rather than invent PASS.
- Use an available browser/runtime; do not install a large toolchain. Add only narrow checks for alias failure/derivation and meaningful rendered state boundaries; avoid tests mirroring static markup.
- Verify local links/assets, `git diff --check`, no external preview requests and unchanged production hashes/diff.
- Render desktop 1440, tablet 768 and compact 390/320; verify reflow, keyboard/visible focus/Escape/return, readable evidence, controls and status announcements. Record real 200% zoom/manual AT checks only if actually performed; limitations remain explicit.
- Planned production ESLint/TypeScript/Vitest/build and visual regression are outside this task unless production scope is separately authorized.

## Completion criteria

- [x] One local reference opens and its token-derived CSS/manifest match the canonical source.
- [x] Required synthetic states and mobile level navigation are inspectable with no API/AI operation.
- [x] Evidence paths and actually run checks recorded; review status is truthful.
- [x] Human visual review recorded, or the concrete review surface is complete with task status `awaiting-review` and the pending owner action recorded. In the latter case do not report task PASS or advance to B02.
- [x] Production files and original research remain unchanged.
- [x] STATUS/NEXT/BLOCKERS reflect the actual outcome and remaining gate.

Recovery result on 2026-10-08: technical foundation and concrete review surface complete; **awaiting-review**, not PASS. Evidence and limitations: [reference README](../../docs/07-Design/reference/README.md). Next bounded action is owner visual/state review of B01 via [REVIEW.md](../../docs/07-Design/reference/REVIEW.md); NEXT retains this task until that gate is recorded. No B02 work is authorized by this closeout.

## STOP

Stop after this reference foundation. Do not start B02, migrate production UI, mark Preview PASS or begin M2. Commit, push, PR, publication and external messages require separate explicit authorization.
