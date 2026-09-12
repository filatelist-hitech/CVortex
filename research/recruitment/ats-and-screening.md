---
title: ATS, Parsing, Matching and Screening
status: reviewed
owner: project
created: 2026-09-12
updated: 2026-09-12
research_ids: [R13-01, R13-02, R13-03]
sources_checked_at: 2026-09-12
confidence: HIGH
review_after: 2026-12-12
tags: [research, recruitment, ats, screening, matching]
---

# ATS, Parsing, Matching and Screening

## Core finding

There is no evidence for a universal cross-ATS score or one universal screening formula. Documented systems combine different mechanisms: structured field extraction, keyword/Boolean filters, explicit skills, inferred skills, location, required questions, vendor-specific matching/ranking and human review.

## Documented evidence

### LinkedIn Recruiter

FACT: Skills filtering uses explicit profile skills, skill keywords, resume skills and inferred/implicit skills. Keyword skill queries can expand to related descriptions/synonyms; LinkedIn’s own example says `SaaS` can surface `Software as a service`.

EVIDENCE: https://www.linkedin.com/help/recruiter/answer/a596630

FACT: Recruiter search has Boolean-capable filters for job titles, location, companies, skills/assessments, schools, industries and spoken languages.

EVIDENCE: https://www.linkedin.com/help/linkedin/answer/a415295/use-boolean-to-filter-search-results-in-recruiter

FACT: Resume Search combines AI/ML extraction with LinkedIn skill data; irregular resume format or file type can reduce extraction confidence.

EVIDENCE: https://www.linkedin.com/help/recruiter/answer/a770588/discover-candidates-with-resume-search-in-recruiter

INTERPRETATION: exact terms matter, but synonyms/adjacent skills and semantic/contextual inference also exist. “Repeat every keyword N times” is not a defensible universal strategy.

Confidence: HIGH.

### Indeed screening

FACT: Indeed documents employer screening capabilities where required questions can affect progression, and Smart Screening can evaluate employer-defined mandatory/preferred criteria against application/resume information. Indeed’s own Smart Fit score is vendor/product specific.

EVIDENCE: https://www.indeed.com/hire/resources/howtohub/what-is-indeed-smart-screening ; https://www.indeed.com/hire/resources/howtohub/how-to-use-screener-questions-on-indeed

INTERPRETATION: a score can be real inside a particular vendor workflow, but that does not justify a fabricated universal `ATS Score` in CVortex.

Confidence: HIGH.

### Automated recruitment decisions

FACT: UK ICO describes systems analysing application content, skills/qualifications, scoring/ranking/filtering, and in some cases rejection before human review.

EVIDENCE: https://ico.org.uk/about-the-ico/media-centre/news-and-blogs/2026/03/here-s-what-jobseekers-need-to-know-about-automated-recruitment-decisions/

Confidence: HIGH for existence of these workflows; LOW for any claim that every employer/ATS uses them.

## Typical workflow classification

```text
application
→ ATS ingestion
→ parsing / structured extraction
→ filtering/search/matching
→ recruiter or automated screening
→ technical/role assessment
→ interviews
→ decision
```

- `application → ingestion`: COMMON.
- parsing/structured extraction: COMMON in ATS/recruiting tools, implementation vendor-specific.
- keyword/Boolean search: COMMON capability, exact syntax/vendor behavior varies.
- knockout/screener questions: COMMON but company/job specific.
- semantic/inferred skill matching: VENDOR-SPECIFIC, documented by LinkedIn and others.
- numeric ranking/fit scores: VENDOR-SPECIFIC.
- automatic rejection: COMPANY/VENDOR-CONFIGURATION SPECIFIC.
- human recruiter review: common but not guaranteed before every automated decision.

## Matching dimensions supported for CVortex research

The evidence supports representing matching as explainable dimensions rather than one synthetic score:

- Technical fit;
- Experience fit;
- Domain fit;
- Seniority;
- Language;
- Location;
- Work format;
- Salary;
- Hard requirement gaps;
- Potential screening risks.

Each dimension should eventually cite vacancy requirements and confirmed candidate facts. This is a recommendation candidate, not an implementation decision.

## Hard requirements

High-value screening fields documented across vendor workflows include:

- required experience/qualifications;
- licenses/certifications where applicable;
- language;
- location;
- work authorization;
- security/eligibility questions;
- employer-defined mandatory screener answers.

CVortex should distinguish `hard requirement`, `preference`, and `unknown` rather than assuming every listed requirement is knockout.

## Claims not supported as universal facts

- `ATS Score: 94%` across systems — NOT SUPPORTED.
- exact keyword-density threshold — NOT SUPPORTED.
- “ATS rejects any CV with columns” — NOT SUPPORTED as universal claim.
- “All applicants are ranked by AI before a recruiter sees them” — NOT SUPPORTED.
- one universal resume parser behavior — NOT SUPPORTED.

## Recommendation candidate

Recruitment Knowledge Base should store vendor/market/employer-scoped findings with confidence and validity window. Match analysis should be evidence-linked and multi-dimensional, with vendor-specific score explanations only when the actual target system documents that score.
