# Implementation Workflow

## Preconditions

Implementation is allowed only when:

- the current phase permits it;
- requirements are sufficiently understood;
- relevant ADRs have been read;
- material architecture decisions are documented.

## 0. Git preflight

Run this before modifying any repository file.

1. Read `.agents/policies/git-workflow.md`.
2. Identify the current branch and the authorized task.
3. When a task spec exists, run:

```bash
bash scripts/check-agent-contract.sh implementation .agents/tasks/<task>.md --write
```

4. If the current explicit user instruction authorizes a bounded task different from `.agents/state/NEXT.md`, run the same preflight with `--user-override` and record that override in the final report.
5. For normal development, the base and PR target are `stage`.
6. If currently on `main` or `stage`, do not modify files there. Synchronize `stage`, create the appropriate bounded short-lived branch automatically, switch to it, then continue.
7. If already on a short-lived branch unrelated to the authorized task, stop before writes and report the mismatch unless the current explicit user instruction authorizes that branch.
8. Do not ask the user to create a branch manually when the branch name can be derived safely from the task.

The user does not need to repeat Git branch instructions in every implementation prompt.

## Workflow

1. Inspect existing implementation.
2. Define the smallest coherent change.
3. Check authorization and security implications.
4. Implement deterministic logic where practical.
5. Add or update tests.
6. Handle failures explicitly.
7. Update documentation.
8. Run validation.
9. Record changed files and validation in project state when the task requires state changes.
10. Stop at the task boundary.

## Rule

Do not create code merely to fill an anticipated future structure.

Do not bypass Git preflight because an API, connector or local tool can technically write to a protected branch.
