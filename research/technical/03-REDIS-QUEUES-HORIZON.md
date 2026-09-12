---
status: research-complete
date: 2026-09-12
phase: 03-technical-research
owner: CVortex
architecture_decision: none
---

# Redis, Laravel Queues and Horizon

## Question

How should CVortex structure Redis-backed background processing without prematurely turning a local-first application into distributed-systems cosplay?

## Redis evidence

- **[E1]** Redis Open Source 8.10.1 was released in August 2026 and contains multiple security fixes, including memory-safety and TLS-related issues.
- **[E2]** Redis 8+ is tri-licensed under RSALv2, SSPLv1 and AGPLv3. AGPLv3 is an OSI-approved open-source license option; licensing must still be documented for deployment/distribution policy.
- **[E3]** Laravel recommends the PhpRedis extension where available; Predis is a userland fallback.

## Horizon evidence

- **[E4]** Horizon provides code-configured Redis queue workers and metrics for throughput, runtime and failures.
- **[E4]** Horizon requires the Redis queue backend.
- **[E4]** Horizon is currently not compatible with Redis Cluster. This matters if CVortex later grows beyond the local/single-node architecture.

## Queue correctness evidence

- **[E5]** Laravel supports unique jobs through `ShouldBeUnique` and related contracts.
- **[E5]** `after_commit` / after-commit dispatch prevents a worker from observing database state that has not committed yet.
- **[E5]** `retry_after` controls when a processing job can become visible for retry. Worker timeout must be shorter than retry visibility to reduce duplicate execution risk.
- Laravel queues provide at-least-once style processing semantics in practical failure cases. CVortex jobs therefore need idempotency; they must not assume magical exactly-once execution.

## Candidate queue topology

Keep the owner-proposed logical separation:

- `critical`: short user-blocking follow-ups and operationally urgent work;
- `default`: ordinary background application work;
- `llm`: interactive/standard LLM jobs;
- `documents`: DOCX/PDF rendering;
- `research`: slow external research;
- `imports`: file/vacancy/conversation ingestion.

These names are logical priorities, not separate microservices.

## Candidate operating rules

- One Redis deployment initially, with logically separated queues and Horizon supervisors.
- Prefer PhpRedis in the container.
- Pin Redis to a patched stable 8.10.x release after license acceptance.
- Define per-job timeout, attempts, backoff, idempotency key and failure policy.
- Dispatch domain-dependent jobs after DB commit.
- Use uniqueness locks where duplicate work is harmful, but do not mistake a lock for permanent idempotency.
- Record `job_id`, correlation/request IDs and relevant application/LLM-run IDs for observability.
- Provider throttling for OpenAI belongs in queue middleware/provider orchestration, not in a giant global sleep.

## Risks

- Redis Cluster cannot be adopted later without reassessing Horizon.
- Long LLM/document jobs with careless timeout/retry settings can execute twice.
- Queue priorities can starve low-priority work if supervisor capacity is poorly configured.
- Redis security patch cadence matters because the queue/cache server processes untrusted imported workload metadata.

## Decision status

Redis + Horizon is already owner-approved direction. Exact Redis version, PHP client, supervisor allocation and queue retry policies remain candidates.

## Confidence

High.

## Citations

- **[E1] Redis Open Source 8.10 release notes**, accessed 2026-09-12: https://redis.io/docs/latest/operate/oss_and_stack/stack-with-enterprise/release-notes/redisce/redisos-8.10-release-notes/
- **[E2] Redis Licenses**, accessed 2026-09-12: https://redis.io/legal/licenses/
- **[E3] Laravel Redis**, accessed 2026-09-12: https://laravel.com/framework/docs/redis
- **[E4] Laravel Horizon**, accessed 2026-09-12: https://laravel.com/framework/docs/horizon
- **[E5] Laravel Queues**, accessed 2026-09-12: https://laravel.com/framework/docs/queues
- **[E6] Laravel Queue contracts 13.x**, accessed 2026-09-12: https://api.laravel.com/docs/13.x/Illuminate/Contracts/Queue.html
