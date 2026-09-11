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
LABELS_FILE="$ROOT/.github/labels.yml"

if [[ ! -f "$LABELS_FILE" ]]; then
  printf 'error: canonical labels catalog is missing: %s\n' "$LABELS_FILE" >&2
  exit 1
fi

create_label() {
  local name="$1"
  local color="$2"
  local description="$3"

  gh label create "$name" \
    --repo "$REPO" \
    --color "$color" \
    --description "$description" \
    --force >/dev/null

  printf 'label synced: %s\n' "$name"
}

count=0
while IFS=$'\t' read -r name color description; do
  [[ -n "$name" ]] || continue
  create_label "$name" "$color" "$description"
  count=$((count + 1))
done < <(
  awk '
    /^[[:space:]]*-[[:space:]]*name:[[:space:]]*/ {
      name=$0
      sub(/^[[:space:]]*-[[:space:]]*name:[[:space:]]*/, "", name)
      next
    }
    /^[[:space:]]*color:[[:space:]]*/ {
      color=$0
      sub(/^[[:space:]]*color:[[:space:]]*/, "", color)
      next
    }
    /^[[:space:]]*description:[[:space:]]*/ {
      description=$0
      sub(/^[[:space:]]*description:[[:space:]]*/, "", description)
      if (name != "" && color != "") {
        printf "%s\t%s\t%s\n", name, color, description
      }
      name=""
      color=""
      description=""
    }
  ' "$LABELS_FILE"
)

if [[ "$count" -eq 0 ]]; then
  echo 'error: no labels parsed from .github/labels.yml' >&2
  exit 1
fi

echo
printf 'GitHub metadata synchronized for %s\n' "$REPO"
printf 'Labels synchronized: %d\n' "$count"
printf 'Canonical catalog: .github/labels.yml\n'
