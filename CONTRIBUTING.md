# Contributing to CVortex

## Branch model

CVortex uses a staging workflow with roadmap-aware delivery metadata.

| Branch | Purpose | Typical source | PR target |
| --- | --- | --- | --- |
| `main` | Stable/release state | `stage`, `hotfix/*` | — |
| `stage` | Integration and staging | short-lived branches | `main` for release |
| `feature/*` | Product capability | `stage` | `stage` |
| `fix/*` | Non-emergency defect | `stage` | `stage` |
| `chore/*` | Tooling/repository/maintenance | `stage` | `stage` |
| `docs/*` | Documentation-only | `stage` | `stage` |
| `ci/*` | CI/CD | `stage` | `stage` |
| `hotfix/*` | Urgent released-state fix | `main` | `main`, then back to `stage` |

Do not develop directly on `main` or `stage`.

## Roadmap placement

Every PR to `stage` must use either:

- one native GitHub Milestone from `M0 · Runnable Core` through `M6 · Distribution & Hardening`; or
- `roadmap:unversioned` for truly cross-cutting work without a product-version target.

M1 PRs also use exactly one M1 slice label unless `roadmap:cross-cutting` is justified.

The canonical mapping lives in `.github/roadmap.yml`.

## Workflow

1. Synchronize `stage`.
2. Create a bounded short-lived branch.
3. Implement behavior, tests, security controls and docs together.
4. Run relevant validation.
5. Open PR to `stage`.
6. Complete the PR body using `.github/pull_request_template.md`.
7. Assign at least one assignee, canonical `type:*` and `area:*` labels, exactly one `priority:*`, roadmap placement and changelog choice.
8. Add `## PR metadata / review checkpoint` and `## Governance / validation checkpoint` comments.
9. Apply exactly one final `status:*` label, normally `status:review` while awaiting review.
10. Run `bash scripts/check-pr-contract.sh <pr-number>` when available and require both Governance checks to be green.
11. Resolve review threads and required checks.
12. Squash-merge ordinary branches.
13. Promote validated `stage` to `main` only through release policy.

## Branch naming

Use lowercase kebab-case. Include milestone/slice when it materially improves recognition:

- `chore/m0-runnable-core`
- `feature/m1.2-career-facts`
- `feature/m1.3-vacancy-paste`
- `fix/m1.4-truth-guard`
- `docs/github-governance`

## Commits

Use Conventional Commit-style messages. Keep the scope domain-oriented (`feat(vacancies)`) rather than roadmap-oriented (`feat(m1.3)`), so history stays useful after milestones close.

## Pull request contract

Before a PR is reported ready for review or merge, verify:

- at least one assignee;
- at least one canonical `type:*` label;
- at least one canonical `area:*` label;
- exactly one `priority:*` label;
- exactly one `status:*` label;
- correct milestone/roadmap placement;
- exactly one changelog path: release-notes intent or `skip-changelog`;
- PR-template sections are present;
- metadata/review checkpoint comment exists;
- governance/validation checkpoint comment exists.

Canonical label names live in `.github/labels.yml`. Do not invent near-duplicate labels in the GitHub UI.

The Governance workflow exposes two checks:

- `Roadmap metadata` for milestone/slice/release placement;
- `PR contract` for the broader metadata/template/checkpoint contract.

Before merge, also verify applicable tests, authorization/cross-user isolation, migrations/backward compatibility, error handling, observability, docs, ADR impact, secret/private-data safety and untrusted-input handling.

## GitHub metadata bootstrap

Canonical labels:

```bash
bash scripts/bootstrap-github-metadata.sh
```

Canonical Milestones + GitHub Project:

```bash
gh auth refresh -s project
bash scripts/bootstrap-github-roadmap.sh
```

Canonical protected-branch ruleset, only after the governance workflow exists on `stage`:

```bash
bash scripts/apply-github-ruleset.sh
```

Do not invent near-duplicate labels or milestones manually in the UI.

## Releases and tags

Follow `.agents/policies/release-management.md`.

Key rules:

- release tags come only from `main`;
- milestone completion and version publication are separate decisions;
- M1 targets `v0.1.0`, M2 targets `v0.2.0`, and later milestones advance the pre-1.0 minor line;
- M0 may optionally publish `v0.1.0-alpha.1` after real runtime validation;
- M1.1–M1.4 do not create four fake releases;
- `v1.0.0` requires a separate stability decision after M6.

## Merge policy

Prefer squash merge for ordinary short-lived branches. `stage → main` release PRs may use a normal merge when preserving the release boundary is useful.

Never force-push `main` or `stage`.
