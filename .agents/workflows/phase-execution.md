# Phase Execution Workflow

Every project phase follows:

```text
READ MINIMUM CONTEXT
↓
INSPECT TARGET
↓
RESEARCH if needed
↓
DECIDE
↓
DOCUMENT
↓
IMPLEMENT if phase permits
↓
VALIDATE NARROWLY
↓
UPDATE DOCS
↓
UPDATE STATE
↓
STOP
```

## 1. READ MINIMUM CONTEXT

Always read:

- `PROJECT.md`;
- `.agents/state/STATUS.md`;
- current task spec under `.agents/tasks/` when one exists.

Then read only directly relevant scoped instructions, policies, accepted ADRs, docs and research.

Do not recursively load `.agents/`, `docs/`, `research/` or source trees by default. Use indexes, links and targeted search to locate context.

Follow `.agents/policies/resource-usage.md`.

## 2. INSPECT TARGET

Inspect the current repository area affected by the task before proposing or changing anything.

Do not assume files, features or decisions exist. Avoid broad repository scans when a targeted path/search is sufficient.

## 3. RESEARCH

Research only where current external knowledge materially affects the decision.

Follow `.agents/policies/research.md` and `.agents/workflows/research.md`.

Work sequentially by default. Stop collecting redundant evidence once confidence is sufficient unless the topic is contested, high-risk or requires broad coverage.

## 4. DECIDE

For reversible technical decisions:

- identify reasonable options;
- compare them briefly;
- choose the simplest justified option;
- document material choices.

Escalate model/effort/context only when the simpler pass is insufficient for correctness.

## 5. DOCUMENT

Record architecture decisions before implementation when appropriate.

Keep task-local notes concise. Do not duplicate stable project rules into new documents.

## 6. IMPLEMENT

Implement only when the active phase explicitly allows implementation.

Use one agent and sequential execution by default. Subagents require a concrete justification under `.agents/policies/resource-usage.md`.

## 7. VALIDATE NARROWLY

Run the narrowest meaningful deterministic checks/tests first.

Do not repeat successful validation unless relevant files changed, a failure requires confirmation, or a required broader final check remains.

Never claim validation that was not actually performed.

## 8. UPDATE DOCS

Update only documentation made stale by the work.

Do not perform unrelated documentation rewrites or formatting sweeps.

## 9. UPDATE STATE

Update:

- `STATUS.md` after every completed bounded block;
- `NEXT.md` only if the next intended task/phase changed;
- `BLOCKERS.md` only if blockers changed;
- changed files and validation actually performed.

## 10. STOP

Report concisely:

- result;
- files changed;
- validation;
- blockers/limitations;
- next bounded task/phase.

Do not automatically begin the next phase.
