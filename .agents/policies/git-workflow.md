# Git Workflow Policy

## Purpose

Define the mandatory Git/GitHub workflow for CVortex and connect branch governance to the value-driven roadmap.

Canonical metadata:

- roadmap: `docs/01-Product/Roadmap.md`;
- GitHub milestone/project mapping: `.github/roadmap.yml`;
- labels: `.github/labels.yml`;
- PR template: `.github/pull_request_template.md`;
- ruleset: `.github/rulesets/cvortex-protected-branches.json`;
- release/version policy: `.agents/policies/release-management.md`.

## Mandatory write preflight

Before the first repository write, the acting agent must determine:

- current execution mode;
- authorized task or explicit current-user override;
- current branch;
- expected base branch;
- expected PR target;
- whether the task is read-only or write-enabled.

For normal write tasks, run the repository preflight when available:

```bash
bash scripts/check-agent-contract.sh <mode> [task-spec] --write
```

If the current user explicitly authorizes a bounded task different from `.agents/state/NEXT.md`, add `--user-override` and record that override in the completion report.

The user does not need to repeat branch instructions in each prompt.

If a write-enabled task starts on `main` or `stage`, the agent must not modify files there. It must synchronize the correct base, derive a bounded branch name, create/switch to that branch, and only then write.

If the current short-lived branch is unrelated to the authorized task, stop before writes unless the explicit current user instruction authorizes that branch.

Tool capability is not authorization. A connector/API that can technically write to `main` or `stage` must still obey this policy.

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

Milestone/slice metadata describes delivery placement. It does not authorize work: `.agents/state/NEXT.md` remains the execution-authority pointer unless the current explicit user instruction authorizes a bounded override.

## Mandatory PR preflight

Opening a PR is not complete merely because GitHub accepts it or `Roadmap metadata` is green.

Before reporting a PR as ready for review or complete, the acting agent must apply and verify the full PR contract:

- at least one assignee;
- at least one canonical `type:*` label;
- at least one canonical `area:*` label;
- exactly one canonical `priority:*` label;
- exactly one canonical `status:*` label;
- exactly one roadmap placement for `stage` PRs;
- correct release-path label for `main` PRs;
- exactly one changelog path: checked release-notes intent or `skip-changelog`;
- PR body follows `.github/pull_request_template.md`;
- top-level `## PR metadata / review checkpoint` comment exists;
- top-level `## Governance / validation checkpoint` comment exists.

Use only labels defined in `.github/labels.yml`. Do not invent near-duplicates in GitHub UI.

When available, run:

```bash
bash scripts/check-pr-contract.sh <pr-number>
```

Apply metadata in this order so the final status-label event revalidates the complete PR state:

1. PR body, assignee, type/area/priority, roadmap and changelog metadata;
2. metadata/review checkpoint comment;
3. governance/validation checkpoint comment;
4. exactly one final `status:*` label, normally `status:review` while awaiting review.

A green check from an earlier incomplete metadata state is not sufficient. Recheck the current head and current metadata before reporting completion.

## Short-lived branches

Use lowercase kebab-case after the prefix:

- `feature/*`;
- `fix/*`;
- `chore/*`;
- `docs/*`;
- `ci/*`;
- `hotfix/*`.

When branch creation is unambiguous, the agent creates it automatically rather than asking the user to do Git housekeeping.

Choose the prefix from the actual change:

- `feature/*` for product capability;
- `fix/*` for defects;
- `chore/*` for repository/tooling/agent-operating work;
- `docs/*` for documentation-only work;
- `ci/*` for CI-only work;
- `hotfix/*` only for urgent released-state fixes based on `main`.

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
2. Run the mandatory write preflight before modifying files.
3. Synchronize local `stage` for normal work.
4. Create/switch to the bounded short-lived branch automatically when needed.
5. Implement tests/docs/security work required by the task.
6. Run relevant validation.
7. Open a PR to `stage`.
8. Apply complete PR metadata and checkpoint comments.
9. Run the mandatory PR preflight.
10. Resolve review threads and required checks.
11. Prefer squash merge for ordinary short-lived branches.
12. Promote validated `stage` to `main` through a release PR only when release policy permits it.

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
- requiring repository governance status checks configured by the canonical ruleset.

`Roadmap metadata` validates milestone/slice/release placement. `PR contract` validates the broader PR metadata/template/checkpoint contract when enabled by the workflow/ruleset.

Apply the ruleset only after `.github/workflows/governance.yml` exists on `stage`; otherwise required checks could lock the repository. `scripts/apply-github-ruleset.sh` enforces its documented preconditions and verifies live state after application.

A repository file describing the desired ruleset is not proof that GitHub currently enforces it. When governance behavior matters, verify the live ruleset state.

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
- create a release/tag from `stage` or a feature branch;
- ask the user to perform routine branch creation or PR metadata work when it can be derived safely.

## Completion checks

A Git-related change is complete only when branch/base, full PR contract, roadmap placement, validation, documentation and release metadata are internally consistent.
