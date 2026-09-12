---
title: Recruitment Market Comparison — RU / EU / US / UK
status: reviewed
owner: project
created: 2026-09-12
updated: 2026-09-12
research_ids: [R13-05]
sources_checked_at: 2026-09-12
confidence: MEDIUM
review_after: 2027-03-12
tags: [research, recruitment, markets, cv]
---

# Recruitment Market Comparison — RU / EU / US / UK

## Rule

These markets must not be collapsed into a single “international CV” rule set. Evidence is uneven, especially inside the EU and across employer types.

## Comparison

| Dimension | RU | EU | US | UK |
|---|---|---|---|---|
| CV/resume platform norms | HeadHunter profile/resume conventions materially shape applications | heterogeneous; Europass is one official cross-EU tool, not universal employer standard | employer/ATS specific; anti-discrimination/privacy context matters | NCS gives explicit CV guidance |
| Personal info | HH profiles can include personal fields; exact employer expectation varies | GDPR/privacy context and national norms vary | protected-characteristic/discrimination concerns argue against unnecessary personal data | NCS says do not include age/DOB, marital status, nationality |
| Photo | HH supports photo; not universal requirement | Europass supports photo but this is not evidence all EU markets expect it | no universal finding recorded in this phase | no NCS requirement established in collected evidence |
| Length | no universal evidence for exact page count | no universal EU page rule established | no universal rule established | NCS advice is guidance, not universal ATS limit |
| Cover letter | HH treats as useful complement | country/employer dependent | employer/application dependent | NCS recommends one; platform requirements still vary |
| ATS/automation | used by many large employers/platforms; exact prevalence not established | EU AI/privacy regulation especially relevant | vendor/employer specific, federal/state compliance context | ICO provides current evidence of automated recruitment use |
| Language | vacancy/employer specific | country + multinational context | usually role/employer specific | role/employer specific |
| Salary expectations | platform/company specific | country/company specific | company/jurisdiction specific | company specific |
| Privacy | personal data handling relevant | GDPR strongly relevant | anti-discrimination/privacy rules vary by law/state | UK GDPR/data-protection and ICO guidance relevant |

## Sources and evidence quality

### UK — HIGH/MEDIUM

UK National Careers Service advises CV structure and states not to include age/date of birth, marital status or nationality.

Source: https://nationalcareers.service.gov.uk/careers-advice/cv-sections

NCS recommends a covering letter with a CV, but that is guidance rather than evidence of universal employer requirement.

Source: https://nationalcareers.service.gov.uk/careers-advice/covering-letter

### RU — MEDIUM

HeadHunter product/help content demonstrates Russian job-search platform conventions, including resume/profile fields and cover-letter workflow. These conventions are platform-specific and should not be promoted into universal Russian law or employer behavior.

Sources: https://hh.ru/ ; https://feedback.hh.ru/knowledge-base/article/1846

### EU — MEDIUM/LOW for universal claims

Europass provides official EU CV tooling and customizable templates. It is evidence of an available cross-EU format, not evidence that every EU employer prefers it or that photo/length expectations are uniform.

Source: https://europass.europa.eu/en/create-europass-cv

RECOMMENDATION: model country-level overrides when evidence justifies them; do not create one `EU` formatting policy beyond broad privacy/regulatory context.

### US — MEDIUM for compliance context, LOW for one formatting standard

US hiring guidance/regulation demonstrates protected-characteristic and accessibility/discrimination concerns around employment and automated selection, but Phase 04 did not establish a single authoritative resume-format rule applicable to all US employers.

RECOMMENDATION: keep US resume advice evidence-scoped and employer/platform aware; do not infer a universal one-page/no-photo rule solely from folklore.

## Product implication

Recruitment Knowledge Base should support:

```text
market -> optional country/region -> employer type -> role family -> vendor/system -> finding
```

A rule can be `HIGH` confidence for one market/vendor and `UNKNOWN` elsewhere.
