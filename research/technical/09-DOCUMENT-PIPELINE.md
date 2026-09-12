---
status: research-complete
date: 2026-09-12
phase: 03-technical-research
owner: CVortex
architecture_decision: none
---

# DOCX Generation and LibreOffice Headless

## Goal

Validate the owner-approved deterministic pipeline: structured resume content -> DOCX template rendering -> LibreOffice headless -> PDF.

## PHPWord evidence

- **[E1]** PHPWord `TemplateProcessor` operates on OOXML DOCX templates with macros.
- It supports scalar replacement, images, block and row cloning, complex values/blocks and save/saveAs operations.
- This fits CVortex because presentation can live in versioned Word templates while LLM output remains structured data.

## Options for DOCX

### A. PHPWord TemplateProcessor

Pros:
- Pure PHP, same runtime as core backend.
- Natural template workflow for resume layouts.
- Supports repeating sections and images.

Risks:
- Complex Word layout edge cases can require OOXML-level workarounds.
- Template macros and Word editing behavior need disciplined template QA.

### B. Raw OOXML manipulation

Pros: maximum control.

Cons: high maintenance and test burden. Use only for gaps proven impossible with PHPWord.

### C. Separate JS/commercial template renderer

Pros: alternative template engines can be powerful.

Cons: extra runtime/licensing/operational surface. No current evidence that CVortex needs it.

## LibreOffice evidence

- **[E2]** LibreOffice 26.2.6 was released 2026-09-04 as the sixth maintenance update of the 26.2 line.
- **[E3]** LibreOffice 26.8.0 is a newer major line released in August 2026.
- **[E4]** The official CLI supports `--convert-to` with export filters, including PDF writer export.

## Candidate pipeline

1. LLM/deterministic logic produces schema-validated `StructuredResume` content.
2. Renderer fills a versioned DOCX template using PHPWord.
3. Result is validated as a readable ZIP/OOXML document and stored with metadata/hash.
4. Isolated document worker executes LibreOffice headless conversion to PDF.
5. PDF is validated for successful exit, existence, non-zero size and render regression fixtures.
6. Both source DOCX and generated PDF preserve provenance to template/content versions.

## LibreOffice version strategy

Do **not** follow the newest major automatically. Candidate approach:

- start compatibility testing with 26.2.6 maintenance branch because it has accumulated fixes;
- separately test 26.8.x;
- pin the exact container version that passes a representative resume render suite;
- upgrade only through the same regression suite.

This is one of those places where “latest” is not synonymous with “please rearrange page 2 of my CV”.

## Security / isolation

- Uploaded source documents are untrusted input.
- Run office conversion in a constrained worker/container with controlled directories, resource/time limits and no unnecessary network access.
- Use generated unique working directories and canonical path checks.
- Never shell-concatenate user filenames into commands.
- Remove temporary files reliably.

## Decision status

DOCX + LibreOffice PDF is owner-approved direction. PHPWord and exact LibreOffice line remain decision candidates.

## Confidence

High on pipeline feasibility; medium-high on PHPWord template fidelity until real CVortex templates are tested.

## Citations

- **[E1] PHPWord Template Processing**, accessed 2026-09-12: https://phpoffice.github.io/PHPWord/usage/template.html
- **[E2] LibreOffice 26.2.6 release**, The Document Foundation, 2026-09-04: https://blog.documentfoundation.org/blog/2026/09/04/libreoffice-26-2-6/
- **[E3] LibreOffice releases**, accessed 2026-09-12: https://www.libreoffice.org/download/release-notes/
- **[E4] LibreOffice File Conversion Filters / CLI documentation**, accessed 2026-09-12: https://help.libreoffice.org/latest/en-US/text/shared/guide/convertfilters.html
