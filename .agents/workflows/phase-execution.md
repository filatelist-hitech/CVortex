# Phase Execution Workflow

Every project phase follows:

```text
READ
↓
INSPECT
↓
RESEARCH if needed
↓
DECIDE
↓
DOCUMENT
↓
IMPLEMENT if phase permits
↓
VALIDATE
↓
UPDATE DOCS
↓
UPDATE STATE
↓
STOP
```

## 1. READ

Read:

- `AGENTS.md`
- `PROJECT.md`
- `.agents/state/STATUS.md`
- `.agents/state/NEXT.md`
- relevant scoped instructions
- relevant accepted ADRs

## 2. INSPECT

Inspect the current repository before proposing or changing anything.

Do not assume files, features or decisions exist.

## 3. RESEARCH

Research only where current external knowledge materially affects the decision.

Follow `.agents/policies/research.md`.

## 4. DECIDE

For reversible technical decisions:

- identify options;
- compare them;
- choose the simplest justified option;
- document material choices.

## 5. DOCUMENT

Record architecture decisions before implementation when appropriate.

## 6. IMPLEMENT

Implement only when the active phase explicitly allows implementation.

## 7. VALIDATE

Run appropriate deterministic checks and tests.

Never claim validation that was not actually performed.

## 8. UPDATE DOCS

Update documentation affected by the work.

## 9. UPDATE STATE

Update:

- STATUS.md
- NEXT.md
- BLOCKERS.md when required
- changed files
- validation performed

## 10. STOP

Do not automatically begin the next phase.
