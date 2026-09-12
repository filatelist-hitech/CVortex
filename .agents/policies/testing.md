# Testing Policy

## Purpose

Ensure changes are verifiable, regression-resistant and appropriate to their risk without wasting compute on redundant validation.

## Mandatory Rules

- Every implementation change requires appropriate validation.
- Prefer deterministic automated tests.
- Run the narrowest meaningful validation first.
- Expand to broader suites when risk, phase completion or merge criteria justify it.
- Test authorization boundaries for multi-user resources.
- Test failure paths, not only happy paths.
- LLM behavior should be evaluated through invariants and schemas rather than fragile exact-string assertions.
- Record validation actually performed in project state.

## Validation Efficiency

Do not rerun a successful check unless:

- relevant code/configuration changed after the check;
- a failed check requires confirmation after a fix;
- the task explicitly requires a final broader suite;
- merge/phase risk justifies broader validation.

Documentation-only changes do not require the full application test suite unless they affect generated, parsed, executable or automatically validated artifacts.

Prefer targeted test files/modules before repository-wide suites while iterating.

## Prohibited Behavior

- Claiming success without validation.
- Removing failing tests merely to make a build green.
- Relying exclusively on manual testing when deterministic automation is practical.
- Treating non-deterministic LLM text as an exact golden string unless specifically justified.
- Repeating full test suites after unrelated documentation-only edits.
- Running expensive broad validation repeatedly when a targeted check can establish the same fact.

## Completion Checks

- Relevant tests passed.
- Failure scenarios were considered.
- Authorization/security tests exist when relevant.
- Documentation reflects changed behavior.
- Validation result is recorded in project state.
- No successful validation was repeated without a concrete reason.
