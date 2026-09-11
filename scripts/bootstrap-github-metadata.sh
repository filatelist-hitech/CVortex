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

# Change type
create_label 'type:bug'       'D73A4A' 'Confirmed defect or regression'
create_label 'type:feature'   '1F6FEB' 'New product capability or behavior'
create_label 'type:docs'      '0075CA' 'Documentation-only change'
create_label 'type:research'  '8250DF' 'Research, evidence gathering, or validation'
create_label 'type:refactor'  'FBCA04' 'Internal refactor without intended behavior change'
create_label 'type:test'      '0E8A16' 'Test or evaluation work'
create_label 'type:chore'     'C5DEF5' 'Repository, tooling, or maintenance work'
create_label 'type:security'  'B60205' 'Security-sensitive change or finding'

# Area
create_label 'area:backend'   '5319E7' 'Laravel backend or API'
create_label 'area:frontend'  '0969DA' 'Next.js, React, PWA, or browser UI'
create_label 'area:ai'        '8A2BE2' 'LLM architecture, skills, agents, prompts, or evals'
create_label 'area:data'      '0052CC' 'PostgreSQL, data model, provenance, or migrations'
create_label 'area:infra'     '006B75' 'Docker, Nginx, Redis, CI/CD, deployment, or operations'
create_label 'area:design'    'D4C5F9' 'Figma, design tokens, accessibility, or UI system'
create_label 'area:docs'      '0E8A16' 'Project documentation and Obsidian vault'

# Priority
create_label 'priority:p0'    'B60205' 'Critical; blocks safe progress or release'
create_label 'priority:p1'    'D93F0B' 'High priority'
create_label 'priority:p2'    'FBCA04' 'Normal priority'
create_label 'priority:p3'    'C2E0C6' 'Low priority or backlog'

# Workflow
create_label 'status:blocked'        'B60205' 'Cannot proceed because of an explicit blocker'
create_label 'status:needs-decision' 'D876E3' 'Requires an explicit product or architecture decision'
create_label 'status:needs-research' '8250DF' 'Requires current evidence before implementation'
create_label 'status:ready'          '0E8A16' 'Requirements are sufficiently clear to execute'

# Release semantics
create_label 'breaking-change' 'B60205' 'Backward-incompatible behavior or contract change'
create_label 'skip-changelog'  'EDEDED' 'Exclude from generated release notes'

echo
printf 'GitHub metadata synchronized for %s\n' "$REPO"
printf 'Canonical catalog: .github/labels.yml\n'
