---
title: Recruiting AI
status: reviewed
owner: project
created: 2026-09-12
updated: 2026-09-12
research_ids: [R13-02, R13-03, R14-07]
sources_checked_at: 2026-09-12
confidence: HIGH
review_after: 2027-01-15
tags: [research, recruitment, ai, regulation]
---

# Recruiting AI

## Documented use cases

FACT: UK ICO reports AI recruitment tooling used to source candidates, summarise CVs and score applicants. In March 2026 ICO also described automated recruitment systems analysing applications for words, skills or qualifications, then scoring, ranking or filtering candidates; some workflows can reject below-threshold applications before human review.

EVIDENCE:
- https://ico.org.uk/about-the-ico/media-centre/news-and-blogs/2024/11/ico-intervention-into-ai-recruitment-tools-leads-to-better-data-protection-for-job-seekers/
- https://ico.org.uk/about-the-ico/media-centre/news-and-blogs/2026/03/here-s-what-jobseekers-need-to-know-about-automated-recruitment-decisions/

Confidence: HIGH for existence, not prevalence at every employer.

## Data protection / fairness

FACT: ICO’s audit made almost 300 recommendations around fairness, data minimisation and explaining use of candidate data.

INTERPRETATION: CVortex Recruitment KB should not only optimize for machine matching. It should track data/privacy and automated-decision risks as part of market context.

## EU regulatory context

FACT: EU AI Act Annex III identifies AI systems used for recruitment/selection, including targeting job ads, analysing/filtering job applications and evaluating candidates, as high-risk use cases under the Act’s framework.

EVIDENCE: Regulation (EU) 2024/1689, Annex III: https://eur-lex.europa.eu/eli/reg/2024/1689/oj

RECOMMENDATION: treat EU recruiting-AI findings as freshness-sensitive regulatory data; re-check application dates and amendments before product decisions. CVortex is a candidate-side assistant, but Employer Research and Recruitment KB must not assume all AI hiring tooling is unregulated or opaque by design.

Confidence: HIGH on classification; implementation-date details should be revalidated at Phase 05/06 if materially relevant.

## US context

Public enforcement guidance from EEOC/DOJ has warned that algorithmic hiring tools can create disability discrimination risks and may require accommodation processes.

RECOMMENDATION: US market research should preserve accessibility/discrimination context rather than optimizing solely for automated screening.

Confidence: MEDIUM-HIGH; detailed jurisdiction/state rules were outside Phase 04 scope.

## What CVortex should not claim

- that every company uses AI screening;
- that AI always makes the final decision;
- that an LLM can predict a company’s private ranking algorithm;
- that a generated resume can “beat the ATS” with a guaranteed formula;
- that a vendor’s documented score is transferable to another ATS.

## Recruitment Knowledge Base implication

A finding should be scoped at least by:

```text
market
industry
role_family
employer_type
vendor/system when known
finding
source
confidence
valid_from
review_after
```

AI recruiting capabilities and regulation are freshness-sensitive; review at least quarterly or on major policy/vendor changes.
