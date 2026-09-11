# Testing Policy

## Purpose

Ensure changes are verifiable, regression-resistant and appropriate to their risk.

## Mandatory Rules

- Every implementation change requires appropriate validation.
- Prefer deterministic automated tests.
- Test authorization boundaries for multi-user resources.
- Test failure paths, not only happy paths.
- LLM behavior should be evaluated through invariants and schemas rather than fragile exact-string assertions.
- Record validation performed in project state.

## Prohibited Behavior

- Claiming success without validation.
- Removing failing tests merely to make a build green.
- Relying exclusively on manual testing when deterministic automation is practical.
- Treating non-deterministic LLM text as an exact golden string unless specifically justified.

## Completion Checks

- Relevant tests passed.
- Failure scenarios were considered.
- Authorization/security tests exist when relevant.
- Documentation reflects changed behavior.
- Validation result is recorded in project state.
