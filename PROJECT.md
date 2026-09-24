# CVortex

## Product Purpose

CVortex is a personal Job Search OS.

Its purpose is to help users prepare truthful, relevant, traceable and internally consistent job applications using confirmed career information, vacancy context, employer history and research.

## Product Principles

### Truth-first

Never invent candidate experience, skills, achievements, responsibilities or other career facts.

### Traceability

Generated candidate-facing content must be traceable through:

`Generated Content → Claims → confirmed Career Facts`

### Consistency-first

Statements made to the same employer must remain consistent with previous applications, conversations and confirmed facts.

### Human Approval

CVortex does not automatically submit applications or send employer communications without explicit user approval.

### Deterministic Before AI

If a problem can be reliably solved with normal code, schema validation, rules or SQL, prefer that over LLM use.

### Provider-independent AI

AI architecture separates Provider, ModelPolicy, Skill, Agent, Workflow and Tool. Business logic must not depend directly on one LLM provider.

### Security-first

Vacancies, recruiter messages, websites and uploaded documents are untrusted input.

### Documentation-first

Important architecture decisions are documented using ADRs. Project documentation is normal Markdown stored in Git and usable as an Obsidian Vault.

### No Premature Complexity

Do not introduce without demonstrated need: Kubernetes, microservices, Kafka, event sourcing, standalone vector databases, GraphQL, native mobile applications or fine-tuning.

## Approved Stack Direction

These directions are approved but do not imply fixed dependency versions.

- Backend: PHP, Laravel, API-first architecture.
- Frontend: Next.js, React, TypeScript, responsive PWA.
- Data: PostgreSQL.
- Queue/cache: Redis + Laravel Horizon.
- Web: Nginx.
- Documents: DOCX templates/generation + LibreOffice headless PDF conversion.
- Design: Figma is the reviewed visual/component source; Git-held design tokens are the machine-readable authority.
- Documentation: Markdown + Git + Obsidian-compatible structure.

## Deployment Direction

Initial deployment is local-first on Mac through Docker Compose. The architecture must permit later migration to VPS/cloud infrastructure without unnecessary core rewrites.

## API-first

Desktop web, responsive PWA, future mobile clients and future browser integrations consume the shared application API.

## Access Model

Registration is invite-only. The system is multi-user. Initial roles are `admin` and `user`. Private user data is isolated between users; admin status is not an implicit private-data bypass.

## AI Architecture

CVortex uses provider-independent runtime AI. Concrete providers, models, model mappings and pricing are configuration/research concerns rather than permanent architecture facts.

Canonical runtime/product LLM Skills live under `/runtime-ai/` according to ADR-0018. `.agents/` is reserved for development agents and repository execution tooling.

## Product Roadmap

The canonical implementation sequence is `docs/01-Product/Roadmap.md`.

Completed Foundation Era Phases 00–07 remain authoritative history. Implementation now proceeds through value-driven milestones and vertical slices beginning with `M0 Runnable Core`, then `M1 First Value`.

`M0 Runnable Core` and M1.1–M1.4 are completed / PASS. M1.4 Application Draft was squash-merged into `stage` by PR #31 on 2026-09-24. M2 implementation has not started; the next bounded task is the planning-only specification `.agents/tasks/m2-application-package-planning.md`.

## Delivery Governance

GitHub delivery metadata is repository-managed rather than an undocumented UI convention:

- `.github/roadmap.yml` maps native GitHub Milestones `M0–M6` to target SemVer versions and M1 slice labels;
- `.github/labels.yml` defines canonical roadmap/slice/release labels;
- `.github/workflows/governance.yml` validates PR roadmap metadata;
- `.github/rulesets/cvortex-protected-branches.json` requires the governance check on protected integration branches;
- `.agents/policies/git-workflow.md` defines branch/PR behavior;
- `.agents/policies/release-management.md` defines version/tag/release behavior;
- `docs/10-Operations/GitHub-Governance.md` documents GitHub Milestones and Project configuration.

Execution authority still comes from `.agents/state/NEXT.md`; a GitHub Milestone is planning metadata, not permission to skip tasks.
