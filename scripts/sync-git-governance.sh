#!/usr/bin/env bash
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
POLICY_DIR="$ROOT/.agents/policies"
POLICY_FILE="$POLICY_DIR/git-workflow.md"
AGENTS_FILE="$ROOT/AGENTS.md"
CONTRIBUTING_FILE="$ROOT/CONTRIBUTING.md"
RULESET_FILE="$ROOT/.github/rulesets/cvortex-protected-branches.json"

BEGIN_MARKER='<!-- CVORTEX:GIT-WORKFLOW:BEGIN -->'
END_MARKER='<!-- CVORTEX:GIT-WORKFLOW:END -->'

mkdir -p "$POLICY_DIR"

cat > "$POLICY_FILE" <<'EOF'
# Git Workflow Policy

## Purpose

Define the mandatory Git and GitHub workflow for CVortex development agents and contributors.

Human-facing contribution details live in `CONTRIBUTING.md`. The importable GitHub repository ruleset lives in `.github/rulesets/cvortex-protected-branches.json`.

## Protected long-lived branches

### `main`

- Stable/release state only.
- Normal product development never starts from `main`.
- Changes arrive through a pull request from `stage`.
- Emergency fixes may use `hotfix/*` branched from `main`.
- Never force-push or delete `main`.

### `stage`

- Integration branch for validated development work.
- Normal short-lived branches start from an up-to-date `stage`.
- Changes arrive through pull requests.
- Never force-push or delete `stage`.

## Short-lived branches

Use lowercase kebab-case after the prefix:

- `feature/*` — product capability;
- `fix/*` — non-emergency defect fix;
- `chore/*` — tooling, repository or maintenance work;
- `docs/*` — documentation-only changes;
- `ci/*` — CI/CD changes;
- `hotfix/*` — urgent release fix created from `main`.

Examples:

- `feature/vacancy-import`
- `fix/employer-memory-conflict`
- `chore/git-governance`
- `docs/truth-guard-adr`
- `ci/backend-tests`
- `hotfix/credential-redaction`

## Standard workflow

1. Read repository instructions and current project state before making changes.
2. Synchronize local `stage`.
3. Create a short-lived branch from `stage`.
4. Keep the branch bounded to one concern.
5. Implement the change together with applicable tests and documentation.
6. Run relevant validation locally.
7. Use reviewable Conventional Commit-style commits.
8. Open a pull request into `stage`.
9. Resolve review threads and required checks before merge.
10. Prefer squash merge for ordinary short-lived branches.
11. Promote validated `stage` to `main` through a separate release pull request.

## Hotfix workflow

1. Branch `hotfix/*` from current `main`.
2. Implement only the urgent fix and its required validation.
3. Open a pull request into `main`.
4. After merge, immediately propagate the same fix back to `stage` through a pull request or cherry-pick on a short-lived branch.
5. Confirm `main` and `stage` no longer diverge on the hotfix.

## Commit policy

Prefer Conventional Commit-style messages:

- `feat(scope): ...`
- `fix(scope): ...`
- `docs(scope): ...`
- `test(scope): ...`
- `refactor(scope): ...`
- `build(scope): ...`
- `ci(scope): ...`
- `chore(scope): ...`

Do not mix unrelated refactors, formatting sweeps or generated files into a functional change unless they are required by that change.

## Pull request requirements

Before merge, verify as applicable:

- relevant tests pass;
- authorization and cross-user isolation are considered;
- migrations and backward compatibility are considered;
- error handling and observability are adequate;
- documentation is current;
- an ADR is updated or added when an accepted architectural decision changes;
- no secrets, tokens, credentials, private career data, recruiter messages or production data are committed;
- external vacancy, recruiter, website and imported-document content remains untrusted input;
- review conversations are resolved.

## Repository ruleset

The canonical importable ruleset is:

`.github/rulesets/cvortex-protected-branches.json`

It protects `main` and `stage` by:

- requiring pull requests;
- blocking branch deletion;
- blocking non-fast-forward updates / force pushes;
- requiring review conversations to be resolved;
- requiring zero approving reviews while CVortex is a solo-maintained repository.

Do not add required status checks to the ruleset until the corresponding GitHub Actions checks exist and are stable. Once CI exists, update both the ruleset and this policy in the same change.

## Mandatory agent behavior

Development agents must not:

- develop directly on `main` or `stage` after repository bootstrap;
- force-push protected branches;
- silently rewrite published branch history;
- bypass a failing validation by weakening the ruleset;
- merge architectural changes without updating the relevant ADR/documentation;
- commit secrets or private user/job-search data.

If repository state conflicts with this policy, stop the write operation, report the conflict, and resolve it explicitly rather than guessing.

## Completion checks

A Git-related change is complete only when:

- the intended branch/base branch is correct;
- validation relevant to the change has run;
- documentation is synchronized;
- the protected-branch rules remain enforceable;
- no secret/private data was introduced;
- branch history remains understandable and traceable.
EOF

managed_block() {
  printf '%s\n' "$BEGIN_MARKER"
  cat <<'EOF'
## Git workflow

For all repository changes, follow `.agents/policies/git-workflow.md` and `CONTRIBUTING.md`.

Mandatory baseline: work on short-lived branches, target `stage` for normal changes, promote `stage` to `main` via release PR, and never force-push or delete protected branches.

The canonical GitHub ruleset is `.github/rulesets/cvortex-protected-branches.json`.
Versioning, tags, and GitHub Releases follow `.agents/policies/release-management.md`.
EOF
  printf '%s\n' "$END_MARKER"
}

if [[ -f "$AGENTS_FILE" ]]; then
  tmp="$(mktemp)"
  awk -v begin="$BEGIN_MARKER" -v end="$END_MARKER" '
    $0 == begin { skip=1; next }
    $0 == end { skip=0; next }
    !skip { print }
  ' "$AGENTS_FILE" > "$tmp"

  # Trim trailing blank lines before appending the managed block.
  awk 'NF { blank=0 } !NF { blank++ } { lines[NR]=$0 } END { last=NR-blank; for (i=1; i<=last; i++) print lines[i] }' "$tmp" > "${tmp}.trimmed"
  mv "${tmp}.trimmed" "$tmp"

  {
    cat "$tmp"
    printf '\n\n'
    managed_block
    printf '\n'
  } > "$AGENTS_FILE"
  rm -f "$tmp"
else
  printf 'info: AGENTS.md does not exist yet; created policy only. Re-run after Phase 00 to link it from AGENTS.md.\n' >&2
fi

if [[ ! -f "$CONTRIBUTING_FILE" ]]; then
  printf 'warning: CONTRIBUTING.md is missing; Git policy was created but human-facing documentation should be restored.\n' >&2
fi

if [[ ! -f "$RULESET_FILE" ]]; then
  printf 'warning: ruleset file is missing: %s\n' "$RULESET_FILE" >&2
fi

printf 'Git governance synchronized:\n'
printf '  policy: %s\n' "$POLICY_FILE"
if [[ -f "$AGENTS_FILE" ]]; then
  printf '  agents: %s\n' "$AGENTS_FILE"
fi
printf '  ruleset: %s\n' "$RULESET_FILE"
