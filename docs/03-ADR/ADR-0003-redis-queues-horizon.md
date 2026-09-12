---
title: ADR-0003 — Redis-backed Laravel Queues and Horizon
status: accepted
decision_nature: OWNER_CONSTRAINT
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, queues, redis, horizon]
related: [ADR-0001, ADR-0005, ADR-0016]
---

# ADR-0003 — Redis-backed Laravel Queues and Horizon

## Context

LLM calls, imports, research and document conversion can exceed an interactive HTTP budget. The approved direction is Redis plus Laravel Horizon, while PostgreSQL remains the durable source of truth.

## Decision Drivers

- isolate heavy/failure-prone work from interactive requests;
- provide retry controls and queue visibility;
- remain simple for a local-first deployment;
- avoid a distributed messaging platform without evidence.

## Options

1. Redis-backed Laravel queues with Horizon.
2. Run all work synchronously in HTTP requests.
3. Use database queues only.
4. Introduce Kafka or a service bus now.

## Comparison

| Option | UX / latency | Operability | Simplicity | Scale fit now |
|---|---|---|---|---|
| Redis + Horizon | High | High | High | Sufficient |
| Synchronous | Low for heavy work | Low | Superficially high | Insufficient |
| Database queue | Medium | Medium | High | Possible but weaker visibility |
| Distributed bus | High | Medium | Low | Excessive |

## Decision

Use Redis for queue and cache use cases, Laravel queues for background execution, and Horizon for worker visibility and control. Interactive HTTP flows enqueue operations whose duration or failure profile is unsuitable for the request lifecycle.

Redis is not a durable business source. Queue names, worker counts, retries and concrete versions remain implementation/configuration decisions. Jobs must assume at-least-once execution and define idempotency, timeout, backoff and post-commit dispatch where relevant.

## Consequences

### Positive

- responsive API and observable background work;
- native fit with the Laravel control plane;
- one initially manageable queue system.

### Negative / Risks

- duplicate execution is possible without idempotency;
- Redis/Horizon has operational and licensing/version review needs;
- Horizon currently constrains a future Redis Cluster choice.

## Security and Validation Impact

Queued payloads, cache keys and logs must be ownership-scoped and must not contain plaintext secrets or unnecessary PII. Workers processing external files/content require least privilege and resource limits.

## Reversibility and Revisit Triggers

Revisit on measured throughput, durability or topology requirements, or if Redis Cluster becomes necessary. A replacement messaging architecture requires a new ADR.

## References

- [PROJECT.md](../../PROJECT.md).
- [Redis, Laravel Queues and Horizon](../../research/technical/03-REDIS-QUEUES-HORIZON.md).
- [External Content Threats](../../research/security/external-content-threats.md).

## Supersedes

None.

## Superseded By

None.
