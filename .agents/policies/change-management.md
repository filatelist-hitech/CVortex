# Change Management Policy

## Purpose

Make changes traceable and prevent silent drift in architecture, state and project direction.

## Mandatory Rules

For each bounded phase or work block:

1. inspect current state;
2. identify affected decisions and documentation;
3. implement only what the phase permits;
4. validate;
5. update documentation;
6. update project state;
7. stop.

If new evidence conflicts with an accepted ADR, record the conflict explicitly.

For reversible technical choices, select a reasonable option without unnecessarily blocking on user confirmation.

## Prohibited Behavior

- Silent scope expansion.
- Automatically moving into the next phase.
- Silently replacing accepted ADR decisions.
- Mixing unrelated architectural changes into a bounded task.
- Using BLOCKERS.md as a backlog.

## Completion Checks

- Scope remained within the active phase.
- Changed files are recorded.
- Validation is recorded.
- Documentation is current.
- STATUS, NEXT and BLOCKERS are current.
- Work stops at the declared phase boundary.
