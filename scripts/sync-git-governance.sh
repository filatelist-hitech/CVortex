#!/usr/bin/env bash
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
POLICY_FILE="$ROOT/.agents/policies/git-workflow.md"
AGENTS_FILE="$ROOT/AGENTS.md"
CONTRIBUTING_FILE="$ROOT/CONTRIBUTING.md"
RULESET_FILE="$ROOT/.github/rulesets/cvortex-protected-branches.json"
ROADMAP_FILE="$ROOT/.github/roadmap.yml"
GOVERNANCE_WORKFLOW="$ROOT/.github/workflows/governance.yml"

BEGIN_MARKER='<!-- CVORTEX:GIT-WORKFLOW:BEGIN -->'
END_MARKER='<!-- CVORTEX:GIT-WORKFLOW:END -->'

for required in "$POLICY_FILE" "$CONTRIBUTING_FILE" "$RULESET_FILE" "$ROADMAP_FILE" "$GOVERNANCE_WORKFLOW"; do
  [[ -f "$required" ]] || {
    printf 'error: required governance file missing: %s\n' "$required" >&2
    exit 1
  }
done

managed_block() {
  printf '%s\n' "$BEGIN_MARKER"
  cat <<'EOF'
## Git workflow

For all repository changes, follow `.agents/policies/git-workflow.md` and `CONTRIBUTING.md`.

Mandatory baseline: work on short-lived branches, target `stage` for normal changes, and promote `stage` to `main` only through release policy.

Every PR to `stage` must have a native M0–M6 GitHub Milestone or `roadmap:unversioned`. M1 work also carries one M1 slice label or `roadmap:cross-cutting`.

Canonical GitHub delivery metadata is `.github/roadmap.yml`. The protected-branch ruleset is `.github/rulesets/cvortex-protected-branches.json`; `Roadmap metadata` is a required check once that ruleset is applied.

Versioning, tags and GitHub Releases follow `.agents/policies/release-management.md`.
EOF
  printf '%s\n' "$END_MARKER"
}

if [[ ! -f "$AGENTS_FILE" ]]; then
  echo "error: AGENTS.md is missing" >&2
  exit 1
fi

tmp="$(mktemp)"
awk -v begin="$BEGIN_MARKER" -v end="$END_MARKER" '
  $0 == begin { skip=1; next }
  $0 == end { skip=0; next }
  !skip { print }
' "$AGENTS_FILE" > "$tmp"

awk 'NF { blank=0 } !NF { blank++ } { lines[NR]=$0 } END { last=NR-blank; for (i=1; i<=last; i++) print lines[i] }' "$tmp" > "${tmp}.trimmed"
mv "${tmp}.trimmed" "$tmp"

{
  cat "$tmp"
  printf '\n\n'
  managed_block
  printf '\n'
} > "$AGENTS_FILE"
rm -f "$tmp"

printf 'Git governance references synchronized.\n'
printf '  policy: %s\n' "$POLICY_FILE"
printf '  roadmap metadata: %s\n' "$ROADMAP_FILE"
printf '  ruleset: %s\n' "$RULESET_FILE"
printf '  workflow: %s\n' "$GOVERNANCE_WORKFLOW"
printf '\nTo sync GitHub labels/milestones/project:\n'
printf '  bash scripts/bootstrap-github-roadmap.sh\n'
printf 'To apply protected-branch rules after governance.yml is on stage:\n'
printf '  bash scripts/apply-github-ruleset.sh\n'
