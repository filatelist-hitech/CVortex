# Contributing to CVortex

## Branch model

CVortex uses a lightweight staging workflow.

| Branch | Purpose | Typical source | PR target |
| --- | --- | --- | --- |
| `main` | Stable/release state | `stage`, `hotfix/*` | — |
| `stage` | Integration and staging | short-lived branches | `main` for release |
| `feature/*` | Product capability | `stage` | `stage` |
| `fix/*` | Non-emergency bug fix | `stage` | `stage` |
| `chore/*` | Tooling, repository, maintenance | `stage` | `stage` |
| `docs/*` | Documentation-only change | `stage` | `stage` |
| `ci/*` | CI/CD work | `stage` | `stage` |
| `hotfix/*` | Urgent production/release fix | `main` | `main`, then back to `stage` |

Do not develop directly on `main` or `stage` after the repository bootstrap.

## Workflow

1. Synchronize local `stage`.
2. Create a short-lived branch from `stage`.
3. Keep the change bounded to one concern.
4. Add or update tests when behavior changes.
5. Update documentation and ADRs when architecture or accepted decisions change.
6. Open a pull request to `stage`.
7. Promote validated staging state to `main` through a separate release PR.

For an urgent hotfix, branch from `main`, merge the fix to `main`, then merge or cherry-pick the same fix back to `stage` immediately.

## Branch naming

Use lowercase kebab-case after the prefix, for example:

- `feature/vacancy-import`
- `fix/employer-memory-conflict`
- `chore/repository-foundation`
- `docs/truth-guard-adr`
- `ci/backend-tests`
- `hotfix/credential-redaction`

## Commits

Prefer Conventional Commit-style messages:

- `feat(scope): ...`
- `fix(scope): ...`
- `docs(scope): ...`
- `test(scope): ...`
- `refactor(scope): ...`
- `build(scope): ...`
- `ci(scope): ...`
- `chore(scope): ...`

Keep commits reviewable. Do not mix unrelated refactors with behavior changes merely because the keyboard was already warm.

## Pull request checks

Before merge, verify as applicable:

- tests pass;
- authorization and cross-user isolation are covered;
- migrations are safe and backward compatibility is considered;
- error handling and observability are adequate;
- documentation is current;
- an ADR is updated or added for architectural changes;
- no secret, token, personal resume, recruiter message, or production data is present;
- untrusted external content is not treated as agent/system instructions.

## Merge policy

Prefer **squash merge** for ordinary short-lived branches to keep history readable. Release PRs from `stage` to `main` may use a normal merge when preserving the release boundary is useful.

Never force-push `main` or `stage` as part of normal work.
