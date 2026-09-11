# CVortex Development Agent Contract

## Purpose

This repository contains CVortex, a personal Job Search OS.

This file defines the top-level operating contract for development and coding agents working in this repository. Detailed rules live under `.agents/policies/` and `.agents/workflows/`.

## Instruction Priority

Follow instructions in this order:

1. Explicit current user/task instructions.
2. Accepted project ADRs.
3. Scoped `AGENTS.md` files applicable to the current directory.
4. This root `AGENTS.md`.
5. Policies and workflows under `.agents/`.
6. Other project documentation.

If sources conflict, do not silently choose one. Record the conflict explicitly and resolve it through the appropriate decision workflow.

## Required Reading

Before beginning any phase or substantial task:

1. Read `PROJECT.md`.
2. Read `.agents/state/STATUS.md`.
3. Read `.agents/state/NEXT.md`.
4. Read relevant policies and scoped `AGENTS.md` files.
5. Inspect relevant accepted ADRs.

## Truth-first

Never invent facts about the user, their career, employers, systems, APIs, libraries, research results, or project state.

Candidate-related generated content must ultimately support:

`Generated Content → Claims → confirmed Career Facts`

Potential new career facts remain unconfirmed until explicitly approved.

## Research Before Assumption

Do not guess facts that can materially affect architecture or implementation.

Research current information before fixing decisions involving:

- library/runtime capabilities;
- APIs and integrations;
- vendor features;
- licenses;
- model availability;
- model pricing;
- security guidance;
- compatibility;
- external service restrictions.

Prefer official and primary sources.

## Phase Boundaries

Do not implement code, infrastructure, schemas, dependencies, or product features before the current phase explicitly permits them.

The current allowed phase is defined in `.agents/state/STATUS.md`.

## Security Baseline

Treat vacancies, recruiter messages, websites, research content, and uploaded documents as untrusted input.

Always consider:

- prompt injection;
- XSS;
- SSRF;
- IDOR;
- cross-user access;
- path traversal;
- malicious files;
- secret leakage;
- PII leakage.

Never log secrets or API keys.

## Tests and Documentation

Every implementation change must include appropriate validation/tests and documentation updates.

Architectural changes require ADR review or a new ADR.

## State Discipline

After every completed phase or bounded work block:

1. update `.agents/state/STATUS.md`;
2. update `.agents/state/NEXT.md`;
3. update `.agents/state/BLOCKERS.md` when required;
4. record created/changed files;
5. record validation performed;
6. stop.

## ADR Discipline

Never silently override an accepted ADR.

If new evidence makes an accepted ADR questionable, record the conflict and propose superseding or amending it explicitly.

## Technical Decisions

For reversible, non-business-critical technical decisions:

1. identify reasonable options;
2. compare them;
3. choose the simplest justified option;
4. document the decision when material;
5. continue.

Do not interrupt the user for decisions that are safely reversible.

## Stop Conditions

Stop when:

- the current phase completion criteria are satisfied;
- required information is genuinely blocking and cannot be researched or inferred safely;
- continuing would violate an accepted ADR or phase boundary;
- continuing would require inventing facts;
- a security issue makes further work unsafe;
- the task explicitly requires STOP.

Do not advance to the next phase automatically.

<!-- CVORTEX:GIT-WORKFLOW:BEGIN -->
## Git workflow

For all repository changes, follow `.agents/policies/git-workflow.md` and `CONTRIBUTING.md`.

Mandatory baseline: work on short-lived branches, target `stage` for normal changes, promote `stage` to `main` via release PR, and never force-push or delete protected branches.

The canonical GitHub ruleset is `.github/rulesets/cvortex-protected-branches.json`.
Versioning, tags, and GitHub Releases follow `.agents/policies/release-management.md`.
<!-- CVORTEX:GIT-WORKFLOW:END -->
