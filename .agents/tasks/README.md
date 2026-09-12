# Repository Task Specifications

Use this directory for durable bounded task specifications that would otherwise be pasted repeatedly into Codex/chat prompts.

Task specs stored in Git reduce repeated prompt tokens, preserve version history, and keep execution reproducible.

## Canonical phase specs

| Phase | Task spec | Purpose |
| --- | --- | --- |
| 06 | `phase-06-product-data-ai-security-design.md` | Product, architecture, data, AI, Truth Guard and security design baseline |
| 07 | `phase-07-design-foundation.md` | Design tokens, accessibility, Figma/design-system foundation |
| 08 | `phase-08-repository-bootstrap.md` | Monorepo, Laravel/Next.js, Docker Compose and runnable M0 |
| 09 | `phase-09-auth-multi-user.md` | Invite-only authentication, roles, ownership and secret foundation |
| 10 | `phase-10-career-foundation.md` | Career facts, provenance, resume import, Claims and Truth Guard foundation |
| 11 | `phase-11-vacancies-matching.md` | Vacancy ingestion, requirements, matching and should-I-apply |
| 12 | `phase-12-application-package.md` | Applications, resume/cover package, Employer Memory and deterministic documents |

These specifications are **execution contracts**, not project state. A phase may be `ready` as a spec while the repository is not yet allowed to execute it. Always obey `.agents/state/NEXT.md`, `STATUS.md`, accepted ADRs and phase boundaries.

## How to launch a phase

Open a fresh coding-agent session in the repository and send a short launch prompt instead of pasting the full specification:

```text
Execute `.agents/tasks/phase-XX-name.md`.
Follow `/AGENTS.md` and the current project state.
Use the repository resource policy: one agent, sequential execution and task-relevant context only.
Complete the entire bounded task, update required docs/state, then STOP.
Do not proceed to the next phase.
```

Do not duplicate the full task specification in the launch prompt.

Do not ask the agent to reread the whole repository. The task spec and resource policy explicitly use lazy context loading.

## How to review a completed phase

Use a **fresh session** after implementation. Review should not be performed by continuing the implementation thread with “check yourself”.

Launch review with:

```text
Review the completed Phase XX changes.

Follow `.agents/workflows/review.md`.
Review against `.agents/tasks/phase-XX-name.md`.

Use one agent and task-relevant context only.
Prioritize completion criteria, accepted ADR consistency, security, Truth-first invariants, tests/validation claims and project-state correctness.

Do not redo the phase.
Do not fix findings.
Do not start the next phase.
Keep the report concise.
STOP after the verdict and actionable findings.
```

If review returns `CHANGES REQUIRED`, create a separate bounded fix session using only the actionable findings, then run a fresh review again before merge.

Recommended lifecycle:

```text
EXECUTE
→ REVIEW
→ FIX (only if required)
→ REVIEW
→ PASS
→ PR
→ human merge
→ next phase in a fresh session
```

## Task Spec Rules

A task specification should define only what is needed for that bounded task:

- goal;
- current phase;
- scope and non-goals;
- authoritative inputs/source of truth;
- requirements and invariants;
- research requirement when applicable;
- security considerations;
- validation;
- documentation/state updates;
- binary completion criteria;
- STOP boundary.

Stable repository-wide rules belong in `AGENTS.md` or `.agents/policies/`, not repeated inside every task.

Task-specific repetition is acceptable when it protects a critical invariant such as Truth-first, cross-user isolation, untrusted-input handling or manual approval.

## Resource Defaults

Unless a task explicitly overrides them, `.agents/policies/resource-usage.md` applies:

- one agent;
- sequential work;
- lazy context loading;
- lowest-cost capable model/effort;
- no subagents unless justified;
- no broad research rerun without a concrete gap;
- narrow validation first;
- no unnecessary successful-test reruns;
- concise final report;
- escalation only on concrete failure, ambiguity, conflict or security risk.

Specific provider/model names and pricing are intentionally not stored in task specs because they are freshness-sensitive configuration, not architecture.

## Research discipline

Do not use future phase specs as justification to re-research already accepted architecture.

Targeted current research is appropriate when:

- a concrete runtime/package/API capability is freshness-sensitive;
- an external integration restriction may have changed;
- an accepted ADR explicitly deferred an implementation choice;
- implementation is blocked by an unresolved external fact.

Use official/primary sources first and record only evidence required for the decision.

## Phase-state discipline

Task files do not authorize phase skipping.

Before execution verify:

1. the previous phase is completed according to `STATUS.md`;
2. `NEXT.md` points to the task being launched;
3. no blocker prevents execution;
4. required accepted ADR/design artifacts exist;
5. the working branch follows Git workflow policy.

After a successful phase, update state exactly as the task spec requires and stop. Never begin the next phase automatically.

## Lifecycle of task specs

Task specs may be:

- `draft` — not ready to execute;
- `ready` — bounded and executable when project state permits;
- `completed` — execution finished and spec retained for traceability;
- `superseded` — replaced by another task/spec.

Keep completed specs when useful for traceability. Do not treat `.agents/tasks/` as a backlog dumping ground.

## Human approval

A task spec can instruct an agent to create code, migrations, documents or pull requests only within its allowed phase. Human approval remains required for repository merge and for all CVortex actions that affect external job applications or employer communication.