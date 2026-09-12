#!/usr/bin/env bash
set -euo pipefail

if ! command -v gh >/dev/null 2>&1; then
  echo "error: GitHub CLI (gh) is required" >&2
  exit 1
fi

if ! gh auth status >/dev/null 2>&1; then
  echo "error: GitHub CLI is not authenticated" >&2
  exit 1
fi

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
cd "$ROOT"

REPO="${CVORTEX_REPO:-filatelist-hitech/CVortex}"
RULESET_FILE="$ROOT/.github/rulesets/cvortex-protected-branches.json"
RULESET_NAME="CVortex protected integration branches"

[[ -f "$RULESET_FILE" ]] || {
  echo "error: missing ruleset file: $RULESET_FILE" >&2
  exit 1
}

# Refuse to require a check that does not yet exist on the protected integration branch.
if ! gh api "repos/$REPO/contents/.github/workflows/governance.yml?ref=stage" >/dev/null 2>&1; then
  cat >&2 <<'EOF'
error: .github/workflows/governance.yml is not present on stage.
Do not apply the ruleset yet: requiring Roadmap metadata before the workflow exists can lock merges.
EOF
  exit 1
fi

RULESET_ID="$(gh api "repos/$REPO/rulesets" \
  --jq ".[] | select(.name == \"$RULESET_NAME\") | .id" | head -n1)"

if [[ -z "$RULESET_ID" ]]; then
  gh api --method POST "repos/$REPO/rulesets" --input "$RULESET_FILE" >/dev/null
  echo "ruleset created: $RULESET_NAME"
else
  gh api --method PUT "repos/$REPO/rulesets/$RULESET_ID" --input "$RULESET_FILE" >/dev/null
  echo "ruleset updated: $RULESET_NAME (#$RULESET_ID)"
fi

# Re-resolve the live ruleset after mutation and verify GitHub actually enforces
# the repository-managed desired state. A successful API mutation alone is not proof.
RULESET_ID="$(gh api "repos/$REPO/rulesets" \
  --jq ".[] | select(.name == \"$RULESET_NAME\") | .id" | head -n1)"

[[ -n "$RULESET_ID" ]] || {
  echo "error: ruleset was not found after apply" >&2
  exit 1
}

for required_rule in deletion non_fast_forward pull_request required_status_checks; do
  count="$(gh api "repos/$REPO/rulesets/$RULESET_ID" \
    --jq "[.rules[] | select(.type == \"$required_rule\")] | length")"
  if [[ "$count" -lt 1 ]]; then
    echo "error: live ruleset is missing required rule: $required_rule" >&2
    exit 1
  fi
done

roadmap_check_count="$(gh api "repos/$REPO/rulesets/$RULESET_ID" \
  --jq '[.rules[] | select(.type == "required_status_checks") | .parameters.required_status_checks[]? | select(.context == "Roadmap metadata")] | length')"

if [[ "$roadmap_check_count" -lt 1 ]]; then
  echo "error: live ruleset does not require the Roadmap metadata status check" >&2
  exit 1
fi

echo "ruleset verified: $RULESET_NAME (#$RULESET_ID)"
echo "required status check verified: Roadmap metadata"
