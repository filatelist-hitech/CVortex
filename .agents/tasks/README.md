# Repository Task Specifications

Task specs are durable bounded execution contracts. They are not a backlog and they do not authorize work by themselves.

Execution authority is always:

```text
STATUS.md prerequisites
+ NEXT.md pointer
+ BLOCKERS.md
+ active task spec
```

## Active roadmap

The canonical product sequence is `docs/01-Product/Roadmap.md`.

Completed Phases 00–07 remain historical foundation. The active implementation model is milestone/value-slice based.

### Ready / near-term specs

| Order | Task spec | Observable outcome |
|---|---|---|
| M0 | `m0-runnable-core.md` | CVortex real stack opens locally in the browser |
| M1.1 | `m1-1-access-core.md` | invited user can register/login safely |
| M1.2 | `m1-2-career-core.md` | pasted career text becomes reviewed CONFIRMED facts/Claims |
| M1.3 | `m1-3-vacancy-core.md` | pasted vacancy receives explainable match/gaps/apply recommendation |
| M1.4 | `m1-4-application-draft.md` | truthful resume recommendations + cover drafts can be reviewed/approved |
| M2 planning | `m2-application-package-planning.md` | Preview 0.1 findings define one bounded application-package implementation task |

Only the task named by `NEXT.md` is executable.

M2–M6 implementation detail stays at roadmap level until the previous value checkpoint is validated. After Preview 0.1, the bounded M2 planning task may define the next implementation contract; do not pre-expand later milestones into giant execution specs.

## Legacy phase specs

The previous horizontal Phase 08–12 plan is preserved under `.agents/tasks/legacy/` as requirement inventory and historical evidence.

It is **not execution authority**.

Legacy mapping:

```text
Phase 08 → M0
Phase 09 → M1.1 + M2 auth/credential hardening
Phase 10 → M1.2 + M3 resume file import
Phase 11 → M1.3 + M3 URL/source integrations
Phase 12 → M1.4 + M2 package/documents + M4 employer journey
```

Two hardened pre-reset specifications are preserved byte-for-byte as references:

- `legacy/phase-08-hardened-pr16.md` — final tree from commit `6ca25b09550b8315913f06382dff28f8d326aa06`;
- `legacy/phase-09-hardened-pr17.md` — final tree from commit `9267f15545cca70a245798a58179742789cf2737`.

The active M0/M1.1 specs intentionally extract only the decisions required by their smaller scopes. Deferred decisions remain available in those legacy references for M2+.

## Task-size rule

One execution task should normally create **one observable system or user outcome**.

Good:

- start the real local stack;
- register/login an invited user;
- paste career text and review extracted facts;
- paste a vacancy and receive explainable match;
- generate and approve a truthful cover draft.

Bad:

- implement all authentication, administration, secrets and account lifecycle forever;
- implement all Career functionality;
- implement all integrations;
- implement the entire application package plus documents and Employer Memory in one task.

Cross-cutting repository policies still apply without being copied into every task.

## Launch

Use a fresh coding-agent session:

```text
Execute `.agents/tasks/<task>.md`.
Follow `/AGENTS.md` and current project state.
Use one agent, sequential execution and task-relevant context only.
Complete the bounded task, update required docs/state, then STOP.
```

Do not paste the full spec into the launch prompt.

## Review

Use a fresh session and `.agents/workflows/review.md`.

Review against the active task spec, accepted ADRs and the observable completion criteria. Do not turn review into implementation.

Lifecycle:

```text
EXECUTE
→ REVIEW
→ FIX if required
→ REVIEW
→ PASS
→ human decision/merge
→ next bounded task
```

## Resource defaults

`.agents/policies/resource-usage.md` remains authoritative:

- one agent;
- sequential work;
- lazy/task-relevant context;
- lowest-cost capable execution;
- targeted research;
- narrow validation first;
- concise final reports;
- escalation only for concrete ambiguity/failure/security risk.

Resource efficiency never overrides Truth-first, security, authorization, evidence or completion criteria.
