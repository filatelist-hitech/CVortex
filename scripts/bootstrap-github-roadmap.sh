#!/usr/bin/env bash
set -euo pipefail

if ! command -v gh >/dev/null 2>&1; then
  echo "error: GitHub CLI (gh) is required" >&2
  exit 1
fi

if ! gh auth status >/dev/null 2>&1; then
  echo "error: GitHub CLI is not authenticated; run: gh auth login" >&2
  exit 1
fi

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
cd "$ROOT"

REPO="${CVORTEX_REPO:-filatelist-hitech/CVortex}"
ROADMAP_FILE="$ROOT/.github/roadmap.yml"

[[ -f "$ROADMAP_FILE" ]] || {
  echo "error: missing .github/roadmap.yml" >&2
  exit 1
}

# Keep labels synchronized before milestones/project fields refer to them.
bash "$ROOT/scripts/bootstrap-github-metadata.sh"

parse_milestones() {
  awk '
    function clean(s) {
      sub(/^[^:]+:[[:space:]]*/, "", s)
      gsub(/^"/, "", s)
      gsub(/"$/, "", s)
      return s
    }
    /^milestones:/ { section="milestones"; next }
    /^slices:/ {
      if (section=="milestones" && key!="") print key "\t" title "\t" version "\t" description
      section="slices"
      key=""; title=""; version=""; description=""
      next
    }
    section=="milestones" {
      if ($0 ~ /^  - key:/) {
        if (key!="") print key "\t" title "\t" version "\t" description
        key=clean($0); title=""; version=""; description=""
      } else if ($0 ~ /^    title:/) {
        title=clean($0)
      } else if ($0 ~ /^    target_version:/) {
        version=clean($0)
      } else if ($0 ~ /^    description:/) {
        description=clean($0)
      }
    }
    END {
      if (section=="milestones" && key!="") print key "\t" title "\t" version "\t" description
    }
  ' "$ROADMAP_FILE"
}

sync_milestone() {
  local key="$1" title="$2" version="$3" description="$4"
  local number body

  [[ -n "$key" && -n "$title" && -n "$version" ]] || {
    echo "error: invalid milestone entry in .github/roadmap.yml" >&2
    exit 1
  }

  number="$(gh api --paginate "repos/$REPO/milestones?state=all&per_page=100" \
    --jq ".[] | select(.title == \"$title\") | .number" | head -n1)"

  body="$description

Target release: \`$version\`. Canonical mapping: .github/roadmap.yml."

  if [[ -z "$number" ]]; then
    gh api --method POST "repos/$REPO/milestones" \
      -f title="$title" \
      -f description="$body" >/dev/null
    printf 'milestone created: %s (%s)\n' "$title" "$version"
  else
    # Preserve open/closed state. Re-running bootstrap must not reopen completed milestones.
    gh api --method PATCH "repos/$REPO/milestones/$number" \
      -f title="$title" \
      -f description="$body" >/dev/null
    printf 'milestone synced: %s (%s)\n' "$title" "$version"
  fi
}

while IFS=$'\t' read -r key title version description; do
  sync_milestone "$key" "$title" "$version" "$description"
done < <(parse_milestones)

project_value() {
  local field="$1"
  awk -v field="$field" '
    function clean(s) {
      sub(/^[^:]+:[[:space:]]*/, "", s)
      gsub(/^"/, "", s)
      gsub(/"$/, "", s)
      return s
    }
    /^project:/ { in_project=1; next }
    in_project && $0 ~ "^  " field ":" { print clean($0); exit }
    in_project && /^[^[:space:]]/ { exit }
  ' "$ROADMAP_FILE"
}

OWNER="${CVORTEX_PROJECT_OWNER:-$(project_value owner)}"
PROJECT_TITLE="$(project_value title)"
PROJECT_VISIBILITY="${CVORTEX_PROJECT_VISIBILITY:-$(project_value visibility)}"
PROJECT_VISIBILITY="$(printf '%s' "$PROJECT_VISIBILITY" | tr '[:lower:]' '[:upper:]')"
REPO_NAME="${REPO#*/}"

if [[ "${CVORTEX_SKIP_PROJECT:-0}" == "1" ]]; then
  echo "GitHub Project sync skipped (CVORTEX_SKIP_PROJECT=1)."
  exit 0
fi

if ! gh project list --owner "$OWNER" --limit 1 >/dev/null 2>&1; then
  cat >&2 <<'EOF'
error: GitHub Project access is unavailable.
Run: gh auth refresh -s project
Then re-run this script.
EOF
  exit 1
fi

PROJECT_NUMBER="$(gh project list --owner "$OWNER" --limit 100 --format json \
  --jq ".projects[] | select(.title == \"$PROJECT_TITLE\") | .number" | head -n1)"

if [[ -z "$PROJECT_NUMBER" ]]; then
  PROJECT_NUMBER="$(gh project create --owner "$OWNER" --title "$PROJECT_TITLE" --format json --jq '.number')"
  printf 'project created: %s (#%s)\n' "$PROJECT_TITLE" "$PROJECT_NUMBER"
else
  printf 'project found: %s (#%s)\n' "$PROJECT_TITLE" "$PROJECT_NUMBER"
fi

gh project edit "$PROJECT_NUMBER" \
  --owner "$OWNER" \
  --description "CVortex value-driven roadmap. Native repository Milestones define M0-M6; custom fields track delivery, slice, target version and priority." \
  --visibility "$PROJECT_VISIBILITY" >/dev/null

if ! gh project link "$PROJECT_NUMBER" --owner "$OWNER" --repo "$REPO_NAME" >/dev/null 2>&1; then
  echo "info: project link already exists or GitHub reported a non-fatal link error; verify with gh project view." >&2
fi

ensure_field() {
  local name="$1" type="$2" options="${3:-}"
  local existing
  existing="$(gh project field-list "$PROJECT_NUMBER" --owner "$OWNER" --limit 100 --format json \
    --jq ".fields[] | select(.name == \"$name\") | .id" | head -n1)"

  if [[ -n "$existing" ]]; then
    printf 'project field exists: %s\n' "$name"
    return 0
  fi

  if [[ "$type" == "SINGLE_SELECT" ]]; then
    gh project field-create "$PROJECT_NUMBER" --owner "$OWNER" \
      --name "$name" --data-type SINGLE_SELECT --single-select-options "$options" >/dev/null
  else
    gh project field-create "$PROJECT_NUMBER" --owner "$OWNER" \
      --name "$name" --data-type "$type" >/dev/null
  fi
  printf 'project field created: %s\n' "$name"
}

ensure_field "Delivery" SINGLE_SELECT "Backlog,Ready,In progress,Review,Blocked,Done"
ensure_field "Slice" SINGLE_SELECT "None,M1.1,M1.2,M1.3,M1.4"
ensure_field "Target version" TEXT
ensure_field "Priority" SINGLE_SELECT "P0,P1,P2,P3"

echo
echo "GitHub roadmap synchronized."
echo "Repository: $REPO"
echo "Project: $PROJECT_TITLE (#$PROJECT_NUMBER)"
echo "Milestone source: .github/roadmap.yml"
echo "Note: GitHub Project views are configured interactively; recommended views are documented in docs/10-Operations/GitHub-Governance.md."
