# Git Workflow Policy

## Purpose

Define the mandatory Git/GitHub workflow for CVortex and connect branch governance to the value-driven roadmap.

Canonical metadata:

- roadmap: `docs/01-Product/Roadmap.md`;
- GitHub milestone/project mapping: `.github/roadmap.yml`;
- labels: `.github/labels.yml`;
- ruleset: `.github/rulesets/cvortex-protected-branches.json`;
- release/version policy: `.agents/policies/release-management.md`.

## Long-lived branches

### `main`

- Stable/release state only.
- Normal product development never starts from `main`.
- Normal release changes arrive from `stage`.
- Urgent fixes may arrive from `hotfix/*` created from `main`.
- Never force-push or delete `main`.

### `stage`

- Integration branch for validated development work.
- Normal short-lived branches start from an up-to-date `stage`.
- Changes arrive through pull requests.
- Never force-push or delete `stage`.

## Roadmap placement

Every PR targeting `stage` must have exactly one delivery placement:

1. a native GitHub Milestone matching `M0` through `M6`; or
2. `roadmap:unversioned` when the work is intentionally cross-cutting and does not target a product milestone/version.

Do not use `roadmap:unversioned` to avoid choosing an obvious milestone.

For `M1 · First Value`, also assign exactly one of:

- `slice:m1.1-access`;
- `slice:m1.2-career`;
- `slice:m1.3-vacancy`;
- `slice:m1.4-application`;
- or `roadmap:cross-cutting` when the change genuinely spans slices.

Milestone/slice metadata describes delivery placement. It does not authorize work: `.agents/state/NEXT.md` remains the execution-authority pointer.

## Short-lived branches

Use lowercase kebab-case after the prefix:

- `feature/*`;
- `fix/*`;
- `chore/*`;
- `docs/*`;
- `ci/*`;
- `hotfix/*`.

When a branch clearly belongs to a roadmap unit, include the milestone/slice token for quick recognition, for example:

- `chore/m0-runnable-core`;
- `feature/m1.2-career-facts`;
- `feature/m1.3-vacancy-paste`;
- `fix/m1.4-truth-guard`.

Do not force milestone numbers into generic cross-cutting branches.

## Commits

Prefer Conventional Commit-style messages such as `feat(vacancies): ...` and `fix(auth): ...`.

Commit scope should describe the code/domain area, not the roadmap number. Milestones change over time; code history should remain meaningful after the roadmap moves on.

Do not mix unrelated work into one commit merely to make a milestone look busy. Humanity already invented project dashboards for that illusion.

## Standard workflow

1. Read `PROJECT.md`, current state and task spec.
2. Synchronize local `stage`.
3. Create a bounded short-lived branch.
4. Implement tests/docs/security work required by the task.
5. Run relevant validation.
6. Open a PR to `stage`.
7. Assign native GitHub Milestone or `roadmap:unversioned`.
8. Assign M1 slice metadata when applicable.
9. Resolve review threads and required checks.
10. Prefer squash merge for ordinary short-lived branches.
11. Promote validated `stage` to `main` through a release PR only when release policy permits it.

## PRs to `main`

Normal promotion:

```text
stage → main
label: release:promotion
```

Hotfix:

```text
hotfix/* → main
label: release:hotfix
```

No other source branch should target `main` under normal operation.

After a hotfix merge, propagate the fix back to `stage` immediately.

## GitHub Project

The recommended planning surface is the native GitHub Project `CVortex Roadmap` linked to this repository.

Use native GitHub Milestone for `M0–M6`. Project custom fields are:

- `Delivery`: Backlog / Ready / In progress / Review / Blocked / Done;
- `Slice`: None / M1.1 / M1.2 / M1.3 / M1.4;
- `Target version`: text SemVer target;
- `Priority`: P0 / P1 / P2 / P3.

Do not duplicate private career data, recruiter messages or secrets into GitHub Project fields/cards.

Repository-managed desired state lives in `.github/roadmap.yml`; synchronize it with `scripts/bootstrap-github-roadmap.sh`.

## Repository ruleset

The canonical importable/applyable ruleset is `.github/rulesets/cvortex-protected-branches.json`.

It protects `main` and `stage` by:

- requiring PRs;
- blocking deletion;
- blocking force pushes/non-fast-forward updates;
- requiring review-thread resolution;
- requiring the `Roadmap metadata` GitHub Actions check.

The check validates milestone/slice placement for `stage` PRs and release/hotfix source metadata for `main` PRs.

Apply the ruleset only after `.github/workflows/governance.yml` exists on `stage`; otherwise a required check could lock the repository. `scripts/apply-github-ruleset.sh` enforces this precondition.

## Security and quality

Before merge, verify as applicable:

- tests pass;
- authorization and cross-user isolation are considered;
- migrations/backward compatibility are considered;
- error handling and observability are adequate;
- documentation is current;
- ADRs are updated for accepted architecture changes;
- no secrets, credentials, private career data, recruiter messages or production data are committed;
- external content remains untrusted input.

## Mandatory agent behavior

Development agents must not:

- push product work directly to `main` or `stage`;
- force-push protected branches;
- silently rewrite published history;
- bypass governance checks by weakening labels/rulesets;
- invent a milestone/version merely to satisfy CI;
- create a release/tag from `stage` or a feature branch.

## Completion checks

A Git-related change is complete only when branch/base, roadmap placement, validation, documentation and release metadata are internally consistent.
