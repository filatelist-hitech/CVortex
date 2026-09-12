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

## Goal

...

## Current Phase

...

## Scope

### Included

- ...

### Excluded / Non-goals

- ...

## Source of Truth / Inputs

- ...

## Requirements / Invariants

- ...

## Research

- `required: yes|no`
- If yes, state the current/freshness-sensitive questions only.
- Follow `.agents/workflows/research.md`.

## Security Considerations

- ...

## Resource Budget

Use `.agents/policies/resource-usage.md` defaults unless this task needs an explicit override.

Expected default:

- one agent;
- sequential execution;
- task-relevant context only;
- lowest-cost capable model/effort;
- no subagents without justification;
- targeted validation before broad suites;
- concise completion report.

Any escalation should state the concrete reason.

## Validation

- ...

## Documentation / State Updates

- ...

## Completion Criteria

- [ ] ...
- [ ] ...

## STOP

Do not begin the next phase or adjacent feature automatically.

## Suggested Launch Prompt

```text
Execute `.agents/tasks/<this-task>.md`.
Follow `AGENTS.md` and current project state.
Use one agent, sequential execution and task-relevant context only.
Complete the bounded task, update required docs/state, then STOP.
```

## Result

Fill after execution if useful for traceability. Keep concise.
