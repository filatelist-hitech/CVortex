---
title: ADR-0017 — Untrusted External Content Boundary
status: accepted
decision_nature: RESEARCH_BACKED_DECISION
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, security, ingestion, prompt-injection]
related: [ADR-0004, ADR-0007, ADR-0009, ADR-0010, ADR-0015, ADR-0016]
---

# ADR-0017 — Untrusted External Content Boundary

## Context

Vacancies, recruiter messages, websites and uploaded documents are central product inputs and attacker-controlled surfaces. Phase 04 confirmed prompt injection, SSRF, XSS, parser, archive, traversal, exfiltration, retention and cross-user risks.

## Decision Drivers

- keep external data from becoming instruction authority;
- constrain tools, network, files and LLM context;
- preserve provenance and source-policy limits;
- avoid a universal unsafe `fetch(url)` path.

## Options

1. Explicit trusted/untrusted boundary with capability/policy-gated ingestion.
2. Treat imported text as ordinary trusted application content.
3. Rely on prompt wording or user caution alone.

## Comparison

| Option | Security | Auditability | Integration flexibility | Complexity |
|---|---|---|---|---|
| Explicit boundary | High | High | High with gated adapters | Moderate |
| Trusted content | Low | Low | High but unsafe | Low |
| Prompt/user caution | Low | Low | Medium | Low |

## Decision

External content is `UNTRUSTED DATA`, never system/developer instruction authority. Ingestion capabilities are source- and policy-aware: documented APIs/feeds, authorized integrations, paste/file input, browser capture and URL fetch are distinct modes with distinct permissions. Undocumented/private endpoints are not accepted integration contracts.

Raw/source representation, normalized data and trusted system instructions remain separate. Context builders expose only authorized, task-relevant data; parsing/generation receives no secrets or unrelated user facts by default. URL fetching requires an isolated SSRF-aware boundary before it may be implemented. Imported HTML is never rendered as trusted raw markup. Files require validation and constrained processing.

Detailed threat models, retention rules and adapter contracts belong to Phase 06. This ADR freezes the boundary, not their implementation.

## Consequences

### Positive

- prompt injection and ingestion risks become architectural concerns, not prompt folklore;
- source restrictions and provenance can be enforced;
- risky modes can remain disabled without blocking safe manual input.

### Negative / Risks

- ingestion paths need capability/policy metadata and separate tests;
- some automation stays unavailable until controls and source terms are validated;
- sanitization and provenance add processing cost.

## Security and Validation Impact

Phase 06 must cover SSRF redirect/DNS/IP validation, XSS sanitization/escaping, file signatures and resource limits, archive limits, generated paths, secret redaction, tenant-scoped caches/jobs and denial tests. Human approval does not replace these controls.

## Reversibility and Revisit Triggers

Individual source modes may be enabled or disabled through researched policy. Relaxing the trust boundary requires security evidence and a superseding ADR.

## References

- [PROJECT.md](../../PROJECT.md).
- [External Content Threats](../../research/security/external-content-threats.md).
- [Vacancy Integration Strategies](../../research/integrations/integration-strategies.md).
- [ATS Career Platforms](../../research/integrations/ats-career-platforms.md).
- [Phase 04 Summary](../../research/PHASE-04-SUMMARY.md).

## Supersedes

None.

## Superseded By

None.
