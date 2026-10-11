---
title: B01 — Mandatory manual and accessibility release gate
status: open
owner: project
created: 2026-10-11
updated: 2026-10-11
tags: [task, accessibility, manual-review, release-gate]
execution:
  workflow: review
  orchestration: native
  agents: 1
  parallelism: false
---

# B01 manual/accessibility release gate

Authority: accepted ADR-0025. Mandatory before production release; B01 acceptance
and B02 implementation do not close this task. Current result: **NOT TESTED / OPEN**.

Scope: every outstanding row of `docs/07-Design/reference/MANUAL-ACCEPTANCE.md`:
32 state/viewport combinations, G1–G3, N1–N2, M1, T1–T2, K1, Z1 and A1.
Retain limited agent evidence with its exact scope. Also assess applicable shipped
production components (including B02 shell) at desktop/compact sizes; no real
personal data or employer actions. No code changes are authorized by this review task.

Record human reviewer identity, date, source/token revision, browser/OS/AT versions,
actual states and CSS viewports, native zoom settings, observed focus behavior and
heard screen-reader text/announcements. DOM/AX/screenshot checks do not substitute
for human audible assessment. Preserve NOT TESTED for checks not performed.

Completion requires PASS for every mandatory row and corresponding production
scope, linked evidence, and resolution of blocking findings. Keep FAIL and missing
coverage open. A separate authorized docs block records results and release gate
closure in REVIEW, STATUS and BLOCKERS. No production release while this task is
open; no waiver by cosmetic acceptance. STOP; no B03 or release authorization.
