# Agent Resource Usage Policy

## Purpose

Minimize token, compute and tool usage without weakening correctness, security, evidence quality or phase completion criteria.

## Default Execution Mode

Unless the task explicitly requires otherwise:

- use one agent;
- work sequentially;
- do not spawn subagents;
- do not parallelize research;
- prefer the lowest-cost capable model and low reasoning effort for routine work;
- escalate model capability or reasoning only when measurable uncertainty, failed validation, hard conflicts or high-risk decisions justify it.

The preferred escalation path is:

```text
low-cost / low effort
↓ only if insufficient
standard / medium effort
↓ only for genuinely hard or high-risk work
advanced / high effort
```

Concrete provider/model names and prices are not architectural constants and must not be frozen here.

## External orchestration

Repository-native workflows are the default execution layer.

External orchestration frameworks, plugin review modes, teams, parallel agents and equivalent mechanisms are opt-in. Do not activate them because a request uses a generic verb such as `review`, `audit`, `check`, `verify`, `research` or `inspect`.

External orchestration is authorized only when the current explicit user/task instruction names or clearly opts into that framework, or when a repository task spec explicitly declares it. Even then, repository scope, Git, security and STOP rules still apply.

If extra orchestration appears useful but is not authorized, report the exact escalation need instead of activating it automatically.

## Context Budget

Load context lazily.

Always start with:

- `PROJECT.md`;
- `.agents/state/STATUS.md`;
- the current task specification, when present.

Then load only directly relevant:

- scoped `AGENTS.md`;
- policies;
- ADRs;
- docs;
- research artifacts;
- source files.

Do not recursively read entire documentation, research, policy or source trees by default.

Use indexes, links, targeted search and known paths to locate the smallest sufficient context.

Do not reread unchanged files in the same work block unless needed to resolve ambiguity.

## Task Prompt Budget

Durable task requirements belong in repository task specs under `.agents/tasks/`.

Interactive launch prompts should normally contain only:

- the task-spec path;
- any current overrides;
- explicit resource constraints if different from this policy;
- STOP/phase boundary when relevant.

Do not duplicate stable project rules in every prompt.

## Subagents and Parallelism

Subagents are opt-in, not default.

Use them only when all are true:

1. workstreams are genuinely independent;
2. parallel execution materially improves correctness or reduces a real blocker;
3. duplicated context/tool cost is justified;
4. the parent agent can reconcile results deterministically.

Do not use subagents merely to make routine work faster.

Prefer a single agent running longer over many agents duplicating repository context.

## Research Efficiency

Research sequentially.

For each question:

1. prefer official/primary evidence;
2. gather enough evidence to establish confidence;
3. stop collecting redundant sources once the finding is sufficiently supported;
4. expand only when sources conflict, confidence remains low, the claim is high-risk, or the task explicitly requires breadth.

Do not repeat a completed research pass merely for stylistic confidence.

## Validation Efficiency

Run the narrowest meaningful validation first.

Do not repeat a successful validation unless:

- relevant code/configuration changed afterwards;
- a previous failure requires confirmation;
- the task or policy explicitly requires a broader final check.

Documentation-only changes do not require full application test suites unless they affect generated/validated artifacts.

Broad validation is appropriate before phase completion or merge when risk warrants it.

## Output Budget

Prefer concise working notes and completion reports.

Do not:

- restate instructions already stored in the repository;
- reproduce long source excerpts when a path/reference is enough;
- generate verbose summaries that do not affect a decision;
- list obvious unchanged project facts.

Default completion report:

- result;
- files changed;
- validation performed;
- blockers/limitations;
- next bounded task/phase.

## Escalation Triggers

Escalate model capability, reasoning effort, context breadth or parallelism only for concrete triggers such as:

- conflicting accepted ADRs/evidence;
- failed deterministic validation that cannot be localized cheaply;
- security-sensitive ambiguity;
- complex cross-domain architecture;
- multi-source reasoning where simpler passes remain inconclusive;
- repeated schema/output validation failure;
- explicit task requirement.

Do not escalate merely because prose quality could be prettier.

## Prohibited Behavior

- Recursively loading the whole repository without need.
- Spawning multiple agents for routine tasks.
- Activating external orchestration without explicit authorization.
- Using the strongest/highest-effort mode as a default.
- Repeating successful tests or research without a reason.
- Duplicating long task specifications in chat prompts.
- Trading away security, Truth-first rules, evidence quality or required validation for lower token usage.

## Completion Checks

Before completing a task, confirm:

- context read was task-relevant rather than exhaustive;
- unnecessary subagents/parallelism were not used;
- no external orchestration was activated without authorization;
- validation was sufficient but not needlessly repeated;
- output is concise;
- any escalation was justified by a concrete need.
