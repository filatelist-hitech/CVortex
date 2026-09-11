---
title: Glossary
status: accepted
owner: product-owner
created: 2026-09-12
updated: 2026-09-12
tags:
  - product
  - glossary
  - terminology
related:
  - "[[../00-Home/CVortex|CVortex]]"
  - "[[Vision]]"
  - "[[Principles]]"
  - "[[Scope]]"
---

# Glossary

This glossary defines product language for early project phases. Later architecture and data-design phases may add implementation-level precision without silently changing the product meaning.

| Term | Meaning |
|---|---|
| **CVortex** | Personal Job Search OS that combines career facts, vacancies, employer history, research, application materials, interviews, and outcomes in one contextual system. |
| **Career Fact** | Atomic statement about the candidate's real experience, skills, education, achievements, constraints, or history. Only confirmed facts may support employer-facing generated claims. |
| **Career Fact Base** | Canonical source of truth for candidate facts across all Career Tracks and applications. |
| **Pending Fact** | Potential Career Fact extracted or proposed by AI but not yet confirmed by the user. It must not be treated as established candidate truth. |
| **Confirmed Fact** | Career Fact explicitly accepted as true and eligible to support claims. |
| **Career Track** | Positioning lens such as QA, AQA, backend/API QA, developer, or AI automation. A track changes relevance and emphasis, not the underlying biography. |
| **Claim** | Candidate-facing or employer-facing assertion constructed from one or more confirmed Career Facts. Claims preserve semantic traceability to their evidence. |
| **Generated Content** | Concrete wording produced for a resume, cover letter, application answer, interview preparation, or similar artifact. It should trace to Claims and then to confirmed Career Facts. |
| **Truth Guard** | Cross-cutting validation concept that prevents unsupported candidate statements and checks provenance and important conflicts. It is a validation responsibility, not a conversational persona. |
| **Employer Memory** | Employer-specific context containing prior vacancies, applications, claims, conversations, salary expectations, work-format statements, interviews, and related history. |
| **Employer Consistency Check** | Validation step that compares new employer-facing content with confirmed prior statements to the same employer and surfaces or blocks meaningful contradictions. |
| **Vacancy** | Normalized job opportunity being evaluated by the user. |
| **Vacancy Source** | Origin of vacancy data, such as pasted text, uploaded file, approved URL source, or a later browser integration. |
| **Vacancy Snapshot** | Preserved source representation of a vacancy at a point in time so normalization does not erase the original input. |
| **Vacancy Requirement** | Normalized responsibility, requirement, preference, constraint, or qualification extracted from a vacancy. |
| **Match** | Structured comparison of vacancy requirements against confirmed candidate context. Match should be multidimensional and explainable. |
| **Should I Apply** | Decision-support result that explains whether a vacancy should be prioritized, considered, deprioritized, or skipped. It is not an automatic application action. |
| **Application** | User-specific record connecting a vacancy and employer with the prepared/sent materials, status history, conversations, interviews, and outcome. |
| **Resume Version** | Immutable or versioned resume content prepared for a particular context or application. |
| **Resume Recommendation** | Proposed resume change showing what to change and why, with supporting evidence and risk where applicable. It requires user review. |
| **Cover Letter** | Generated employer-facing letter based on vacancy context, confirmed facts, selected positioning, and Employer Memory. |
| **Research Source** | External evidence used to support a technical, product, integration, hiring, or market finding. |
| **Research Finding** | Evidence-backed conclusion derived from one or more Research Sources. |
| **Research Rule** | Versioned recommendation or operational rule derived from research, with scope, confidence, and review lifecycle. |
| **LLM Provider** | Adapter responsible for communicating with a specific model provider. Product domain logic should not depend directly on it. |
| **Model Policy** | Rules for selecting capability/cost tiers and escalation behavior for a task. Concrete provider/model mappings are changeable configuration. |
| **Skill** | Reusable runtime AI capability with a defined purpose, input/output contract, validation, and version. Runtime product Skills are distinct from development-agent instructions. |
| **Agent** | Orchestrator used when a task requires coordination of multiple Skills, tools, or steps. Agents should not be created merely as personas. |
| **Workflow** | Explicit sequence of deterministic and/or AI-assisted steps that produce a product outcome. |
| **Tool** | Deterministic capability an Agent or Workflow may invoke, such as retrieval, validation, storage, or document rendering. |
| **Context Builder** | Concept responsible for selecting only relevant context for an AI task instead of sending the entire user history. |
| **Human Approval** | Mandatory user review/decision gate before important generated content or application actions become final. |
| **Untrusted Input** | External content such as vacancies, recruiter messages, websites, and imported documents. It is treated as data, never as trusted agent instruction. |
| **Application Package** | The reviewed set of materials prepared for an application, such as a tailored resume version, cover letter, supporting answers, and associated provenance. |
