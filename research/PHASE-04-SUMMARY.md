---
title: Phase 04 — Product, Integrations and Hiring Research Summary
status: reviewed
owner: project
created: 2026-09-12
updated: 2026-09-12
research_ids: [R12-01, R12-02, R12-03, R12-04, R12-05, R12-06, R12-07, R13-01, R13-02, R13-03, R13-04, R13-05, R13-06, R13-07, R13-08, R14-01, R14-02, R14-03, R14-04, R14-05, R14-06, R14-07, R17-04, R17-05, R17-06]
sources_checked_at: 2026-09-12
confidence: MEDIUM
review_after: 2026-12-12
tags: [research, phase-04, integrations, recruitment, security]
---

# Phase 04 Summary

> **Historical state note**
>
> The repository-state conflict recorded later in this document was accurate during the original Phase 04 run, but it was subsequently resolved before Phase 05. Phase 03 artifacts are now present and reviewed, Phase 05 is completed, and the old prerequisite finding is **not an active blocker**.
>
> For current execution state use `.agents/state/STATUS.md`, `.agents/state/NEXT.md`, `.agents/state/BLOCKERS.md`, and `research/RESEARCH-INDEX.md`. This document preserves the original observation for research provenance only.

## Scope completed

Evidence collected for all requested vacancy-source classes and named platforms, ATS-hosted career platforms, import methods, API/auth/feed constraints, relevant Terms/robots distinctions, ATS parsing/screening, recruiting AI, resume practices, cover letters, technical hiring, RU/EU/US/UK market differences, employer-type evidence and external-content threats.

No product code, adapter, scraper, browser extension, migration, OAuth connection, final ERD or accepted architecture ADR was created.

## Canonical preliminary source tiers

Exactly one research tier is assigned per required source. Fallback methods are separate from tier classification.

| Source | Tier |
|---|---|
| HeadHunter | TIER A |
| LinkedIn | TIER D |
| Indeed | TIER C |
| Habr Career | TIER A |
| SuperJob | TIER A |
| Glassdoor | TIER U |
| Wellfound | TIER D |
| Greenhouse | TIER B |
| Lever | TIER B |
| Workday | TIER C |
| Ashby | TIER B |
| SmartRecruiters | TIER B |
| Teamtailor | TIER C |
| Personio | TIER B |
| Remote OK | TIER A |
| We Work Remotely | TIER D |
| Dice | TIER D |
| ZipRecruiter | TIER C |
| Company career pages | TIER B |

Notes:
- Habr Career is TIER A conditionally: application approval/purpose/storage terms still apply.
- Glassdoor is TIER U because current API access eligibility and exact current Terms fit require revalidation; manual capture remains the practical fallback.
- Company career pages are TIER B as a category because structured public ATS APIs/feeds/JSON-LD are feasible; an individual site can later downgrade to C/D/U after policy evidence.

## Integration approaches realistically available

### Official integration

High-confidence candidates:

- HeadHunter official API;
- SuperJob official API;
- Habr Career API, conditional on application approval, purpose and storage terms;
- Remote OK public JSON/RSS feeds.

### Structured public source

High-confidence candidates:

- Greenhouse Job Board API;
- Lever Postings API;
- Ashby Public Job Posting API;
- SmartRecruiters public Posting API;
- Personio public XML feed when enabled;
- company career pages exposing standards-based `JobPosting` data.

### Browser/manual capture preferred

- Workday generic career pages where no authorized tenant API exists;
- Teamtailor for arbitrary candidate-side use because API keys are employer/customer controlled;
- ZipRecruiter candidate-side import because documented Partner API is ATS/job distribution;
- Indeed absent an approved candidate-relevant integration;
- Glassdoor until current access/terms are revalidated.

### Avoid automation

- LinkedIn unauthorized scraping/crawling;
- Wellfound automated scraping;
- We Work Remotely persistent/job-search integration without explicit permission;
- Dice automated retrieval/indexing/data mining.

## Recruitment findings with sufficient evidence

1. ATS/recruiting systems may parse resumes into structured fields and extract skills.
2. Recruiter search can use exact/Boolean filters, explicit skills, contextual/implicit skills, synonyms/related descriptions and resume-derived skills.
3. Employer-defined required questions can be knockout criteria.
4. Vendor-specific scoring/ranking exists, but no universal cross-ATS score is supported.
5. Automated recruitment may source, summarize, rank, score and filter candidates; prevalence and decision authority vary by employer/vendor.
6. Irregular resume format/file type can reduce parser confidence in at least documented vendor systems.
7. Cover letters are context-dependent: required, optional or unavailable depending on employer/platform/market.
8. Technical interviews vary by role/employer; coding/testing/design/behavioral stages are examples, not universal sequence.
9. RU/EU/US/UK require market-aware guidance; EU is internally heterogeneous.
10. Employer-type stereotypes were not sufficiently supported to become deterministic rules.

## Popular claims without sufficient evidence

- one universal ATS score;
- guaranteed keyword threshold/density;
- “all ATS reject columns/tables/icons”;
- one universal CV page count;
- one universal global resume format;
- cover letters are always required;
- cover letters are never read;
- all employers use AI ranking;
- startup/fintech/enterprise process can be predicted reliably from employer type alone.

## Security findings

Future ingestion/research pipeline must treat all external content as untrusted. Primary threats:

- indirect prompt injection;
- SSRF from URL import;
- malicious/oversized files and parser exploits;
- archive/decompression bombs;
- XSS from imported HTML;
- path traversal/unsafe filenames;
- secret/internal-context exfiltration;
- cross-user leakage;
- third-party terms/retention violations;
- stale vacancy data.

## High-confidence conclusions

- Public ATS job-board APIs/feeds are the cleanest automation surface.
- Partner/customer APIs are not equivalent to public candidate APIs.
- Undocumented frontend endpoints must not become adapters.
- Manual paste/file import is a necessary universal fallback.
- Source-specific policy metadata is required for future ingestion design.
- Matching must be multi-dimensional and evidence-linked; fake universal ATS percentages are unsupported.
- External content needs a strict data/instruction trust boundary.

## Medium-confidence conclusions

- A conservative ATS-oriented resume template is justified, but cannot be called universally ATS-proof.
- Browser capture can improve UX on sources lacking APIs, but must be source-policy aware.
- Market-specific resume/cover guidance is justified; country-level EU detail needs further evidence.

## Low-confidence / unresolved

- exact current Glassdoor API eligibility and July 2026 scraping clause wording;
- exact rate limits for many APIs where not publicly documented;
- public/candidate API status for several boards where only partner/employer APIs were found;
- strong universal distinctions among fintech/startup/enterprise hiring;
- country-by-country EU resume/photo conventions;
- prevalence of specific AI screening mechanisms across employers;
- source-specific browser-extension legality/ToS compatibility beyond explicit restrictions researched here.

## Conflicts with existing project knowledge

No accepted product decision was contradicted. Research reinforces existing `Scope.md` decisions to avoid fake ATS percentages and make URL ingestion conditional on research.

Historical observation from the original run: Phase 02 research plan was merged in `stage`, while Phase 03 completion was not yet present. At that time this gap correctly blocked Phase 05. It has since been reconciled and is retained here only as provenance; it is not a current blocker.

## Decision inputs for Phase 05

Phase 05 may decide, but Phase 04 does not accept:

- source registry / adapter capability model;
- public ATS API/feed adapter priority;
- source policy/retention metadata;
- URL/browser/manual fallback boundaries;
- matching dimension model;
- Recruitment Knowledge Base scope/versioning;
- external-content trust boundary and URL-import security architecture.

These inputs were subsequently consumed during Phase 05 together with Phase 03 evidence. Current accepted architecture is defined by `docs/03-ADR/INDEX.md` and `docs/02-Architecture/Architecture-Baseline.md`.

## Validation

Checked before completion:

- every named vacancy source appears in the matrix;
- every named source has one canonical preliminary tier in this summary;
- API existence is not inferred from private/internal endpoints;
- partner/customer/public API distinctions are explicit;
- OAuth is not listed as useful where only unrelated OAuth exists;
- undocumented rate limits are marked unknown/not publicly documented;
- Terms/API Terms/robots interpretations are separated;
- facts/inference/recommendations are separated in research reports;
- no universal ATS score was introduced;
- security implications are documented;
- no product code or accepted ADR is introduced by Phase 04 artifacts.
