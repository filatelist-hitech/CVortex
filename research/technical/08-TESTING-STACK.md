---
status: research-complete
date: 2026-09-12
phase: 03-technical-research
owner: CVortex
architecture_decision: none
---

# Testing Stack Research

## Goal

Identify current test/tooling candidates for backend, frontend, E2E, static analysis and LLM invariants without fixing dependencies prematurely.

## Backend

### Pest 5

- **[E1]** Pest 5 was released 2026-07-28.
- Requires PHP 8.4+ and is built on PHPUnit 13.
- Candidate advantage: concise developer ergonomics for feature/authorization/invariant tests.

### PHPUnit 13

- **[E2]** PHPUnit 13 is current and requires PHP 8.4+.
- It remains the underlying standard testing engine and can be used directly even if Pest is selected.

### Static analysis

PHPStan + Larastan remain the leading candidate combination for Laravel static analysis. Exact versions should be pinned in Phase 08 after Laravel/PHP selection and compatibility validation.

## Frontend unit/component

- **[E3]** Vitest 5 was released 2026-09-03.
- **[E4]** React Testing Library promotes tests around user-observable behavior rather than component internals.
- Candidate: Vitest 5 + React Testing Library for component/hook/unit behavior.

## E2E

- **[E5]** Playwright 1.63 is current at research date.
- Candidate: Playwright for browser E2E and security-sensitive auth/authorization workflow tests.

## CVortex-specific test layers

The owner-required layers remain appropriate:

1. unit;
2. integration;
3. API/HTTP;
4. frontend component;
5. E2E;
6. LLM evaluation/invariant tests;
7. security/authorization tests;
8. document rendering regression tests.

## LLM test principle

Do not assert exact prose. Assert invariants, structured schemas and provenance constraints, including:

- no unconfirmed candidate fact introduced;
- Content -> Claim -> CONFIRMED Fact traceability;
- salary/location/work-format preserved unless explicitly changed;
- employer consistency rules;
- language/output schema correctness;
- deterministic validators reject bad model output;
- cost/latency/quality tracked across prompt/model changes.

## Document tests

Future document tests should include:

- DOCX opens and contains expected semantic content;
- PDF conversion exits successfully;
- generated files are non-empty and within size sanity bounds;
- page count/layout snapshots or extracted structural checks for known fixtures;
- fonts/line breaks/table overflow regression fixtures;
- deterministic content hash where appropriate, but avoid byte-for-byte assertions when office metadata makes binaries variable.

## Candidate recommendation, not ADR

- Backend: Pest 5 on PHPUnit 13, with direct PHPUnit available when useful.
- Static analysis: PHPStan/Larastan.
- Frontend: Vitest 5 + React Testing Library.
- E2E: Playwright 1.63 current patch.
- Keep LLM evals and document regressions as separate first-class suites rather than hiding them inside ordinary unit tests.

## Confidence

High for test categories; medium-high for exact backend style choice (Pest vs direct PHPUnit) until team ergonomics are validated.

## Citations

- **[E1] Pest 5 release / docs**, accessed 2026-09-12: https://pestphp.com/
- **[E2] PHPUnit 13 documentation**, accessed 2026-09-12: https://docs.phpunit.de/en/13.0/
- **[E3] Vitest 5 announcement**, 2026-09-03: https://vitest.dev/blog/vitest-5
- **[E4] React Testing Library**, accessed 2026-09-12: https://testing-library.com/docs/react-testing-library/intro/
- **[E5] Playwright release notes**, accessed 2026-09-12: https://playwright.dev/docs/release-notes
- **[E6] PHPStan**, accessed 2026-09-12: https://phpstan.org/
- **[E7] Larastan**, accessed 2026-09-12: https://github.com/larastan/larastan
