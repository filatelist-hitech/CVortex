# Repository Task Specifications

Use this directory for durable bounded task specifications that would otherwise be pasted repeatedly into Codex/chat prompts.

## Why

Task specs stored in Git reduce repeated prompt tokens, preserve version history, and keep execution reproducible.

Interactive launch prompts should usually be short, for example:

```text
Execute `.agents/tasks/<task>.md`.
Follow `AGENTS.md` and current project state.
Use one agent, sequential execution and task-relevant context only.
Complete the bounded task, update required state/docs, then STOP.
```

Do not duplicate the full task specification in the launch prompt.

## Task Spec Rules

A task specification should define only what is needed for that bounded task:

- goal;
- current phase;
- scope/non-goals;
- authoritative inputs/source of truth;
- requirements/invariants;
- research requirement when applicable;
- security considerations;
- validation;
- documentation/state updates;
- binary completion criteria;
- STOP boundary.

Stable repository-wide rules belong in `AGENTS.md` or `.agents/policies/`, not repeated inside every task.

## Resource Defaults

Unless a task explicitly overrides them, `.agents/policies/resource-usage.md` applies:

- one agent;
- sequential work;
- lazy context loading;
- lowest-cost capable model/effort;
- no subagents unless justified;
- narrow validation first;
- concise final report;
- escalation only on concrete failure/ambiguity/risk.

## Lifecycle

Task specs may be:

- `draft` — not ready to execute;
- `ready` — bounded and executable;
- `completed` — execution finished;
- `superseded` — replaced by another task/spec.

Keep completed specs when they remain useful for traceability. Do not treat `.agents/tasks/` as a backlog dumping ground.
