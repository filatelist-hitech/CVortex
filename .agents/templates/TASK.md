---
title:
status: draft
owner:
created:
updated:
tags:
  - task
related:
execution:
  workflow: implementation
  orchestration: native
  agents: 1
  parallelism: false
git:
  write: true
  base: stage
  target: stage
  branch: auto
---

# Task

## Observable Outcome

Describe one system or user-visible state that must exist after this task. Prefer one vertical slice over a whole subsystem.

## Milestone / Slice

...

## Goal

...

## Scope

### Included

- ...

### Excluded / Non-goals

- ...

## Source of Truth / Inputs

- ...

Reference repository-wide policies/ADRs instead of copying them wholesale.

## Execution Contract

Frontmatter is machine-readable task routing metadata.

Defaults for implementation tasks:

- `execution.workflow: implementation`;
- `execution.orchestration: native`;
- `execution.agents: 1`;
- `execution.parallelism: false`;
- `git.write: true`;
- `git.base: stage`;
- `git.target: stage`;
- `git.branch: auto`.

Change these only when the task genuinely requires another repository-native workflow. Review tasks normally use `workflow: review` and `git.write: false`.

`orchestration: native` means external orchestration/framework skills are not authorized by default. If a task intentionally requires one, name it explicitly in the task and justify the resource cost.

## Requirements / Invariants

- ...

## Research

- `required: yes|no`
- If yes, state only freshness-sensitive questions that block this task.
- Follow `.agents/workflows/research.md`.

## Security Considerations

- ...

## Resource Budget

Use `.agents/policies/resource-usage.md` defaults unless an explicit override is justified:

- one agent;
- sequential execution;
- task-relevant context only;
- lowest-cost capable model/effort;
- no subagents without correctness justification;
- narrow validation before broad suites;
- concise final report.

## Validation

List actual runnable checks that prove the observable outcome.

## Documentation / State Updates

- ...

## Completion Criteria

Use binary observable checks.

- [ ] ...

## STOP

Stop when this bounded outcome is complete. Do not begin the next slice or speculative refactor.

## Suggested Launch Prompt

```text
Execute `.agents/tasks/<this-task>.md`.
Follow the repository task router and the workflow declared by the task.
Use repository-native execution unless this task explicitly opts into another named framework.
Complete the bounded task, update required docs/state, then STOP.
```

For review tasks use:

```text
Review `.agents/tasks/<this-task>.md`.
Follow the repository task router and `.agents/workflows/review.md`.
Repository-native review only.
Do not modify files.
STOP after the verdict.
```

## Result

Fill after execution if useful for traceability. Keep concise.
