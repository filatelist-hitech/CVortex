# Review Workflow

## Purpose

Perform an independent, bounded review without repeating the implementation task or consuming unnecessary context/compute.

## Execution gate

This workflow is repository-native by default.

Unless the current explicit user/task instruction opts in to a named external framework or skill:

- use exactly one reviewer agent;
- work sequentially;
- do not spawn subagents or teams;
- do not parallelize review or research;
- do not activate OMX, code-review orchestration, security-review orchestration, or an equivalent external review workflow;
- do not escalate model capability or reasoning automatically.

Words such as `review`, `audit`, `inspect`, `verify`, `check`, `security review`, or similar are not permission to activate external orchestration.

If a specific unresolved finding genuinely needs stronger reasoning, broader context or parallel review, report:

`ESCALATION RECOMMENDED`

Include the exact unresolved question, evidence and why the current review mode is insufficient. Do not perform the escalation automatically.

When a task spec is available, the reviewer may run:

```bash
bash scripts/check-agent-contract.sh review .agents/tasks/<task>.md
```

Review mode is read-only. Do not pass `--write`.

## Default Review Mode

- Review only; do not fix findings unless the task explicitly says to fix.
- Read `PROJECT.md`, `.agents/state/STATUS.md`, the task spec (if present), the diff/changed files, and only context needed to judge those changes.
- Do not recursively reread the repository.
- Reuse credible, still-applicable validation evidence.
- Re-run tests/research only when a finding depends on revalidation, evidence is missing/stale, or a high-risk requirement needs independent confirmation.
- Keep the report concise and actionable.

Follow `.agents/policies/resource-usage.md`.

## Review Order

1. Phase/scope compliance.
2. Truth-first compliance when relevant.
3. Security and authorization.
4. Correctness.
5. Architecture/ADR consistency.
6. Tests and validation claims.
7. Error handling/observability when relevant.
8. Documentation and state consistency.
9. Backward compatibility when relevant.
10. Resource-discipline violations that materially inflate future agent cost.

## Research Review

For research-heavy changes, prioritize revalidation of claims that can change architecture or security, such as:

- official/public API availability;
- authentication/OAuth applicability;
- ToS or access restrictions;
- rate limits;
- current vendor/model capabilities;
- high-confidence hiring/security claims.

Do not redo the entire research phase. Recheck a representative or risk-based subset unless evidence quality is broadly suspect.

## Findings

Classify findings as:

- `CRITICAL` — unusable/unsafe foundation;
- `HIGH` — materially affects architecture, security or correctness;
- `MEDIUM` — should be fixed before completion but is locally bounded;
- `LOW` — minor quality/consistency issue;
- `INFO` — non-blocking observation.

For each finding record only:

- severity and title;
- affected file/location;
- problem;
- impact;
- required correction/evidence.

Do not generate praise filler.

## Verdict

Use one of:

- `PASS`;
- `PASS WITH MINOR FINDINGS`;
- `CHANGES REQUIRED`;
- `BLOCKED`.

## Output

Report only:

- verdict;
- finding counts by severity;
- actionable findings;
- validation/research claims independently rechecked;
- requirements that remain unverified;
- remaining uncertainty;
- `ESCALATION RECOMMENDED` only when justified.

Do not approve work that claims tests or validation that were not actually performed.

After the report, STOP. Do not transition into implementation/fix mode automatically.
