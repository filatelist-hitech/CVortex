---
title:
status: draft
owner:
created:
updated:
tags:
  - task
related:
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
- [ ] ...

## STOP

Stop when this bounded outcome is complete. Do not begin the next slice or speculative refactor.

## Suggested Launch Prompt

```text
Execute `.agents/tasks/<this-task>.md`.
Follow `AGENTS.md` and current project state.
Use one agent, sequential execution and task-relevant context only.
Complete the bounded task, update required docs/state, then STOP.
```

## Result

Fill after execution if useful for traceability. Keep concise.
