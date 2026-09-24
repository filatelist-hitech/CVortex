#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  bash scripts/check-pr-contract.sh <pr-number>
  bash scripts/check-pr-contract.sh --api-url <public-github-pr-api-url>

Validates the CVortex pull-request metadata contract against the canonical
repository taxonomy and PR template.

Local mode requires authenticated gh. CI may use --api-url for this public
repository so trusted-base validation does not need a GitHub token.
USAGE
}

MODE="local"
PR_NUMBER=""
PR_API_URL=""

case "${1:-}" in
  --api-url)
    MODE="public-api"
    PR_API_URL="${2:-}"
    [[ "$PR_API_URL" =~ ^https://api\.github\.com/repos/filatelist-hitech/CVortex/pulls/[0-9]+$ ]] || {
      echo "error: invalid CVortex PR API URL" >&2
      exit 2
    }
    ;;
  "")
    usage >&2
    exit 2
    ;;
  *)
    PR_NUMBER="$1"
    [[ "$PR_NUMBER" =~ ^[0-9]+$ ]] || {
      usage >&2
      exit 2
    }
    ;;
esac

for command_name in jq awk grep; do
  command -v "$command_name" >/dev/null 2>&1 || {
    echo "error: required command is missing: $command_name" >&2
    exit 1
  }
done

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
cd "$ROOT"

REPO="${CVORTEX_REPO:-filatelist-hitech/CVortex}"
LABELS_FILE="$ROOT/.github/labels.yml"

[[ -f "$LABELS_FILE" ]] || {
  echo "error: canonical labels catalog is missing: $LABELS_FILE" >&2
  exit 1
}

if [[ "$MODE" == "local" ]]; then
  command -v gh >/dev/null 2>&1 || {
    echo "error: GitHub CLI (gh) is required" >&2
    exit 1
  }
  gh auth status >/dev/null 2>&1 || {
    echo "error: GitHub CLI is not authenticated" >&2
    exit 1
  }
  pr_json="$(gh api "repos/$REPO/pulls/$PR_NUMBER")"
  comments_json="$(gh api "repos/$REPO/issues/$PR_NUMBER/comments?per_page=100")"
else
  command -v curl >/dev/null 2>&1 || {
    echo "error: curl is required for --api-url mode" >&2
    exit 1
  }
  pr_json="$(curl --fail --silent --show-error \
    -H 'Accept: application/vnd.github+json' \
    "$PR_API_URL")"
  comments_url="$(jq -r '.comments_url' <<<"$pr_json")"
  comments_json="$(curl --fail --silent --show-error \
    -H 'Accept: application/vnd.github+json' \
    "${comments_url}?per_page=100")"
  PR_NUMBER="$(jq -r '.number' <<<"$pr_json")"
fi

base="$(jq -r '.base.ref' <<<"$pr_json")"
head="$(jq -r '.head.ref' <<<"$pr_json")"
body="$(jq -r '.body // ""' <<<"$pr_json")"
milestone="$(jq -r '.milestone.title // ""' <<<"$pr_json")"
mapfile -t labels < <(jq -r '.labels[].name' <<<"$pr_json")
mapfile -t assignees < <(jq -r '.assignees[].login' <<<"$pr_json")
mapfile -t comments < <(jq -r '.[].body // ""' <<<"$comments_json")

declare -A canonical_labels=()
while IFS= read -r label; do
  [[ -n "$label" ]] || continue
  canonical_labels["$label"]=1
done < <(
  awk '
    /^[[:space:]]*-[[:space:]]*name:[[:space:]]*/ {
      name=$0
      sub(/^[[:space:]]*-[[:space:]]*name:[[:space:]]*/, "", name)
      print name
    }
  ' "$LABELS_FILE"
)

has_label() {
  local wanted="$1"
  local label
  for label in "${labels[@]}"; do
    [[ "$label" == "$wanted" ]] && return 0
  done
  return 1
}

count_prefix() {
  local prefix="$1"
  local count=0
  local label
  for label in "${labels[@]}"; do
    [[ "$label" == "$prefix"* ]] && count=$((count + 1))
  done
  printf '%d\n' "$count"
}

fail() {
  echo "error: $*" >&2
  exit 1
}

is_known_milestone() {
  case "$1" in
    "M0 · Runnable Core"|\
    "M1 · First Value"|\
    "M2 · Real Application Package"|\
    "M3 · Imports & Integrations"|\
    "M4 · Employer Journey"|\
    "M5 · Outcomes & Analytics"|\
    "M6 · Distribution & Hardening") return 0 ;;
    *) return 1 ;;
  esac
}

for label in "${labels[@]}"; do
  [[ -n "${canonical_labels[$label]:-}" ]] || fail "non-canonical label on PR: $label"
done

[[ "${#assignees[@]}" -ge 1 ]] || fail "PR requires at least one assignee"
[[ "$(count_prefix 'type:')" -ge 1 ]] || fail "PR requires at least one type:* label"
[[ "$(count_prefix 'area:')" -ge 1 ]] || fail "PR requires at least one area:* label"
[[ "$(count_prefix 'priority:')" -eq 1 ]] || fail "PR requires exactly one priority:* label"
[[ "$(count_prefix 'status:')" -eq 1 ]] || fail "PR requires exactly one status:* label"

required_headings=(
  "## Summary"
  "## Roadmap / release metadata"
  "## Related work"
  "## Change type"
  "## Scope"
  "## Truth / provenance"
  "## Security / authorization"
  "## Validation"
  "## Release notes"
  "## Notes / risks"
)
for heading in "${required_headings[@]}"; do
  grep -Fq "$heading" <<<"$body" || fail "PR body is missing template heading: $heading"
done

skip_changelog=0
release_notes_checked=0
has_label "skip-changelog" && skip_changelog=1
if grep -Eiq '^[[:space:]]*-[[:space:]]*\[[xX]\][[:space:]]+This change should appear in (product[[:space:]]+)?release notes' <<<"$body"; then
  release_notes_checked=1
fi
if [[ $((skip_changelog + release_notes_checked)) -ne 1 ]]; then
  fail "choose exactly one release-note path: checked release-notes checkbox or skip-changelog label"
fi

if [[ "$base" == "stage" ]]; then
  [[ "$head" =~ ^(feature|fix|chore|docs|ci)/[a-z0-9][a-z0-9._-]*$ ]] || \
    fail "stage PR must come from a bounded feature/fix/chore/docs/ci branch: $head"

  if [[ -z "$milestone" ]]; then
    has_label "roadmap:unversioned" || fail "stage PR without milestone requires roadmap:unversioned"
  else
    is_known_milestone "$milestone" || fail "unknown milestone: $milestone"
    ! has_label "roadmap:unversioned" || fail "milestone-bound PR must not also use roadmap:unversioned"
  fi

  slice_count=0
  for slice in slice:m1.1-access slice:m1.2-career slice:m1.3-vacancy slice:m1.4-application; do
    has_label "$slice" && slice_count=$((slice_count + 1))
  done
  cross_cutting=0
  has_label "roadmap:cross-cutting" && cross_cutting=1

  if [[ "$milestone" == "M1 · First Value" ]]; then
    [[ $((slice_count + cross_cutting)) -eq 1 ]] || \
      fail "M1 PR requires exactly one slice:m1.* label or roadmap:cross-cutting"
  else
    [[ "$slice_count" -eq 0 ]] || fail "M1 slice labels may only be used with the M1 milestone"
    [[ "$cross_cutting" -eq 0 ]] || fail "roadmap:cross-cutting may only be used inside the M1 milestone"
  fi
elif [[ "$base" == "main" ]]; then
  if [[ "$head" == "stage" ]]; then
    has_label "release:promotion" || fail "stage → main requires release:promotion"
    ! has_label "release:hotfix" || fail "stage → main must not use release:hotfix"
  elif [[ "$head" == hotfix/* ]]; then
    has_label "release:hotfix" || fail "hotfix/* → main requires release:hotfix"
    ! has_label "release:promotion" || fail "hotfix/* → main must not use release:promotion"
  else
    fail "main accepts only stage or hotfix/* sources"
  fi
else
  fail "PR contract is defined only for stage/main targets; got: $base"
fi

metadata_checkpoint=0
governance_checkpoint=0
for comment in "${comments[@]}"; do
  grep -Fq '## PR metadata / review checkpoint' <<<"$comment" && metadata_checkpoint=1
  grep -Fq '## Governance / validation checkpoint' <<<"$comment" && governance_checkpoint=1
done
[[ "$metadata_checkpoint" -eq 1 ]] || fail "missing PR metadata / review checkpoint comment"
[[ "$governance_checkpoint" -eq 1 ]] || fail "missing Governance / validation checkpoint comment"

echo "PR contract valid for #$PR_NUMBER: $head → $base"
