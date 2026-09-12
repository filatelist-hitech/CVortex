---
title: ADR-0016 — Deterministic DOCX-to-PDF Rendering Pipeline
status: accepted
decision_nature: OWNER_CONSTRAINT
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, documents, docx, pdf]
related: [ADR-0003, ADR-0009, ADR-0015, ADR-0017]
---

# ADR-0016 — Deterministic DOCX-to-PDF Rendering Pipeline

## Context

CVortex must produce reviewable resume documents without allowing an LLM to invent binary layout or bypass provenance. The owner-approved direction is DOCX generation followed by LibreOffice headless PDF conversion.

## Decision Drivers

- separation of semantic content and presentation;
- deterministic, versioned and regression-testable output;
- common employer-facing formats;
- isolated handling of complex document tooling.

## Options

1. Structured content → deterministic DOCX renderer → LibreOffice PDF → validation.
2. Ask an LLM to generate DOCX/PDF binaries or layout markup directly.
3. HTML/CSS-to-PDF only.
4. Commercial/separate renderer now.

## Comparison

| Option | Determinism | DOCX support | Testability | Complexity |
|---|---|---|---|---|
| DOCX + LibreOffice | High | Native | High | Moderate |
| LLM binary | Low | Unreliable | Low | Unsafe |
| HTML/PDF only | High | None | High | Medium |
| Separate renderer | Varies | Varies | Medium | High now |

## Decision

Use this boundary:

```text
Career Facts + Vacancy + Resume Strategy
                    ↓
          Structured Resume Content
                    ↓
       Deterministic Template Renderer
                    ↓
                  DOCX
                    ↓
       LibreOffice headless conversion
                    ↓
                   PDF
                    ↓
                Validation
```

LLMs may propose schema-valid structured content subject to Truth Guard and approval; they never generate final binary documents directly. Templates/generator and converter are versioned. DOCX and PDF retain provenance to structured content and template versions. Conversion runs as background work with validation.

PHPWord TemplateProcessor is a research-backed candidate, not frozen until a real template fidelity spike. Exact LibreOffice version is pinned later through regression testing.

## Consequences

### Positive

- repeatable output and clear provenance;
- layout changes do not mutate semantic content;
- render regressions can be tested independently of LLM prose.

### Negative / Risks

- office rendering has font/layout/version variability;
- template maintenance requires QA fixtures;
- conversion adds a worker and temporary-file lifecycle.

## Security and Validation Impact

Conversion runs with controlled working directories, generated filenames, time/resource limits and no unnecessary network. Validate OOXML structure, conversion exit, non-empty output and representative layout/extracted content. Uploaded source files remain untrusted.

## Reversibility and Revisit Triggers

The structured-content boundary allows a renderer replacement. Revisit after a measured fidelity, format or licensing blocker; replacement requires a new ADR if the canonical output pipeline changes.

## References

- [PROJECT.md](../../PROJECT.md).
- [Document Pipeline](../../research/technical/09-DOCUMENT-PIPELINE.md).
- [Testing Stack](../../research/technical/08-TESTING-STACK.md).
- [Resume Practices](../../research/recruitment/resume-practices.md).

## Supersedes

None.

## Superseded By

None.
