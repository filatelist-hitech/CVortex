---
title: Resume Practices and ATS Compatibility
status: reviewed
owner: project
created: 2026-09-12
updated: 2026-09-12
research_ids: [R13-01, R13-03, R13-05, R13-07]
sources_checked_at: 2026-09-12
confidence: MEDIUM
review_after: 2027-03-12
tags: [research, recruitment, resume, ats]
---

# Resume Practices and ATS Compatibility

## Evidence grading

### Strong evidence

1. **Resume data is parsed/extracted by recruiting systems.** LinkedIn documents AI/ML extraction of experience and skills from candidate resumes.
2. **Irregular format/file type can reduce parser confidence.** LinkedIn explicitly states this can make extraction harder.
3. **Recruiter search uses more than exact text.** LinkedIn documents explicit, implicit, resume-derived and related/synonymous skills.

Source: https://www.linkedin.com/help/recruiter/answer/a770588/discover-candidates-with-resume-search-in-recruiter ; https://www.linkedin.com/help/recruiter/answer/a596630

### Moderate evidence / recommendation

A simple ATS-oriented resume template with clear semantic structure and standard document formats is a sensible portability strategy across parsers.

Interpretation: because parser behavior differs, CVortex should provide a conservative template rather than assert that one layout is guaranteed to work everywhere.

### Weak/common advice without sufficient universal evidence

The research did **not** establish as universal facts:

- exactly one page is always optimal;
- exactly two pages is always optimal;
- all tables break ATS parsing;
- all columns break ATS parsing;
- icons are always rejected;
- headers/footers are always ignored;
- a fixed keyword density improves ranking;
- repeating a skill a specific number of times guarantees progression.

These may be vendor/template-specific risks and should not become universal CVortex rules without stronger evidence.

## Content findings

Evidence supports preserving:

- clear chronology;
- explicit job titles/companies/dates;
- skills in context as well as a structured skills section;
- role-relevant terminology and common synonyms where truthful;
- measurable outcomes where the Career Fact Base actually contains them;
- unambiguous standard section structure.

Truth-first remains dominant: a missing skill keyword is not permission to invent experience.

## File formats

Vendor parsing capabilities vary. DOCX/PDF are common application formats, but CVortex must not promise equivalent parsing in every ATS. Deterministic DOCX/PDF generation should be validated against representative parsers later with an eval dataset.

## Visual complexity

RECOMMENDATION candidate: ship an `ATS-oriented` simple document template as a conservative baseline and treat richer visual templates as separate presentation options. Do not label it “ATS-proof”.

Confidence: MEDIUM.

## Keyword strategy

Recommended future behavior:

1. extract exact skills/terms from the vacancy;
2. map verified synonyms/adjacent skills;
3. match only to confirmed Career Facts;
4. surface hard gaps separately;
5. recommend wording changes that improve clarity without changing truth;
6. never invent hidden thresholds.

This is supported by documented recruiter search/matching behavior, but exact ranking weights remain vendor/company specific.
