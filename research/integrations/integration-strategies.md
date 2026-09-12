---
title: Vacancy Integration Strategies
status: reviewed
owner: project
created: 2026-09-12
updated: 2026-09-12
research_ids: [R12-06, R12-07]
sources_checked_at: 2026-09-12
confidence: HIGH
review_after: 2026-12-12
tags: [research, integrations, ingestion]
---

# Vacancy Integration Strategies

## Method matrix

| Retrieval method | Feasibility | Legal/ToS risk | Technical risk | Maintenance risk | Recommendation |
|---|---|---|---|---|---|
| Official public API | HIGH where documented | LOW-MEDIUM, API terms still apply | LOW | LOW-MEDIUM | RECOMMENDED |
| Official public feed (RSS/XML/JSON) | HIGH | LOW-MEDIUM | LOW | LOW | RECOMMENDED |
| Partner/customer API | CONDITIONAL | MEDIUM; purpose/eligibility constrained | MEDIUM | MEDIUM | ONLY for authorized use case |
| User paste | HIGH | LOW from automation perspective | LOW | LOW | RECOMMENDED universal fallback |
| User file upload | HIGH | LOW from retrieval perspective | MEDIUM-HIGH security | LOW | RECOMMENDED with hardened upload/parser pipeline |
| Browser extension capture | MEDIUM | source-specific; may be prohibited | MEDIUM | HIGH | CONDITIONAL fallback, not universal |
| User-authorized integration | MEDIUM where suitable OAuth scopes exist | MEDIUM | MEDIUM | MEDIUM | RECOMMENDED only when vendor actually supports candidate use case |
| Server-side page fetch | MEDIUM | source-specific | HIGH due SSRF/XSS/prompt injection | HIGH | CONDITIONAL |
| HTML parsing | MEDIUM | often MEDIUM-HIGH | MEDIUM | HIGH | LAST structured fallback only where permitted |
| Manual metadata entry | HIGH | LOW | LOW | LOW | RECOMMENDED fallback |
| Undocumented/private endpoint | technically possible but unsupported | HIGH | HIGH | VERY HIGH | NOT RECOMMENDED |

## Evidence

- Public ATS endpoints exist for Greenhouse, Lever, Ashby and SmartRecruiters; Personio can expose an XML feed.
- Remote OK explicitly publishes unauthenticated JSON/RSS feeds and permits aggregators with attribution/link-back.
- LinkedIn terms prohibit unauthorized automated crawling/scraping and certain scraping browser tooling.
- We Work Remotely API terms prohibit a job-search service and prohibit scraping/copying/saving/storing WWR data.
- Dice terms prohibit unapproved retrieval/indexing/data mining.
- Indeed API access is approval/use-case constrained; Job Sync is job distribution, not generic candidate vacancy search.

## Fallback decision table

| Source class | Preferred | Fallback 1 | Fallback 2 | Avoid |
|---|---|---|---|---|
| Public ATS API | API | public career page structured data | paste | private endpoint reverse engineering |
| Public feed | feed | paste | file upload | scraping when feed suffices |
| Customer/partner API | authorized integration if CVortex qualifies | public page structured data | paste | pretending partner API is public |
| Restrictive job board | user paste | file upload/manual | none | server scrape/account automation |
| Generic company page | JSON-LD / documented feed | permitted HTML | browser capture/paste | unrestricted generic crawler |

## Browser extension implication

A browser extension is not automatically a legal/ToS escape hatch. If an agreement prohibits scraping, copying, automated extraction or browser plugins used for extraction, moving code from server to the user’s browser does not magically make the restriction disappear.

RECOMMENDATION: extension capture should be source-aware and policy-gated, with manual paste always available.

## Server-side fetch implication

Any future URL importer must treat URL and response as hostile. Architecture must plan for SSRF controls before implementation: protocol allowlist, DNS/IP validation, redirect re-validation, blocking localhost/private/link-local/metadata ranges, response/body limits and timeouts.

## Provenance implication

For every import retain enough provenance to explain origin and parser path:

```text
source type
original URL where allowed
capture/import method
retrieved_at/imported_at
raw content hash
parser/adapter version
terms/policy classification snapshot
```

Exact persistence of third-party content must follow source terms; Habr Career and We Work Remotely are examples where storage restrictions matter.

## Recommendation candidate

Use a capability/policy-driven source registry in future architecture, not a universal `fetch(url) -> scrape everything` path. This remains Phase 04 research input, not an ADR.
