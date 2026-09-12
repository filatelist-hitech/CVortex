# CVortex Project State

## Current Phase

`03-technical-research`

Status: **completed**

## Completed Phases

- `00-ai-system-bootstrap`
- `01-project-knowledge-bootstrap`
- `02-research-plan`
- `03-technical-research`

## Current Repository State

The repository contains the development-agent operating layer and
repository/GitHub governance foundation created during Phase 00.

Phase 01 adds the initial CVortex project knowledge base and documentation
skeleton for use by subsequent Codex sessions.

The project knowledge base currently includes:

- project home;
- documentation map;
- product vision;
- product principles;
- product scope;
- canonical glossary;
- documentation section skeleton for architecture, ADRs, data, API, AI,
  design, security, QA, operations, research, and archive.

No product implementation has been created.

Phase 01 does not introduce:

- Laravel or Next.js product code;
- npm/composer dependencies;
- Docker Compose;
- database migrations;
- final ERD;
- concrete dependency versions;
- permanent concrete LLM model mappings;
- LLM pricing assumptions;
- runtime product AI Skills.

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
- `main` is the stable/release branch and `stage` is the integration branch.
- Normal changes use short-lived branches and pull requests into `stage`.
- Release promotion flows from validated `stage` to `main` through a pull request.
- Git tags and GitHub Releases are created only from validated `main` commits and follow SemVer once releases begin.
- No tag/release is required for documentation/repository bootstrap phases.

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

Phase 01 project knowledge:

- `docs/00-Home/CVortex.md`
- `docs/00-Home/Documentation-Map.md`
- `docs/01-Product/Vision.md`
- `docs/01-Product/Principles.md`
- `docs/01-Product/Scope.md`
- `docs/01-Product/Glossary.md`
- documentation skeleton directories under `docs/`

Core operating layer:

- `AGENTS.md`
- `PROJECT.md`
- `.agents/policies/truth-first.md`
- `.agents/policies/architecture.md`
- `.agents/policies/research.md`
- `.agents/policies/security.md`
- `.agents/policies/testing.md`
- `.agents/policies/documentation.md`
- `.agents/policies/change-management.md`
- `.agents/policies/git-workflow.md`
- `.agents/policies/release-management.md`
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

Repository/GitHub governance:

- `CONTRIBUTING.md`
- `.github/CODEOWNERS`
- `.github/labels.yml`
- `.github/release.yml`
- `.github/ISSUE_TEMPLATE/bug.yml`
- `.github/ISSUE_TEMPLATE/feature.yml`
- `.github/ISSUE_TEMPLATE/research.yml`
- `.github/ISSUE_TEMPLATE/task.yml`
- `.github/ISSUE_TEMPLATE/config.yml`
- `.github/rulesets/cvortex-protected-branches.json`
- `scripts/sync-git-governance.sh`
- `scripts/bootstrap-github-metadata.sh`

### Phase 01 — Project Knowledge Bootstrap

Documentation:

- `docs/00-Home/CVortex.md`
- `docs/00-Home/Documentation-Map.md`
- `docs/01-Product/Vision.md`
- `docs/01-Product/Principles.md`
- `docs/01-Product/Scope.md`
- `docs/01-Product/Glossary.md`
- documentation skeleton under `docs/`

Project state:

- `.agents/state/STATUS.md`
- `.agents/state/NEXT.md`


## Phase 02 — Research Plan

Status: **completed**

Phase 02 created the research planning foundation for subsequent evidence-based
technical and product research.

Research planning artifacts:

- `research/README.md`
- `research/RESEARCH-PLAN.md`
- `research/RESEARCH-INDEX.md`

Phase 02 established:

- research categories and priorities;
- decision questions influenced by research;
- preferred source hierarchy;
- freshness sensitivity;
- blocking vs non-blocking research questions;
- separation between research evidence and architecture decisions.

No product implementation was introduced.

## Phase 03 — Technical Research

Status: **completed**

Phase 03 executed the technical portion of the research plan.

Topics researched include:

- PHP runtime;
- Laravel framework;
- PostgreSQL;
- Redis;
- Laravel Horizon;
- authentication;
- authorization;
- encryption;
- rate limiting;
- queue architecture;
- OpenAI API;
- current model catalog and pricing;
- Structured Outputs;
- prompt caching;
- batch processing;
- PHP OpenAI/API client options;
- Next.js;
- React;
- TypeScript;
- Tailwind CSS;
- component primitive options;
- backend/frontend/E2E testing;
- DOCX generation;
- LibreOffice headless conversion;
- design-token tooling;
- Figma MCP;
- Figma Code Connect.

Technical research artifacts:

- `research/technical/01-BACKEND-RUNTIME-DATA.md`
- `research/technical/02-LARAVEL-AUTH-SECURITY.md`
- `research/technical/03-REDIS-QUEUES-HORIZON.md`
- `research/technical/04-OPENAI-API-MODELS.md`
- `research/technical/05-OPENAI-PHP-INTEGRATION.md`
- `research/technical/06-FRONTEND-STACK.md`
- `research/technical/07-COMPONENT-PRIMITIVES.md`
- `research/technical/08-TESTING-STACK.md`
- `research/technical/09-DOCUMENT-PIPELINE.md`
- `research/technical/10-DESIGN-TOKENS-FIGMA.md`
- `research/technical/DECISION-CANDIDATES.md`
- `research/technical/README.md`
- `research/technical/SOURCES.md`

Phase 03 intentionally did NOT:

- implement Laravel or Next.js;
- install Composer or npm dependencies;
- create Docker Compose;
- create migrations;
- implement authentication;
- implement queue workers;
- implement OpenAI integration;
- create accepted architecture ADRs for pending technical choices;
- freeze provider/model mappings as permanent architecture.

Technical alternatives and candidate decisions are recorded in:

- `research/technical/DECISION-CANDIDATES.md`

These candidates remain pending until the appropriate architecture decision
phase unless the direction was already explicitly approved by the product owner.

## Last Validation Result

Phase 03 validation: **PASS**

Validated:

- Phase 02 research planning artifacts remain present;
- technical research is stored under `research/technical/`;
- required Phase 03 technical topics were researched;
- research reports contain evidence and citations;
- `research/technical/DECISION-CANDIDATES.md` exists;
- technical alternatives remain decision candidates rather than silently accepted ADRs;
- owner-approved stack directions remain unchanged;
- no product code was introduced by Phase 03;
- no dependencies were installed by Phase 03;
- no database migrations were created by Phase 03;
- no permanent concrete LLM model mapping was frozen;
- no premature implementation was started;
- next bounded phase is `04-product-integrations-hiring-research`.
