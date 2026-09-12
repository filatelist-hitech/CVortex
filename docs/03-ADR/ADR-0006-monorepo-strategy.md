---
title: ADR-0006 — Incremental Monorepo Strategy
status: accepted
decision_nature: DERIVED_ARCHITECTURAL_DECISION
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, repository, monorepo]
related: [ADR-0001, ADR-0004, ADR-0012]
---

# ADR-0006 — Incremental Monorepo Strategy

## Context

CVortex has one product, one owner and tightly coupled documentation, research, API, UI and deployment boundaries. Expected zones include `apps/`, `services/`, `packages/`, `infra/`, `docs/`, `research/` and `brand/`, but most do not yet exist.

## Decision Drivers

- atomic review of cross-boundary changes;
- one decision/documentation history;
- simple local onboarding;
- no empty scaffolding or tooling before need.

## Options

1. One incremental monorepo.
2. Separate repositories for frontend, backend, infrastructure and docs now.
3. One undifferentiated application directory.

## Comparison

| Option | Traceability | Change coordination | Isolation | Current complexity |
|---|---|---|---|---|
| Monorepo | High | High | Via boundaries | Low |
| Polyrepo | Medium | Low | High | High |
| Undifferentiated repo | Medium | High | Low | Low now, costly later |

## Decision

Use a monorepo with explicit top-level zones introduced only when real artifacts exist. `docs/`, `research/` and `brand/` retain knowledge/design assets; runnable applications, specialized services, shared packages and infrastructure belong under their corresponding zones when a permitted implementation phase creates them.

Repository proximity does not erase runtime or domain boundaries. Cross-zone dependencies must be deliberate; the API contract remains the client/backend boundary.

## Consequences

### Positive

- architecture, evidence and implementation evolve atomically;
- shared validation and consistent version control;
- easier local setup for a small project.

### Negative / Risks

- CI can become broad without path-aware checks;
- accidental coupling is easier unless ownership/dependency rules are documented;
- later extraction may require history/tooling work.

## Security and Validation Impact

Repository access does not imply runtime access. Secrets and private user data remain prohibited. Future build scopes must avoid leaking server code/configuration into client artifacts.

## Reversibility and Revisit Triggers

Extract a repository only when independent ownership, release cadence, access control or scale provides concrete benefit. No empty directory tree is created by this ADR.

## References

- [PROJECT.md](../../PROJECT.md).
- [Documentation Map](../00-Home/Documentation-Map.md).
- [Git Workflow Policy](../../.agents/policies/git-workflow.md).

## Supersedes

None.

## Superseded By

None.
