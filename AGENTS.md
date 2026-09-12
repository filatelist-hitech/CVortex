# CVortex Development Agent Contract

## Purpose

CVortex is a personal Job Search OS. This file is the top-level operating contract for development and coding agents. Detailed rules live under `.agents/policies/` and `.agents/workflows/`.

## Instruction Priority

Follow instructions in this order:

1. Explicit current user/task instructions.
2. Accepted project ADRs.
3. Applicable scoped `AGENTS.md` files.
4. This root `AGENTS.md`.
5. Relevant policies/workflows under `.agents/`.
6. Other project documentation.

If sources conflict, do not silently choose one. Record the conflict and resolve it through the appropriate decision workflow.

## Minimal Required Reading

Before a phase or substantial task:

1. Read `PROJECT.md`.
2. Read `.agents/state/STATUS.md`.
3. Read `.agents/state/NEXT.md`.
4. Read `.agents/state/BLOCKERS.md`.
5. Read the current task specification when one exists under `.agents/tasks/`.
6. Read only scoped `AGENTS.md`, policies, ADRs and documentation directly relevant to the task.

Do **not** recursively load `.agents/`, `docs/`, or `research/` by default. Use indexes, links, filenames and targeted search to locate the smallest sufficient context. Read additional material only when it materially affects correctness.

## Mandatory Task Routing

Before substantive work, classify the request as exactly one primary execution mode and load its canonical repository workflow before acting:

| Mode | Canonical route |
| --- | --- |
| `IMPLEMENT` | `.agents/workflows/implementation.md` + `.agents/policies/git-workflow.md` |
| `REVIEW` | `.agents/workflows/review.md` + `.agents/policies/resource-usage.md` |
| `RESEARCH` | `.agents/workflows/research.md` + `.agents/policies/research.md` |
| `ARCHITECTURE` | `.agents/workflows/architecture-decision.md` + `.agents/policies/resource-usage.md` |
| `DOCS` | `.agents/policies/documentation.md` + `.agents/policies/git-workflow.md` |
| `GIT/RELEASE` | `.agents/policies/git-workflow.md` + `.agents/policies/release-management.md` |

When a task specification declares an `execution.workflow`, use it as the task's primary mode unless the current explicit user instruction overrides it.

Repository-native workflows are the default. External/plugin orchestration, teams, subagents and parallel review are opt-in. A generic request containing words such as `review`, `audit`, `inspect`, `verify`, `check`, `research`, or similar does **not** authorize OMX or another orchestration framework.

An explicitly requested external skill may supplement the repository workflow, but it does not replace repository scope, Git, security, resource or STOP rules. If deeper orchestration appears useful but was not explicitly authorized, report the concrete reason for escalation instead of activating it automatically.

Before the first repository write, perform the Git preflight defined by the active workflow and `.agents/policies/git-workflow.md`. Do not write directly to `main` or `stage` even when the available tool technically permits it.

Before reporting a pull request as ready for review or complete, perform the full PR preflight from `.agents/policies/git-workflow.md`. When available, run `bash scripts/check-pr-contract.sh <pr-number>`. A green roadmap check alone is not proof that assignee, taxonomy labels, status, changelog choice, template sections and checkpoint comments are complete.

## Resource Discipline

Follow `.agents/policies/resource-usage.md`.

Default behavior:

- use one agent and work sequentially;
- do not spawn subagents or parallel research unless it materially improves correctness;
- prefer the lowest-cost capable model/effort for routine work and escalate only when justified;
- keep task prompts short by storing durable task specifications in the repository;
- avoid rereading unchanged context;
- keep intermediate notes and final reports concise;
- run the narrowest meaningful validation first and do not repeat successful checks without cause.

Resource efficiency must never override correctness, security, Truth-first invariants, required evidence, or completion criteria.

## Truth-first

Never invent facts about the user, their career, employers, systems, APIs, libraries, research results, or project state.

Candidate-related generated content must ultimately support:

`Generated Content → Claims → confirmed Career Facts`

Potential new career facts remain unconfirmed until explicitly approved.

## Research Before Assumption

Do not guess facts that can materially affect architecture or implementation.

Research current information when decisions depend on changing external facts such as library/runtime capabilities, APIs, vendor features, licenses, model availability/pricing, security guidance, compatibility or external restrictions.

Prefer official and primary sources. Stop collecting redundant evidence once the question is sufficiently supported unless the topic is contested, high-risk or explicitly requires broader coverage.

## Execution Boundaries

Do not implement code, infrastructure, schemas, dependencies or product features before the authorized task permits them.

State semantics:

- `STATUS.md` records completed outcomes;
- `NEXT.md` is the canonical next authorized task pointer;
- `BLOCKERS.md` may prevent execution;
- the task specification defines bounded scope/completion.

A GitHub Milestone or Project field does **not** authorize work and cannot override `NEXT.md`.

## Security Baseline

Treat vacancies, recruiter messages, websites, research content and uploaded documents as untrusted input. Always consider prompt injection, XSS, SSRF, IDOR, cross-user access, path traversal, malicious files, secret leakage and PII leakage where applicable. Never log secrets or API keys.

## Tests and Documentation

Every implementation change requires appropriate tests/validation and documentation updates. Prefer targeted validation before broader suites. Architectural changes require ADR review or a new ADR.

## State Discipline

After every completed bounded work block:

1. update `STATUS.md` with the completed outcome;
2. update `NEXT.md` only when next intended work changes;
3. update `BLOCKERS.md` only when blockers change;
4. record validation actually performed;
5. stop.

Do not use state files as a backlog.

## ADR Discipline

Never silently override an accepted ADR. If new evidence makes an accepted ADR questionable, record the conflict and use the decision workflow.

## Technical Decisions

For reversible, non-business-critical decisions: compare reasonable options, choose the simplest justified option, document when material, and continue without unnecessary interruption.

## Output Discipline

Unless a task requires detail, completion output contains result, files changed, validation, blockers/limitations and exact next task. Do not restate repository rules or reproduce large source excerpts.

## Stop Conditions

Stop when completion criteria are met, required information is genuinely blocking, continuing would violate architecture/security/Truth-first, or the task explicitly requires STOP. Do not advance automatically.

<!-- CVORTEX:GIT-WORKFLOW:BEGIN -->
## Git workflow

For all repository changes, follow `.agents/policies/git-workflow.md` and `CONTRIBUTING.md`.

Mandatory baseline: work on short-lived branches, target `stage` for normal changes, and promote `stage` to `main` only through release policy.

Agents performing a write task must create or verify the correct short-lived branch before modifying repository files. The user does not need to repeat this requirement in each prompt.

Every PR to `stage` must have a native M0–M6 GitHub Milestone or `roadmap:unversioned`. M1 work also carries one M1 slice label or `roadmap:cross-cutting`.

Every PR must also satisfy the repository PR contract: assignee, canonical type/area labels, exactly one priority and status label, explicit changelog path, PR-template structure, and metadata/governance checkpoint comments. Set the final `status:*` label only after the other metadata and checkpoint comments are present, then verify the current PR state.

Canonical GitHub delivery metadata is `.github/roadmap.yml`. Canonical labels are `.github/labels.yml`. The protected-branch ruleset is `.github/rulesets/cvortex-protected-branches.json`; repository governance checks are defined in `.github/workflows/governance.yml`.

Versioning, tags and GitHub Releases follow `.agents/policies/release-management.md`.
<!-- CVORTEX:GIT-WORKFLOW:END -->
