---
title: ADR-0005 — Local-first Docker Compose Deployment Baseline
status: accepted
decision_nature: OWNER_CONSTRAINT
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, deployment, docker]
related: [ADR-0001, ADR-0003, ADR-0015, ADR-0016]
---

# ADR-0005 — Local-first Docker Compose Deployment Baseline

## Context

The initial CVortex environment runs locally on a Mac but must remain portable to a VPS or cloud host. The architecture must not encode one developer's filesystem or introduce Kubernetes before operational need exists.

## Decision Drivers

- reproducible local operation;
- parity with a future Linux host;
- bounded cost and operational surface;
- explicit service and storage boundaries.

## Options

1. Docker Compose baseline, portable configuration and volumes.
2. Native Mac-only processes and absolute paths.
3. Hosted managed services first.
4. Kubernetes first.

## Comparison

| Option | Local fit | Portability | Operability | Premature complexity |
|---|---|---|---|---|
| Compose | High | High | High | Low |
| Native Mac | High | Low | Medium | Low |
| Cloud first | Low | High | Medium | Medium |
| Kubernetes | Low | High | Low initially | Very high |

## Decision

Docker Compose is the development/deployment baseline for the initial local Mac environment. Runtime configuration, service discovery, paths and persistent storage use environment/configuration and container abstractions rather than a specific Mac directory layout.

Nginx is the approved web entry point when the runnable topology is implemented; its concrete configuration is not frozen here.

The same service boundaries should be deployable on a conventional VPS/cloud container host with operational changes rather than domain rewrites. Kubernetes is rejected for the current baseline. This ADR does not create Compose files or choose a cloud vendor.

## Consequences

### Positive

- reproducible services and closer local/hosted parity;
- simple backup, upgrade and troubleshooting surface;
- no cloud-vendor dependency.

### Negative / Risks

- Docker Desktop resource/filesystem behavior can differ from Linux;
- production hosting still needs secrets, TLS, backups and observability design;
- stateful volume migration must be practiced.

## Security and Validation Impact

Secrets stay outside images and Git. Services use least exposure; converters/parsers need isolation and no unnecessary network access. Deployment validation must cover arm64 Mac and target Linux container behavior when implementation begins.

## Reversibility and Revisit Triggers

Move to managed services or an orchestrator only when availability, scale or operations justify it through a new ADR.

## References

- [PROJECT.md](../../PROJECT.md).
- [Backend Runtime and Data Stack](../../research/technical/01-BACKEND-RUNTIME-DATA.md).
- [Document Pipeline](../../research/technical/09-DOCUMENT-PIPELINE.md).

## Supersedes

None.

## Superseded By

None.
