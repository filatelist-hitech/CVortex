---
title: GitHub Delivery Governance
status: accepted
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [github, roadmap, milestones, project, releases, governance]
related:
  - "[[../01-Product/Roadmap|Product Roadmap]]"
---

# GitHub Delivery Governance

## Purpose

GitHub should reflect the CVortex value-driven roadmap instead of keeping milestones and versions only in prose.

Repository-managed desired state:

- `.github/roadmap.yml` — milestone/version/slice/project mapping;
- `.github/labels.yml` — canonical labels;
- `.github/workflows/governance.yml` — PR metadata validation;
- `.github/rulesets/cvortex-protected-branches.json` — protected-branch rules;
- `.agents/policies/git-workflow.md` — contributor/agent workflow;
- `.agents/policies/release-management.md` — SemVer and release gates.

## Native GitHub Milestones

Create exactly these active milestone identities:

| GitHub Milestone | Target version | Meaning |
| --- | --- | --- |
| `M0 · Runnable Core` | `v0.1.0-alpha.1` optional | runnable technical baseline |
| `M1 · First Value` | `v0.1.0` | Preview 0.1 |
| `M2 · Real Application Package` | `v0.2.0` | MVP 0.2 |
| `M3 · Imports & Integrations` | `v0.3.0` | input/integration expansion |
| `M4 · Employer Journey` | `v0.4.0` | employer context + interview flow |
| `M5 · Outcomes & Analytics` | `v0.5.0` | outcome learning |
| `M6 · Distribution & Hardening` | `v0.6.0` | deployment/operations hardening |

Do not add arbitrary due dates merely to make a roadmap chart look industrious. Add dates only when the owner actually commits to them.

Closing a Milestone records delivery completion; it does not automatically publish the target version.

## M1 slices

GitHub Milestones are intentionally coarse. M1 vertical slices use labels:

- `slice:m1.1-access`;
- `slice:m1.2-career`;
- `slice:m1.3-vacancy`;
- `slice:m1.4-application`.

Use `roadmap:cross-cutting` only when one M1 change genuinely spans slices.

## GitHub Project

Recommended Project title: `CVortex Roadmap`.

Keep the Project private by default unless the owner deliberately chooses public visibility.

Native Milestone remains the milestone authority. Do not create a duplicate custom Milestone field.

Custom fields:

| Field | Type | Values |
| --- | --- | --- |
| Delivery | single select | Backlog, Ready, In progress, Review, Blocked, Done |
| Slice | single select | None, M1.1, M1.2, M1.3, M1.4 |
| Target version | text | SemVer target, for example `v0.1.0` |
| Priority | single select | P0, P1, P2, P3 |

Recommended views:

1. **Current** — filter to current/open Milestone, group by Delivery.
2. **Roadmap** — group by native Milestone, sort by Priority.
3. **M1 Slices** — filter Milestone M1, group by Slice.
4. **Release** — filter non-empty Target version, group/sort by target.
5. **Blocked** — Delivery=Blocked or `status:blocked`.

Project fields must never contain private career facts, recruiter messages, secrets or production data.

## PR governance

PRs to `stage` require either a native M0–M6 Milestone or `roadmap:unversioned`.

M1 PRs additionally require one M1 slice label unless `roadmap:cross-cutting` is justified.

PRs to `main` are only:

- `stage → main` with `release:promotion`; or
- `hotfix/* → main` with `release:hotfix`.

`.github/workflows/governance.yml` enforces these rules as the `Roadmap metadata` check.

## Versioning

Milestones target a version but do not mechanically create it.

- M0 may optionally produce `v0.1.0-alpha.1`.
- M1 produces the first required coherent preview target `v0.1.0` only after M1.1–M1.4 work end to end.
- M2 targets `v0.2.0`.
- M3–M6 advance minor pre-1.0 versions.
- patch releases fix an already published milestone line.
- `v1.0.0` requires a separate production-stability decision after M6.

## Bootstrap

Sync labels, Milestones and Project:

```bash
gh auth refresh -s project
bash scripts/bootstrap-github-roadmap.sh
```

The script is idempotent and preserves closed milestone state when re-run.

Apply branch rules only after the governance workflow exists on `stage`:

```bash
bash scripts/apply-github-ruleset.sh
```

The apply script deliberately refuses to install a required `Roadmap metadata` check before the workflow file is available on `stage`, avoiding a governance-shaped self-inflicted outage.

## Authority

Planning metadata never overrides execution state.

```text
GitHub Milestone / Project
= planning + visibility

.agents/state/NEXT.md
= exact next task authorized for execution
```

When they disagree, stop and reconcile instead of silently following whichever UI happens to look more official.
