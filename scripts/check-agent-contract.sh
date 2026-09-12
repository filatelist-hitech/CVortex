#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  scripts/check-agent-contract.sh <mode> [task-spec] [--write] [--user-override]

Modes:
  implementation | review | research | architecture | docs | git-release
USAGE
}

MODE="${1:-}"
if [[ "$MODE" == "-h" || "$MODE" == "--help" ]]; then
  usage
  exit 0
fi
[[ -n "$MODE" ]] || { usage >&2; exit 2; }
shift

TASK_SPEC=""
WRITE=false
USER_OVERRIDE=false

while (($#)); do
  case "$1" in
    --write) WRITE=true ;;
    --user-override) USER_OVERRIDE=true ;;
    -h|--help) usage; exit 0 ;;
    --*) echo "error: unknown option: $1" >&2; exit 2 ;;
    *)
      if [[ -n "$TASK_SPEC" ]]; then
        echo "error: only one task spec may be provided" >&2
        exit 2
      fi
      TASK_SPEC="$1"
      ;;
  esac
  shift
done

case "$MODE" in
  implementation) WORKFLOW=".agents/workflows/implementation.md" ;;
  review) WORKFLOW=".agents/workflows/review.md" ;;
  research) WORKFLOW=".agents/workflows/research.md" ;;
  architecture) WORKFLOW=".agents/workflows/architecture-decision.md" ;;
  docs) WORKFLOW=".agents/policies/documentation.md" ;;
  git-release) WORKFLOW=".agents/policies/git-workflow.md" ;;
  *) echo "error: unsupported mode: $MODE" >&2; usage >&2; exit 2 ;;
esac

ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || {
  echo "error: not inside a Git repository" >&2
  exit 1
}
cd "$ROOT"

[[ -f "$WORKFLOW" ]] || {
  echo "error: canonical workflow missing for mode '$MODE': $WORKFLOW" >&2
  exit 1
}

if [[ "$MODE" == "review" && "$WRITE" == true ]]; then
  echo "error: repository-native review is read-only; --write is not allowed" >&2
  exit 1
fi

if [[ -n "$TASK_SPEC" ]]; then
  [[ -f "$TASK_SPEC" ]] || {
    echo "error: task spec not found: $TASK_SPEC" >&2
    exit 1
  }

  TASK_ID="$(basename "$TASK_SPEC")"
  TASK_ID="${TASK_ID%.md}"

  if [[ -f .agents/state/NEXT.md ]]; then
    NEXT_ID="$(tr -d '[:space:]' < .agents/state/NEXT.md)"
    if [[ -n "$NEXT_ID" && "$TASK_ID" != "$NEXT_ID" && "$USER_OVERRIDE" != true && "$MODE" != "review" ]]; then
      cat >&2 <<EOF2
error: task '$TASK_ID' does not match NEXT '$NEXT_ID'.
Use --user-override only when the current explicit user instruction authorizes this bounded override.
EOF2
      exit 1
    fi
  fi
fi

if [[ "$WRITE" == true ]]; then
  BRANCH="$(git branch --show-current)"
  [[ -n "$BRANCH" ]] || {
    echo "error: detached HEAD is not allowed for a write task" >&2
    exit 1
  }

  case "$BRANCH" in
    main|stage)
      echo "error: write task cannot modify protected branch '$BRANCH'; create/switch to a bounded short-lived branch first" >&2
      exit 1
      ;;
    feature/*|fix/*|chore/*|docs/*|ci/*|hotfix/*) ;;
    *)
      echo "error: write task must use an approved short-lived branch prefix; current branch: $BRANCH" >&2
      exit 1
      ;;
  esac

  if [[ "$BRANCH" == hotfix/* ]]; then
    if git show-ref --verify --quiet refs/heads/main; then
      git merge-base --is-ancestor main HEAD || {
        echo "error: hotfix branch is not based on local main" >&2
        exit 1
      }
    fi
  elif git show-ref --verify --quiet refs/heads/stage; then
    git merge-base --is-ancestor stage HEAD || {
      echo "error: normal write branch is not based on local stage" >&2
      exit 1
    }
  fi
fi

printf 'agent-contract: mode=%s workflow=%s' "$MODE" "$WORKFLOW"
[[ -n "$TASK_SPEC" ]] && printf ' task=%s' "$TASK_SPEC"
printf ' write=%s user_override=%s\n' "$WRITE" "$USER_OVERRIDE"
