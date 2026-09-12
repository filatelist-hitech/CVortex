---
title: ATS Career Platforms
status: reviewed
owner: project
created: 2026-09-12
updated: 2026-09-12
research_ids: [R12-04, R12-06]
sources_checked_at: 2026-09-12
confidence: HIGH
review_after: 2026-12-12
tags: [research, integrations, ats, career-pages]
---

# ATS Career Platforms

## Question

Можно ли распознавать ATS-hosted company career pages и получать опубликованные вакансии через документированные structured interfaces, не занимаясь reverse engineering?

## Facts

### Greenhouse

FACT: Greenhouse документирует Job Board API для опубликованных вакансий; public GET job-board data не требует private Harvest access.

EVIDENCE: https://developers.greenhouse.io/docs/job-board

INFERENCE: Greenhouse можно надежно определять как отдельный source type и использовать публичный Job Board API для вакансий.

RECOMMENDATION: `TIER B`, публичный API первым, HTML только fallback.

Confidence: HIGH.

### Lever

FACT: Lever поддерживает публичный Postings API; опубликованные postings предназначены для публичного просмотра.

EVIDENCE: https://github.com/lever/postings-api

INFERENCE: для Lever не нужен private/internal endpoint.

RECOMMENDATION: `TIER B`, Postings API.

Confidence: HIGH.

### Ashby

FACT: Ashby имеет отдельный Public Job Posting API для опубликованных вакансий. У Ashby также существуют authenticated customer APIs, которые нельзя смешивать с public endpoint.

EVIDENCE: https://developers.ashbyhq.com/docs/public-job-posting-api

RECOMMENDATION: `TIER B`, public posting API only.

Confidence: HIGH.

### SmartRecruiters

FACT: Posting API публикует public job postings; некоторые API data paths не требуют authentication, тогда как internal/protected capabilities требуют auth/scopes.

EVIDENCE: https://developers.smartrecruiters.com/docs/posting-api ; https://developers.smartrecruiters.com/docs/authentication

RECOMMENDATION: `TIER B`, public postings only.

Confidence: HIGH.

### Personio

FACT: employer может включить XML career feed, доступный без credentials. Recruiting API является отдельной customer integration capability.

EVIDENCE: https://support.personio.de/hc/en-us/articles/29375445597725-Frequently-asked-questions-on-XML-job-integration

RECOMMENDATION: `TIER B` для enabled XML feed; public page/manual fallback otherwise.

Confidence: HIGH.

### Teamtailor

FACT: Teamtailor API key создается Company Admin; public scope означает тип данных, доступный этому authorized client, а не безусловно anonymous global API.

EVIDENCE: https://support.teamtailor.com/en/articles/5963369-use-our-teamtailor-api

RECOMMENDATION: `TIER C` для generic CVortex ingestion; employer-authorized API можно рассматривать позже как отдельный use case.

Confidence: HIGH.

### Workday

FACT: Workday имеет Recruiting REST/web-service capabilities в tenant/customer security context. Наличие таких APIs не доказывает наличие generic public candidate search API across tenants.

EVIDENCE: https://developer.workday.com/

RECOMMENDATION: `TIER C`. Использовать только публичную structured career data там, где она реально опубликована; не reverse-engineer internal XHR endpoints.

Confidence: HIGH.

## ATS detection strategy — research candidate

1. Detect known ATS host/domain/markup.
2. If vendor documents a public job-board API/feed, use it.
3. Else inspect standards-based structured data such as `JobPosting` JSON-LD.
4. Else HTML parsing only if source Terms/robots and product policy permit it.
5. Else browser-side user capture, paste, file upload or manual metadata.
6. Never promote an undocumented frontend endpoint into `VacancySourceAdapter` evidence.

This is a recommendation candidate for Phase 05, not an accepted architecture decision.

## Generic structured data

Schema.org defines `JobPosting` as structured job vacancy data. Presence of JSON-LD is useful for parsing, but does not override site Terms or make arbitrary server crawling permitted.

Source: https://schema.org/JobPosting

## Decision impact

Evidence supports a future `VacancySourceAdapter` abstraction with capability metadata rather than a single generic scraper. Public ATS APIs/feeds can be first-class source classes; private/customer APIs should remain separate from public source adapters.
