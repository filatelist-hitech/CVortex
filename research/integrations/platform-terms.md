---
title: Vacancy Platform Terms and Access Restrictions
status: reviewed
owner: project
created: 2026-09-12
updated: 2026-09-12
research_ids: [R17-04, R17-05, R17-06, R12-01, R12-02, R12-03, R12-05]
sources_checked_at: 2026-09-12
confidence: MEDIUM
review_after: 2026-10-12
tags: [research, integrations, terms, robots]
---

# Vacancy Platform Terms and Access Restrictions

## Scope

Technical/product research, not legal advice. We distinguish `Terms of Service`, `API Terms`, `robots.txt`, technical limitations and CVortex interpretation.

## Findings

### LinkedIn

FACT: LinkedIn Crawling Terms prohibit automated crawling without express permission. User Agreement also prohibits scraping/copying via bots, scripts and certain browser plugins/add-ons.

EVIDENCE: https://www.linkedin.com/legal/crawling-terms ; https://www.linkedin.com/legal/user-agreement

INTERPRETATION: generic server scraping or scraping extension is high-risk for CVortex.

RECOMMENDATION: manual user paste/import only unless LinkedIn grants a suitable integration.

Confidence: HIGH.

### Indeed

FACT: current Developer Agreement grants API access at Indeed discretion for approved integrations and limits API/data use to approved/documented purpose. It restricts scraping/building permanent copies except as required/expressly permitted and prohibits using the integration to replicate Indeed experience.

EVIDENCE: https://docs.indeed.com/legal-terms/developer-agreement ; https://docs.indeed.com/legal-terms/job-sync

INTERPRETATION: Indeed’s partner API catalog is not evidence of a public candidate vacancy-search API for CVortex.

RECOMMENDATION: treat API integration as partner-only; manual import until approved candidate-relevant capability is documented.

Confidence: HIGH.

### Habr Career

FACT: API Terms require application activation/declared purpose and restrict storage of API information except temporary unavoidable caching.

EVIDENCE: https://career.habr.com/info/legal/api_rules

INTERPRETATION: the official API is real, but CVortex’s history/persistence use case may conflict with storage rules.

RECOMMENDATION: mark API as conditional until retention design is checked against terms.

Confidence: HIGH.

### Glassdoor

FACT: current terms history identifies July 1, 2026 as current. Public developer pages still describe Jobs API and note additional Jobs APIs are partner-only. Previous terms explicitly prohibited scraping/mining without express permission.

EVIDENCE: https://www.glassdoor.com/about/terms-history/ ; https://www.glassdoor.com/developer/jobsApiActions.htm

UNKNOWN: this research run did not obtain a clean excerpt from the July 1, 2026 terms proving exact current scraping language.

RECOMMENDATION: do not automate Glassdoor retrieval until current terms/access requirements are revalidated; manual fallback.

Confidence: MEDIUM.

### Wellfound

FACT: Wellfound Terms restrict scraping/copying and automated system load.

EVIDENCE: https://wellfound.com/terms

RECOMMENDATION: avoid automated ingestion; paste/manual fallback.

Confidence: HIGH.

### Remote OK

FACT: official FAQ provides free public JSON and RSS feeds without auth. Aggregators should credit Remote OK and link each original posting.

EVIDENCE: https://remoteok.com/faq ; Terms updated 2026-07-20: https://remoteok.com/legal

RECOMMENDATION: public feed integration is realistic with attribution/link-back.

Confidence: HIGH.

### We Work Remotely

FACT: API Terms prohibit using WWR API/data to build a job-search service; require applications to route through WWR; prohibit scraping/copying/saving/storing WWR data and require API-only usage for permitted products.

EVIDENCE: https://weworkremotely.com/api-terms-and-guidelines

RECOMMENDATION: `TIER D` for CVortex automated/persistent integration absent explicit permission.

Confidence: HIGH.

### Dice

FACT: Dice Terms prohibit unapproved navigation/search/storage mechanisms, retrieval/indexing/data mining, certain AI/model uses and research/information gathering.

EVIDENCE: https://www.dice.com/about/terms-and-conditions

RECOMMENDATION: no automated source adapter; manual input only.

Confidence: HIGH.

### ZipRecruiter

FACT: Partner Platform is explicitly ATS integration: partners post jobs, receive applications and exchange hiring signals. Jobs API uses Basic API key and creates/updates/retrieves jobs belonging to the partner.

EVIDENCE: https://www.ziprecruiter.com/partner/documentation/ ; https://www.ziprecruiter.com/partner/documentation/job-api/

INTERPRETATION: this is not a public cross-platform job-search API for CVortex candidates.

RECOMMENDATION: do not classify it as candidate ingestion API.

Confidence: HIGH.

## robots.txt

RFC 9309 standardizes Robots Exclusion Protocol. Robots rules communicate crawler preferences/access policy for compliant crawlers; they are not authentication/authorization and are not a substitute for ToS/API Terms.

Source: https://www.rfc-editor.org/rfc/rfc9309.html

For sources where a reliable current robots artifact was not captured in this run, matrix value is `NOT CONFIRMED`, not guessed.

## Rate limits

Exact limits were recorded only when an official source exposed them. For most sources above the relevant rate limit remains `NOT PUBLICLY DOCUMENTED` in this evidence set. No synthetic numbers are introduced.

## Review policy

Re-check Terms/API terms before implementation and at least every 30–90 days for restrictive/high-change platforms. A material terms change can supersede this research without changing an ADR silently.
