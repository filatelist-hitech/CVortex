# CVortex Project State

## Current Phase

`00-ai-system-bootstrap`

Status: **completed**

## Completed Phases

- `00-ai-system-bootstrap`

## Current Repository State

The repository contains only the development-agent operating layer created for Phase 00.

No product implementation has been created.

Specifically, Phase 00 does not create:

- Laravel;
- Next.js;
- npm/composer dependencies;
- Docker Compose;
- database migrations;
- backend/frontend product code;
- final ERD;
- permanent concrete LLM model mappings;
- LLM pricing assumptions.

## Accepted Decisions

The following product directions are owner-approved inputs:

- CVortex is a personal Job Search OS.
- Truth-first is mandatory.
- Generated Content → Claims → confirmed Career Facts.
- Employer-facing statements must remain consistent.
- Human approval is required before employer-facing actions.
- Deterministic solutions are preferred before LLM use.
- AI architecture must be provider-independent.
- Initial architecture direction is local-first and API-first.
- Registration is invite-only.
- The system is multi-user.
- MVP roles are admin and user.
- Backend direction: Laravel.
- Frontend direction: Next.js / React / TypeScript.
- Data direction: PostgreSQL.
- Queue/cache direction: Redis + Horizon.
- Web direction: Nginx.
- Document direction: DOCX + LibreOffice headless → PDF.
- Initial client direction: responsive PWA.
- Figma is the visual source of truth.
- Documentation is Markdown stored in Git and usable as an Obsidian Vault.
- `.agents/` is reserved for DEVELOPMENT AGENTS.

## Pending Architecture Decisions

- Canonical location and lifecycle of CVortex runtime/product LLM Skills.
- Concrete provider/model mappings.
- Concrete model pricing configuration.
- Dependency/library versions.
- Detailed data model and final ERD.

These must be resolved only in their appropriate future phases.

## Relevant ADR Links

No ADRs created during Phase 00.

Architecture decisions requiring ADR treatment will be formalized in later phases.

## Files Created / Changed

- `AGENTS.md`
- `PROJECT.md`
- `.agents/policies/truth-first.md`
- `.agents/policies/architecture.md`
- `.agents/policies/research.md`
- `.agents/policies/security.md`
- `.agents/policies/testing.md`
- `.agents/policies/documentation.md`
- `.agents/policies/change-management.md`
- `.agents/workflows/phase-execution.md`
- `.agents/workflows/research.md`
- `.agents/workflows/architecture-decision.md`
- `.agents/workflows/implementation.md`
- `.agents/workflows/review.md`
- `.agents/templates/ADR.md`
- `.agents/templates/RESEARCH.md`
- `.agents/templates/TASK.md`
- `.agents/templates/SKILL.md`
- `.agents/templates/REVIEW.md`
- `.agents/state/STATUS.md`
- `.agents/state/NEXT.md`
- `.agents/state/BLOCKERS.md`
- `docs/AGENTS.md`
- `research/AGENTS.md`

## Last Validation Result

Phase 00 bootstrap validation is performed by the bootstrap script after all files are created.

Required checks:

- all operating instructions exist;
- policies are separated;
- workflows are separated;
- templates exist;
- persistent state exists;
- docs and research have scoped instructions;
- `NEXT.md` contains only `01-project-knowledge-bootstrap`;
- no prohibited product bootstrap artifacts exist.

Expected result: PASS.
