---
title: Phase 06 Conceptual Data Design
status: awaiting-independent-review
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [data, erd, ownership, provenance, audit, phase-06]
related: ["[[../02-Architecture/Phase-06-System-Design|System Design]]", "[[../06-AI/Phase-06-AI-Design|AI Design]]"]
---

# Phase 06 Conceptual Data Design

This is a conceptual model, not a physical schema or migration plan.

```mermaid
erDiagram
 USER ||--o{ INVITATION : creates
 USER ||--|| CAREER_PROFILE : owns
 CAREER_PROFILE ||--o{ CAREER_TRACK : has
 CAREER_PROFILE ||--o{ CAREER_FACT : contains
 USER ||--o{ CLAIM : owns
 CAREER_FACT ||--o{ CLAIM_EVIDENCE : supports
 CLAIM ||--o{ CLAIM_EVIDENCE : cites
 USER ||--o{ VACANCY : owns
 VACANCY ||--o{ VACANCY_SNAPSHOT : preserves
 VACANCY ||--o{ VACANCY_REQUIREMENT : has
 COMPANY ||--o{ VACANCY : offers
 USER ||--o{ APPLICATION : owns
 VACANCY ||--o{ APPLICATION : evaluated_for
 APPLICATION ||--o{ RESUME_VERSION : uses
 APPLICATION ||--o{ COVER_LETTER : uses
 APPLICATION ||--o{ CONVERSATION : has
 APPLICATION ||--o{ INTERVIEW : has
 USER ||--o{ GENERATED_CONTENT : owns
 GENERATED_CONTENT ||--o{ GENERATED_CONTENT_CLAIM : uses
 CLAIM ||--o{ GENERATED_CONTENT_CLAIM : is_used_in
 APPLICATION ||--o{ GENERATED_CONTENT : prepares
 USER ||--o{ GENERATED_FILE : owns
 GENERATED_FILE ||--o{ FILE_VERSION : versions
 SKILL ||--o{ SKILL_VERSION : versions
 WORKFLOW ||--o{ LLM_RUN : invokes
 SKILL_VERSION ||--o{ LLM_RUN : executes
 USER ||--o{ LLM_RUN : owns
 USER ||--o{ AUDIT_EVENT : acts
```

## Entities, lifecycle and privacy

| Group | Entities and lifecycle | Provenance / audit / privacy |
|---|---|---|
| Identity | User, Invitation, UserSetting; invitation `created/revoked/used/expired` | invitation events audited; identity/setting data is private PII |
| Career | CareerProfile, CareerTrack, EmploymentHistory, CareerFact, Achievement, Education, Language, Skill, SalaryExpectation; fact `PENDING/CONFIRMED/REJECTED/DEPRECATED` | source, excerpt, extraction/confirmation actor and time required; private sensitive PII |
| Claims/content | Claim, ClaimEvidence, GeneratedContent, GeneratedContentClaim, ResumeTemplate, ResumeVersion, ResumeChange, CoverLetter, GeneratedFile, FileVersion | `Claim` and `GeneratedContent` are private user-owned aggregates; `GeneratedContentClaim` records a Claim's use in a concrete generated artifact, not ownership of the Claim; every candidate-facing version/content is traceable through valid confirmed evidence; approval/render/file events audited |
| Employer/workflow | Company, CompanyContact, CompanyResearch, Vacancy, VacancySource, VacancySnapshot, VacancyRequirement, Application, ApplicationStatusHistory, Conversation, ConversationMessage, ConversationFact, Interview, InterviewQuestion | raw snapshots preserve external source separately from normalized data; private per user unless explicitly system reference data |
| Research | ResearchSource, ResearchFinding, ResearchRule | source/version/date preserved; access is policy-specific, not automatically public |
| AI | LlmProvider, ModelPolicy, Skill, SkillVersion, Agent, Workflow, PromptVersion, LlmRun, EvalRun | version, selected policy/actual model, context provenance and validation result retained; credentials never stored in runs |
| Audit | AuditEvent append-oriented | actor, target, action, timestamp, correlation metadata; minimal PII, no secret/raw credential |

## Relationships, ownership and deletion

Career Profile, Vacancy, Application, Conversation, Interview, generated file, generated candidate content, Claim and LLM run are private aggregates directly owned by a User; their children inherit that owner through the aggregate. `ClaimEvidence` is a child of `Claim` and has an explicitly inherited owner. It may reference only a `CareerFact` with the same owner. `GeneratedContentClaim` is a conceptual link-entity between `GeneratedContent` and `Claim`: one generated artifact may use many Claims and one Claim may be reused by many generated artifacts. The link records that specific use and is authorised only when both endpoints have the same owner. This establishes the conceptual invariant for every admissible provenance chain:

```text
GeneratedContent.owner = GeneratedContentClaim.owner = Claim.owner = CareerFact.owner

GeneratedContent(owner=A) → GeneratedContentClaim → Claim(owner=B) is invalid
Claim(owner=A) → ClaimEvidence → CareerFact(owner=B) is invalid
```

`GeneratedContent` is the ownership/provenance boundary for candidate-facing material, including the generated state of a ResumeVersion or CoverLetter. An Application may associate only same-owner generated content. A Claim may be reused only through a same-owner `GeneratedContentClaim`; the link-entity cannot create cross-user reuse. Company may be shared/system reference only when no private employer context is exposed; otherwise a user-owned company context is used. Provider, policy, Skill, workflow, prompt and research rule may be system-owned; a user credential remains private.

The server derives acting identity from authenticated context and resolves the aggregate owner through trusted relationships before authorization. It scopes every direct/nested lookup, ClaimEvidence relation, `GeneratedContentClaim` relation, queue payload, cache key, file access and AI context; client-provided `user_id` is never authorization evidence. ContextBuilder selects Claims/CareerFacts only after this server-side same-owner authorization. Admin capability does not grant implicit private-data access: an exceptional authorised action must be explicit and audited. Cross-user enumeration returns no resource information.

Deletion is lifecycle-driven: approved artifacts, raw sources, provenance and audit records are retained according to policy or anonymised/tombstoned where legally required; they are not blindly cascade-deleted. File binaries are removed only after authorised metadata/lifecycle processing and hash-aware reconciliation.

## Provenance vs audit

Provenance answers why content exists: `Source → Extracted fact → human confirmation → CareerFact → ClaimEvidence → Claim → GeneratedContentClaim → Generated Content`; it carries source type/id/version/snapshot, excerpt/reference, extractor/Skill/Prompt version, policy/actual model, actors and timestamps. A Claim is valid only when all supporting facts are same-owner and `CONFIRMED`; a Claim with absent or invalid provenance is invalid and blocks generated content. The Truth Guard identifies the Claims actually used by a specific generated artifact through its `GeneratedContentClaim` links, then applies the same STRICT provenance and confirmation checks. Audit answers who did what to a system resource and is append-oriented; it is not evidence for truth.

## Audit event matrix

`AuditEvent` is append-oriented and separate from provenance. Every record conceptually stores actor, target, action, timestamp and correlation metadata (request/workflow/job identifier as applicable); it minimizes PII and never stores secrets or raw credentials.

| Area | Minimum audit-worthy events |
|---|---|
| Invitations | invitation created; revoked; used |
| Career Facts | Career Fact created; confirmed; edited; rejected; deprecated |
| Claims | Claim created; changed; invalidated or deprecated when that lifecycle transition occurs |
| Generated/application artifacts | ResumeVersion approved; CoverLetter approved; Application status changed |
| Credentials | LLM credential added; rotated; deleted |
| Security/administration | critical authorization allow/deny operations; role or admin operations; credential-resolution failure; security-policy changes; exceptional administrative private-data access |
