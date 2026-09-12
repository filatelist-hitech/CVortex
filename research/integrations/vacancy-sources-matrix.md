---
title: Vacancy Sources Matrix
status: reviewed
owner: project
created: 2026-09-12
updated: 2026-09-12
research_ids: [R12-01, R12-02, R12-03, R12-04, R12-05, R12-06, R12-07, R17-04, R17-05, R17-06]
sources_checked_at: 2026-09-12
confidence: MEDIUM
review_after: 2026-12-12
tags: [research, integrations, vacancies, ats]
---

# Vacancy Sources Matrix

## Purpose

Единая evidence matrix для будущего vacancy ingestion CVortex. Это research classification, не accepted architecture decision.

Значения capability: `YES`, `NO`, `LIMITED`, `PARTNER_ONLY`, `CUSTOMER_ONLY`, `UNKNOWN`.

Tier:
- `A` — official integration feasible;
- `B` — structured public career data feasible;
- `C` — browser/manual capture preferred;
- `D` — automated integration high-risk / impractical;
- `U` — insufficient evidence.

`robots.txt` фиксируется отдельно от Terms/API Terms. Robots не является юридическим разрешением и не заменяет authorization.

## Summary matrix

| Source | Type | Official API/feed relevant to CVortex | Access | Recommended import | Tier | Confidence |
|---|---|---|---|---|---|---|
| HeadHunter | job board | YES, REST API | OAuth2 / documented app flows | official API; paste fallback | A | HIGH |
| LinkedIn | professional network/job board | PARTNER_ONLY for Talent integrations | approved partner/customer | user paste/manual; no automated scraping | D | HIGH |
| Indeed | job board | PARTNER_ONLY / approved integrations | partner approval + API keys | paste/manual unless approved integration | C | HIGH |
| Habr Career | job board | YES, approved API | app activation + OAuth2 | user-authorized API subject to storage terms; paste fallback | A (conditional) | HIGH |
| SuperJob | job board | YES, REST API | app key + OAuth2 | official API; paste fallback | A | HIGH |
| Glassdoor | job/review platform | LIMITED; public Jobs API docs plus partner-only APIs | eligibility/key requirements need revalidation | manual preferred; API only after access confirmation | U/C | MEDIUM |
| Wellfound | startup job board | NO public candidate API confirmed | n/a | user paste/manual | D | HIGH |
| Greenhouse | ATS career platform | YES, public Job Board API | public GET, no auth for published jobs | public Job Board API | B | HIGH |
| Lever | ATS career platform | YES, public Postings API | public published postings | public Postings API | B | HIGH |
| Workday | ATS/HCM | CUSTOMER_ONLY tenant APIs | tenant/customer security | structured career page / manual; no private endpoint reverse engineering | C | HIGH |
| Ashby | ATS career platform | YES, public job posting API | public board endpoint | public posting API | B | HIGH |
| SmartRecruiters | ATS career platform | YES, public Posting API | public postings; auth for protected data | public Posting API | B | HIGH |
| Teamtailor | ATS career platform | CUSTOMER_ONLY API key | Company Admin-generated key | public career page/manual; employer-authorized API later | C | HIGH |
| Personio | ATS career platform | YES, public XML feed when enabled | no credentials for enabled XML feed | XML feed | B | HIGH |
| Remote OK | remote job board | YES, public JSON + RSS | no auth; attribution required for aggregation | public feed/API | A | HIGH |
| We Work Remotely | remote job board | API exists but terms prohibit job-search service and saving/storing API data | special token | user paste/manual only unless explicit permission | D | HIGH |
| Dice | job board | no candidate retrieval API confirmed; employer integrations exist | contract | user paste/manual; avoid automated retrieval | D | HIGH |
| ZipRecruiter | job board | PARTNER_ONLY Jobs API is ATS -> ZipRecruiter job distribution | partner API key | user paste/manual; partner integration is not candidate search | C | HIGH |
| Company career pages | generic/ATS-hosted | varies | varies | known ATS API/feed -> JSON-LD JobPosting -> permitted HTML -> browser/manual fallback | B/C | HIGH |

## Detailed records

### HeadHunter

- `source_type`: job board
- `official_website`: https://hh.ru/
- `official_api`: YES
- `api_type`: REST/JSON
- `api_documentation`: https://api.hh.ru/openapi/redoc
- `public_or_private`: public documentation; app/user access depends on endpoint
- `authentication`: OAuth 2.0; documented application flows
- `oauth_support`: YES
- `feeds`: UNKNOWN
- `web_access`: public website exists
- `career_page_structure`: job-board vacancy pages
- `allowed_retrieval_methods`: official API; user paste
- `documented_restrictions`: follow API terms/endpoint permissions; do not infer site scraping permission from API availability
- `terms_relevant_to_cvortex`: API use must follow documented contract
- `robots_relevant_to_cvortex`: NOT CONFIRMED in this research run
- `rate_limits`: NOT PUBLICLY DOCUMENTED in evidence collected
- `browser_extension_feasibility`: LIMITED; prefer API/paste
- `server_side_fetch_feasibility`: YES through official API; generic HTML fetch not recommended without separate terms check
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: YES where relevant OAuth scopes permit
- `fallback_strategy`: paste vacancy text / file / manual metadata
- `security_notes`: API responses and vacancy text remain untrusted data
- `confidence`: HIGH
- `review_after`: 2026-12-12
- `sources`: HH OpenAPI above

### LinkedIn

- `source_type`: professional network / job board
- `official_api`: PARTNER_ONLY for Talent Solutions integration use cases
- `api_type`: Talent Solutions / Apply Connect / job distribution integrations
- `api_documentation`: https://learn.microsoft.com/en-us/linkedin/talent/
- `public_or_private`: restricted/approved partner
- `authentication`: partner/application credentials where approved
- `oauth_support`: LIMITED; OAuth existence does not make candidate vacancy search available to CVortex
- `feeds`: NOT CONFIRMED for candidate retrieval
- `allowed_retrieval_methods`: user paste/manual input
- `documented_restrictions`: User Agreement and Crawling Terms prohibit unauthorized scraping/crawling/automation and certain browser plugins/add-ons
- `terms_relevant_to_cvortex`: automated extraction without permission is not recommended
- `robots_relevant_to_cvortex`: current robots includes explicit automated-access notice and disallows job-related paths for crawlers
- `rate_limits`: NOT PUBLICLY DOCUMENTED for a relevant CVortex candidate-search API because such API was not confirmed
- `browser_extension_feasibility`: HIGH RISK if it scrapes/copies LinkedIn content; do not recommend
- `server_side_fetch_feasibility`: NO for automated scraping without express permission
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: NOT CONFIRMED for CVortex vacancy retrieval
- `fallback_strategy`: paste vacancy text; manual metadata; user-provided file
- `security_notes`: treat pasted content as untrusted; never automate account actions
- `confidence`: HIGH
- `review_after`: 2026-10-12
- `sources`: https://www.linkedin.com/legal/crawling-terms ; https://www.linkedin.com/legal/user-agreement ; https://learn.microsoft.com/en-us/linkedin/talent/apply-connect/apply-connect-overview

### Indeed

- `source_type`: job board
- `official_api`: PARTNER_ONLY / approval-based
- `api_type`: Job Sync and other hiring integrations
- `api_documentation`: https://docs.indeed.com/
- `public_or_private`: partner/employer/agency integration
- `authentication`: API key where granted
- `oauth_support`: NOT CONFIRMED as a relevant candidate vacancy retrieval path
- `feeds`: partner job sync/feed capabilities exist for job distribution
- `allowed_retrieval_methods`: approved integration for approved purpose; user paste/manual otherwise
- `documented_restrictions`: API use limited to approved/documented integration purpose; permanent copies/scraping restricted except where necessary/expressly permitted
- `terms_relevant_to_cvortex`: Indeed may approve/reject integrations; candidate-side generic search API was not confirmed
- `robots_relevant_to_cvortex`: checked separately; robots is not authorization
- `rate_limits`: vendor may set/enforce limits; exact relevant limit NOT PUBLICLY DOCUMENTED in collected evidence
- `browser_extension_feasibility`: LIMITED/NOT RECOMMENDED absent express permission
- `server_side_fetch_feasibility`: approved API only; generic scraping not recommended
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: NOT CONFIRMED for candidate vacancy retrieval
- `fallback_strategy`: paste vacancy text / user file / manual metadata
- `security_notes`: no account automation; external data untrusted
- `confidence`: HIGH for partner API constraints; MEDIUM for site-specific retrieval alternatives
- `review_after`: 2026-10-12
- `sources`: https://docs.indeed.com/legal-terms/developer-agreement ; https://docs.indeed.com/legal-terms/job-sync

### Habr Career

- `source_type`: job board
- `official_api`: YES
- `api_type`: REST API
- `api_documentation`: https://career.habr.com/info/api
- `public_or_private`: application activation required
- `authentication`: OAuth 2.0
- `oauth_support`: YES
- `feeds`: UNKNOWN
- `allowed_retrieval_methods`: approved API for declared functionality; user paste
- `documented_restrictions`: API terms constrain purpose and storage; API information generally must not be retained except temporary unavoidable cache
- `terms_relevant_to_cvortex`: persistent vacancy storage needs explicit compatibility review before implementation
- `robots_relevant_to_cvortex`: NOT CONFIRMED
- `rate_limits`: Habr may impose limits; exact values NOT PUBLICLY DOCUMENTED in collected evidence
- `browser_extension_feasibility`: LIMITED; official API/paste preferred
- `server_side_fetch_feasibility`: API YES if approved; generic HTML fetch not recommended without terms evidence
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: YES, conditional on application approval/scopes/terms
- `fallback_strategy`: paste vacancy text / file
- `security_notes`: API content untrusted; storage policy is a product/legal constraint
- `confidence`: HIGH
- `review_after`: 2026-10-12
- `sources`: https://career.habr.com/info/legal/api_rules ; https://career.habr.com/info/api

### SuperJob

- `source_type`: job board
- `official_api`: YES
- `api_type`: REST/JSON
- `api_documentation`: https://api.superjob.ru/
- `public_or_private`: public docs; application key and OAuth for user data
- `authentication`: X-Api-App-Id / OAuth2 as documented
- `oauth_support`: YES
- `feeds`: UNKNOWN
- `allowed_retrieval_methods`: official API; user paste
- `documented_restrictions`: follow API contract
- `terms_relevant_to_cvortex`: use only documented API behavior
- `robots_relevant_to_cvortex`: NOT CONFIRMED
- `rate_limits`: NOT PUBLICLY DOCUMENTED in collected evidence
- `browser_extension_feasibility`: LIMITED; API preferred
- `server_side_fetch_feasibility`: YES via official API
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: YES where OAuth endpoints require it
- `fallback_strategy`: paste/file/manual
- `security_notes`: untrusted vacancy data
- `confidence`: HIGH
- `review_after`: 2026-12-12
- `sources`: https://api.superjob.ru/

### Glassdoor

- `source_type`: job/review platform
- `official_api`: LIMITED
- `api_type`: Jobs API actions; additional APIs partner-only
- `api_documentation`: https://www.glassdoor.com/developer/jobsApiActions.htm
- `public_or_private`: public documentation exists; current access/keys/eligibility require revalidation
- `authentication`: UNKNOWN for a new CVortex integration
- `oauth_support`: UNKNOWN
- `feeds`: UNKNOWN
- `allowed_retrieval_methods`: API only after current access eligibility confirmed; manual import
- `documented_restrictions`: historical/current-term lineage prohibits scraping/mining without express permission; current terms dated 2026-07-01 should be reviewed before implementation
- `terms_relevant_to_cvortex`: automation requires caution/permission
- `robots_relevant_to_cvortex`: checked but not used as legal permission
- `rate_limits`: UNKNOWN
- `browser_extension_feasibility`: LIMITED/HIGH RISK
- `server_side_fetch_feasibility`: NOT RECOMMENDED without permission
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: UNKNOWN
- `fallback_strategy`: paste/manual
- `security_notes`: avoid using review/company data beyond permitted scope
- `confidence`: MEDIUM
- `review_after`: 2026-10-12
- `sources`: https://www.glassdoor.com/developer/jobsApiActions.htm ; https://www.glassdoor.com/about/terms-history/

### Wellfound

- `source_type`: startup job board
- `official_api`: NO public candidate vacancy API confirmed
- `api_type`: n/a
- `authentication`: n/a for integration
- `oauth_support`: UNKNOWN
- `feeds`: UNKNOWN
- `allowed_retrieval_methods`: user paste/manual
- `documented_restrictions`: Terms restrict scraping/copying and automated systems beyond ordinary human-like load
- `terms_relevant_to_cvortex`: automated retrieval not recommended
- `robots_relevant_to_cvortex`: current robots checked; not treated as authorization
- `rate_limits`: UNKNOWN
- `browser_extension_feasibility`: HIGH RISK if scraping/copying
- `server_side_fetch_feasibility`: NO recommended automated path
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: NOT CONFIRMED
- `fallback_strategy`: paste/file/manual metadata
- `security_notes`: untrusted external content
- `confidence`: HIGH
- `review_after`: 2026-12-12
- `sources`: https://wellfound.com/terms

### Greenhouse

- `source_type`: ATS career platform
- `official_api`: YES
- `api_type`: Job Board API
- `api_documentation`: https://developers.greenhouse.io/docs/job-board
- `public_or_private`: published job GET endpoints public; application submission/auth differs
- `authentication`: none for public published job GET
- `oauth_support`: not needed for public job retrieval
- `feeds`: API JSON
- `web_access`: ATS-hosted boards
- `career_page_structure`: board token + published jobs
- `allowed_retrieval_methods`: public Job Board API
- `documented_restrictions`: use documented endpoint; do not substitute private Harvest endpoints
- `terms_relevant_to_cvortex`: only published public job data path recommended
- `robots_relevant_to_cvortex`: website robots not necessary for documented API path
- `rate_limits`: NOT PUBLICLY DOCUMENTED in collected evidence
- `browser_extension_feasibility`: feasible as capture fallback, but API preferred
- `server_side_fetch_feasibility`: YES through documented API
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: not required for published jobs
- `fallback_strategy`: public career page / paste
- `security_notes`: validate tenant/board identifier; untrusted text
- `confidence`: HIGH
- `review_after`: 2026-12-12
- `sources`: https://developers.greenhouse.io/docs/job-board

### Lever

- `source_type`: ATS career platform
- `official_api`: YES
- `api_type`: Postings API
- `api_documentation`: https://github.com/lever/postings-api
- `public_or_private`: published jobs public
- `authentication`: not required for public postings
- `oauth_support`: not required for public postings
- `feeds`: JSON/HTML via documented API
- `career_page_structure`: site/account postings
- `allowed_retrieval_methods`: documented Postings API
- `documented_restrictions`: public postings are documented as publicly viewable; API is preferred over scraping
- `terms_relevant_to_cvortex`: keep apply/original links and source provenance
- `robots_relevant_to_cvortex`: not required for API path
- `rate_limits`: NOT PUBLICLY DOCUMENTED in collected evidence
- `browser_extension_feasibility`: fallback only
- `server_side_fetch_feasibility`: YES via API
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: not required for public postings
- `fallback_strategy`: career page/paste
- `security_notes`: tenant identifiers and returned HTML/text untrusted
- `confidence`: HIGH
- `review_after`: 2026-12-12
- `sources`: https://github.com/lever/postings-api

### Workday

- `source_type`: ATS/HCM career platform
- `official_api`: CUSTOMER_ONLY for tenant Recruiting APIs
- `api_type`: Workday REST/web services
- `api_documentation`: https://developer.workday.com/
- `public_or_private`: tenant/customer security context
- `authentication`: tenant integration security
- `oauth_support`: vendor supports auth mechanisms, but generic candidate vacancy retrieval use is NOT CONFIRMED
- `feeds`: tenant-specific integrations/RaaS possible
- `career_page_structure`: Workday-hosted external career sites, tenant-specific
- `allowed_retrieval_methods`: public structured data where exposed and permitted; manual/browser capture; customer API only with tenant authorization
- `documented_restrictions`: do not treat private/internal career-site endpoints as public API
- `terms_relevant_to_cvortex`: arbitrary cross-tenant server integration not supported by evidence
- `robots_relevant_to_cvortex`: per-career-site
- `rate_limits`: NOT PUBLICLY DOCUMENTED for generic use
- `browser_extension_feasibility`: MEDIUM, subject to site terms and capture only
- `server_side_fetch_feasibility`: LIMITED, public page only where permitted
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: CUSTOMER_ONLY, not ordinary job seeker authorization
- `fallback_strategy`: JSON-LD if present -> permitted page capture -> paste/file
- `security_notes`: no reverse engineering of tenant internals
- `confidence`: HIGH
- `review_after`: 2026-12-12
- `sources`: https://developer.workday.com/

### Ashby

- `source_type`: ATS career platform
- `official_api`: YES
- `api_type`: public Job Postings API
- `api_documentation`: https://developers.ashbyhq.com/docs/public-job-posting-api
- `public_or_private`: public job-board endpoint
- `authentication`: none for public endpoint
- `oauth_support`: not needed for public postings
- `feeds`: JSON API
- `career_page_structure`: job board name
- `allowed_retrieval_methods`: public posting API
- `documented_restrictions`: distinguish public posting API from authenticated customer APIs such as jobPosting.list
- `terms_relevant_to_cvortex`: use public endpoint only for public data
- `robots_relevant_to_cvortex`: not required for API path
- `rate_limits`: NOT PUBLICLY DOCUMENTED in collected evidence
- `browser_extension_feasibility`: fallback only
- `server_side_fetch_feasibility`: YES via documented public API
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: not required
- `fallback_strategy`: career page/paste
- `security_notes`: tenant board name validation
- `confidence`: HIGH
- `review_after`: 2026-12-12
- `sources`: https://developers.ashbyhq.com/docs/public-job-posting-api

### SmartRecruiters

- `source_type`: ATS career platform
- `official_api`: YES
- `api_type`: Posting API
- `api_documentation`: https://developers.smartrecruiters.com/docs/posting-api
- `public_or_private`: public posting data; protected/internal APIs require auth/scopes
- `authentication`: none for public Posting API data; auth for protected scopes
- `oauth_support`: exists in platform, but not required for public postings
- `feeds`: JSON API
- `career_page_structure`: company identifier + postings
- `allowed_retrieval_methods`: public Posting API
- `documented_restrictions`: do not request internal postings/scopes without authorization
- `terms_relevant_to_cvortex`: public published postings only
- `robots_relevant_to_cvortex`: not needed for documented API path
- `rate_limits`: NOT PUBLICLY DOCUMENTED in collected evidence
- `browser_extension_feasibility`: fallback only
- `server_side_fetch_feasibility`: YES via public API
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: not required for public postings
- `fallback_strategy`: career page/paste
- `security_notes`: external fields untrusted
- `confidence`: HIGH
- `review_after`: 2026-12-12
- `sources`: https://developers.smartrecruiters.com/docs/posting-api ; https://developers.smartrecruiters.com/docs/authentication

### Teamtailor

- `source_type`: ATS career platform
- `official_api`: CUSTOMER_ONLY
- `api_type`: REST API
- `api_documentation`: https://support.teamtailor.com/en/articles/5963369-use-our-teamtailor-api
- `public_or_private`: API key generated by Company Admin; public scope exposes public career data to authorized client
- `authentication`: API key
- `oauth_support`: NOT CONFIRMED for this use case
- `feeds`: UNKNOWN
- `career_page_structure`: Teamtailor-hosted career site
- `allowed_retrieval_methods`: employer-authorized API; public career page/manual where permitted
- `documented_restrictions`: arbitrary CVortex server cannot assume employer API key
- `terms_relevant_to_cvortex`: customer authorization required for API
- `robots_relevant_to_cvortex`: per site, NOT CONFIRMED globally
- `rate_limits`: NOT PUBLICLY DOCUMENTED in collected evidence
- `browser_extension_feasibility`: MEDIUM as user capture, subject to terms
- `server_side_fetch_feasibility`: LIMITED without employer API authorization
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: CUSTOMER_ONLY, not normal candidate OAuth
- `fallback_strategy`: public page/paste/file
- `security_notes`: never ask candidate for employer admin key
- `confidence`: HIGH
- `review_after`: 2026-12-12
- `sources`: https://support.teamtailor.com/en/articles/5963369-use-our-teamtailor-api

### Personio

- `source_type`: ATS career platform
- `official_api`: YES plus public XML feed when employer enables it
- `api_type`: XML career feed; Recruiting API for customer integrations
- `api_documentation`: https://support.personio.de/hc/en-us/articles/29375445597725-Frequently-asked-questions-on-XML-job-integration
- `public_or_private`: enabled XML feed public/no credentials; Recruiting API customer-controlled
- `authentication`: none for enabled XML feed
- `oauth_support`: not relevant to public XML feed
- `feeds`: YES, XML
- `career_page_structure`: `{account}.jobs.personio.com/xml` when enabled
- `allowed_retrieval_methods`: XML feed; public page/manual
- `documented_restrictions`: only employers with XML feed enabled expose it
- `terms_relevant_to_cvortex`: retain source/original apply link; re-check terms before production
- `robots_relevant_to_cvortex`: not needed for documented feed path
- `rate_limits`: exact limit NOT PUBLICLY DOCUMENTED; vendor recommends periodic synchronization
- `browser_extension_feasibility`: fallback only
- `server_side_fetch_feasibility`: YES for enabled feed
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: not required for XML
- `fallback_strategy`: career page/paste
- `security_notes`: XML parser hardening required later
- `confidence`: HIGH
- `review_after`: 2026-12-12
- `sources`: Personio XML integration support article above

### Remote OK

- `source_type`: remote job board
- `official_api`: YES
- `api_type`: public JSON + RSS feeds
- `api_documentation`: https://remoteok.com/faq
- `public_or_private`: public
- `authentication`: none
- `oauth_support`: NO/irrelevant
- `feeds`: JSON `https://remoteok.com/api`; RSS `https://remoteok.com/remote-jobs.rss`
- `allowed_retrieval_methods`: official feeds
- `documented_restrictions`: aggregators/public sharing should credit Remote OK and link original job post
- `terms_relevant_to_cvortex`: attribution/link-back
- `robots_relevant_to_cvortex`: API/feed path preferred
- `rate_limits`: NOT PUBLICLY DOCUMENTED in collected evidence
- `browser_extension_feasibility`: unnecessary
- `server_side_fetch_feasibility`: YES via feed
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: not required
- `fallback_strategy`: RSS/JSON -> paste
- `security_notes`: feed content untrusted
- `confidence`: HIGH
- `review_after`: 2026-12-12
- `sources`: https://remoteok.com/faq ; https://remoteok.com/legal

### We Work Remotely

- `source_type`: remote job board
- `official_api`: YES, but documented API is partner-oriented and Terms severely restrict use
- `api_type`: Jobs API with special token
- `api_documentation`: https://weworkremotely.com/api
- `public_or_private`: token/partner
- `authentication`: special token
- `oauth_support`: NO evidence
- `feeds`: UNKNOWN as a CVortex-safe retrieval mechanism
- `allowed_retrieval_methods`: only explicit permitted API use; otherwise user paste/manual
- `documented_restrictions`: Terms prohibit building a job advertising/job search service; scraping/copying/saving/storing WWR data is strictly prohibited; apply must route through WWR
- `terms_relevant_to_cvortex`: automated/persistent CVortex import conflicts with documented API terms absent permission
- `robots_relevant_to_cvortex`: irrelevant to override Terms
- `rate_limits`: vendor says limits exist; exact collected values not recorded here
- `browser_extension_feasibility`: NOT RECOMMENDED for scraping/storage
- `server_side_fetch_feasibility`: NO recommended path without permission
- `manual_import_feasibility`: YES as user-provided text, subject to product legal review
- `user_authenticated_import_feasibility`: NOT CONFIRMED
- `fallback_strategy`: user paste/manual metadata
- `security_notes`: do not bypass application route
- `confidence`: HIGH
- `review_after`: 2026-10-12
- `sources`: https://weworkremotely.com/api-terms-and-guidelines ; https://weworkremotely.com/api

### Dice

- `source_type`: job board
- `official_api`: no generic candidate retrieval API confirmed; employer/batch integrations exist
- `public_or_private`: contracted integrations
- `authentication`: contract-specific
- `oauth_support`: UNKNOWN
- `feeds`: employer integrations may exist; not candidate retrieval
- `allowed_retrieval_methods`: user paste/manual
- `documented_restrictions`: Terms prohibit unapproved retrieval/indexing/data mining and use for AI/model development; robots/retrieval mechanisms not approved by Dice are restricted
- `terms_relevant_to_cvortex`: automated vacancy ingestion not recommended
- `robots_relevant_to_cvortex`: does not override Terms
- `rate_limits`: UNKNOWN
- `browser_extension_feasibility`: HIGH RISK if it retrieves/indexes Dice content
- `server_side_fetch_feasibility`: NO recommended automated path
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: NOT CONFIRMED
- `fallback_strategy`: paste/file/manual metadata
- `security_notes`: avoid automated account interactions
- `confidence`: HIGH
- `review_after`: 2026-12-12
- `sources`: https://www.dice.com/about/terms-and-conditions

### ZipRecruiter

- `source_type`: job board
- `official_api`: PARTNER_ONLY for ATS/employer distribution
- `api_type`: Partner Jobs API
- `api_documentation`: https://www.ziprecruiter.com/partner/documentation/job-api/
- `public_or_private`: partner onboarding
- `authentication`: Basic API key
- `oauth_support`: sponsorship flows may use token exchange; this does not create candidate job-search access
- `feeds`: XML feed import for partners
- `allowed_retrieval_methods`: partner job distribution for approved partners; user paste/manual for CVortex candidate ingestion
- `documented_restrictions`: API direction is partner ATS -> ZipRecruiter, not public cross-market vacancy search
- `terms_relevant_to_cvortex`: do not misclassify partner API as candidate retrieval
- `robots_relevant_to_cvortex`: NOT CONFIRMED
- `rate_limits`: NOT PUBLICLY DOCUMENTED in collected evidence
- `browser_extension_feasibility`: UNKNOWN/LIMITED
- `server_side_fetch_feasibility`: no official generic candidate retrieval API confirmed
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: NOT CONFIRMED
- `fallback_strategy`: paste/file/manual metadata
- `security_notes`: partner credentials are secrets and must never be logged
- `confidence`: HIGH
- `review_after`: 2026-12-12
- `sources`: https://www.ziprecruiter.com/partner/documentation/ ; https://www.ziprecruiter.com/partner/documentation/job-api/

### Company career pages

- `source_type`: generic employer career pages / ATS-hosted pages
- `official_api`: varies
- `api_type`: ATS public APIs, XML/RSS/JSON feeds, JSON-LD `JobPosting`, static HTML
- `api_documentation`: source-specific
- `public_or_private`: varies
- `authentication`: usually none for public pages; ATS customer APIs separate
- `oauth_support`: source-specific
- `feeds`: source-specific
- `web_access`: YES for public careers
- `career_page_structure`: static HTML, JSON-LD JobPosting, ATS-hosted pages/widgets, feeds, custom sites
- `allowed_retrieval_methods`: prefer official public API/feed; then structured public data; HTML only where permitted; manual/browser capture fallback
- `documented_restrictions`: source-specific Terms and robots must be checked; do not infer permission from discoverable internal endpoints
- `terms_relevant_to_cvortex`: evaluate per source
- `robots_relevant_to_cvortex`: evaluate per host; robots is crawler policy, not authorization
- `rate_limits`: source-specific / UNKNOWN unless documented
- `browser_extension_feasibility`: often useful as user-side fallback, but must not bypass site restrictions
- `server_side_fetch_feasibility`: source-specific; SSRF-safe fetcher required before implementation
- `manual_import_feasibility`: YES
- `user_authenticated_import_feasibility`: source-specific
- `fallback_strategy`: API/feed -> JSON-LD -> permitted HTML -> browser capture -> paste/file/manual metadata
- `security_notes`: URL input creates SSRF risk; HTML creates XSS/prompt-injection risk
- `confidence`: HIGH for strategy; per-site confidence varies
- `review_after`: 2026-12-12
- `sources`: https://schema.org/JobPosting ; source-specific vendor documentation

## Evidence boundary

No undocumented endpoint discovered in browser traffic is treated as an integration API. No API existence is inferred from a website’s own frontend calls. Exact rate limits are `NOT PUBLICLY DOCUMENTED` unless an official source in this research run stated them.
