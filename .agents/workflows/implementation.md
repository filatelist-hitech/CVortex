# Implementation Workflow

## Preconditions

Implementation is allowed only when:

- the current phase permits it;
- requirements are sufficiently understood;
- relevant ADRs have been read;
- material architecture decisions are documented.

## Workflow

1. Inspect existing implementation.
2. Define the smallest coherent change.
3. Check authorization and security implications.
4. Implement deterministic logic where practical.
5. Add or update tests.
6. Handle failures explicitly.
7. Update documentation.
8. Run validation.
9. Record changed files and validation in project state.
10. Stop at the task boundary.

## Rule

Do not create code merely to fill an anticipated future structure.
