#!/usr/bin/env bash
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

historical_task=".agents/tasks/phase-07-design-foundation.md"
output_file="$(mktemp)"
trap 'rm -f "$output_file"' EXIT

if ! bash scripts/check-agent-contract.sh review "$historical_task" >"$output_file" 2>&1; then
  cat "$output_file" >&2
  echo "agent-contract test failed: read-only historical review was blocked" >&2
  exit 1
fi

if bash scripts/check-agent-contract.sh implementation "$historical_task" >"$output_file" 2>&1; then
  echo "agent-contract test failed: implementation NEXT mismatch was not blocked" >&2
  exit 1
fi

grep -Fq "does not match NEXT" "$output_file"
echo "agent-contract: review bypasses historical NEXT mismatch; implementation remains gated"
