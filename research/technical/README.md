---
status: research-complete
date: 2026-09-12
phase: 03-technical-research
owner: CVortex
architecture_decision: none
---

# Technical Research Index

## Reports

1. [Backend runtime and data stack](01-BACKEND-RUNTIME-DATA.md)
2. [Laravel auth, authorization and security controls](02-LARAVEL-AUTH-SECURITY.md)
3. [Redis, queues and Horizon](03-REDIS-QUEUES-HORIZON.md)
4. [OpenAI API, models and cost controls](04-OPENAI-API-MODELS.md)
5. [OpenAI integration options for PHP](05-OPENAI-PHP-INTEGRATION.md)
6. [Frontend stack](06-FRONTEND-STACK.md)
7. [Component primitives](07-COMPONENT-PRIMITIVES.md)
8. [Testing stack](08-TESTING-STACK.md)
9. [Document pipeline](09-DOCUMENT-PIPELINE.md)
10. [Design tokens, Figma MCP and Code Connect](10-DESIGN-TOKENS-FIGMA.md)
11. [Decision candidates](DECISION-CANDIDATES.md)
12. [Source register](SOURCES.md)

## Research rules used

- Official documentation and primary sources were preferred.
- Facts are separated from candidate recommendations.
- Version-sensitive facts include the access/research date.
- No library or version choice below is an accepted architecture decision unless it was already fixed by the product owner.
- Concrete dependency versions must still be pinned and validated during the repository bootstrap phase.

## Main findings

- Laravel 13 is the current major and supports PHP 8.3 through 8.5. PHP 8.5 has a longer remaining support window than PHP 8.4.
- PostgreSQL 18.6 is the current supported minor on the newest stable major.
- Redis Open Source 8.10.1 includes August 2026 security fixes. Redis 8+ licensing must be documented explicitly; AGPLv3 is one available license.
- Horizon remains a strong fit for Redis queues but is not compatible with Redis Cluster.
- First-party SPA auth in Laravel should use session/cookie authentication with Sanctum and CSRF, not bearer tokens stored in the SPA.
- OpenAI's current flagship is GPT-6 Astra. GPT-5.6 Sol, Terra and Luna form progressively cheaper tiers. CVortex should map logical capability tiers to model IDs via configuration, not hardcode model names in domain logic.
- OpenAI Batch is suitable only for non-interactive workloads and provides a 50% discount with up to 24-hour turnaround.
- OpenAI does not publish an official PHP SDK. PHP choices are a community client, Laravel AI SDK, generated/direct HTTP, all behind CVortex's own provider interface.
- Next.js 16.x is Active LTS and 16.3.3 is the current security-patched stable line identified in the August 2026 release. React 19.3 and TypeScript 6.0 are current.
- Base UI, Radix and React Aria are all viable primitive layers. Base UI is the preliminary candidate for a spike, not an accepted decision.
- DTCG 2025.10 is the first stable design-token format. Style Dictionary 5.5 is current, but its own docs say complete DTCG 2025.10 support is still in progress.
- Figma Remote MCP supports read/write workflows; write-to-canvas requires a Full seat and still has beta limitations. Code Connect new integrations should use template files, not legacy framework parsers.
