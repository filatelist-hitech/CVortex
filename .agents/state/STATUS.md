# CVortex Project State

## Current Phase

`04-product-integrations-hiring-research`

Status: **completed**

## Completed Phases

- `00-ai-system-bootstrap`
- `01-project-knowledge-bootstrap`
- `02-research-plan`
- `03-technical-research`
- `04-product-integrations-hiring-research`

## Current Repository State

The repository contains the development-agent operating layer and repository/GitHub governance foundation created during Phase 00.

Phase 01 added the initial CVortex project knowledge base and documentation skeleton.

Phase 02 added the research plan/index covering technical, integration, recruitment, security and licensing questions.

Phase 03 adds reviewed technical evidence for:

- PHP, Laravel and PostgreSQL;
- Redis, queues and Horizon;
- authentication, authorization, encryption and rate limiting;
- OpenAI API, current model catalog and pricing;
- Structured Outputs, prompt caching and Batch;
- PHP OpenAI integration options;
- Next.js, React, TypeScript and Tailwind CSS;
- component primitives;
- testing stack;
- DOCX generation and LibreOffice headless conversion;
- design-token tooling;
- Figma MCP and Code Connect.

Technical alternatives remain decision candidates rather than accepted ADRs.

Phase 04 adds reviewed evidence for:

- vacancy/job-board and ATS career-source capabilities;
- API/auth/OAuth/feed classifications and source restrictions;
- realistic vacancy ingestion fallbacks;
- ATS parsing, screening, skill matching and recruiting AI;
- resume and cover-letter practices;
- technical hiring;
- RU/EU/US/UK market comparison;
- fintech/startup/enterprise evidence limits;
- external-content/ingestion security threats.

No product implementation has been created by Phase 04.

Phase 04 does not introduce:

- Laravel or Next.js product code;
- npm/composer dependencies;
- Docker Compose;
- database migrations;
- final ERD;
- vacancy adapters or scraping;
- browser extension implementation;
- live OAuth/job-board connections;
- Recruitment Knowledge Base tables;
- concrete dependency versions;
- permanent concrete LLM model mappings;
- LLM pricing assumptions;
- runtime product AI Skills;
- accepted architecture ADRs.

## Sequencing Reconciliation

Phase 04 was merged before Phase 03 because Phase 03 artifacts were not yet
present in `stage` at the time Phase 04 was executed.

Phase 03 technical research has now been completed and reconciled into the
project state.

The temporary Phase 05 prerequisite blocker is therefore resolved.

The project sequence is now logically complete through Phase 04:

`00 → 01 → 02 → 03 → 04`

No Phase 03 or Phase 04 research finding becomes an accepted architecture
decision merely because the sequencing gap has been reconciled.


## Accepted Decisions

The following product directions remain owner-approved inputs:

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

## Phase 04 Research Conclusions — Not Accepted ADRs

Evidence supports these later decision inputs:

- public ATS job-board APIs/feeds are the cleanest automation surface;
- partner/customer APIs are not public candidate APIs;
- undocumented frontend endpoints must not be treated as integration APIs;
- manual paste/file input remains a necessary universal fallback;
- restrictive sources require source-specific automation/storage policy;
- matching/screening is multi-dimensional/vendor-specific, not one universal ATS percentage;
- external vacancy/web/document content remains untrusted data;
- future URL import requires SSRF-aware security architecture.

These findings are not architecture decisions until Phase 05 accepts them through the project decision process.

## Pending Architecture Decisions

Existing pending decisions remain:

- Canonical location and lifecycle of CVortex runtime/product LLM Skills.
- Concrete provider/model mappings.
- Concrete model pricing configuration.
- Dependency/library versions.
- Detailed data model and final ERD.

Phase 04 adds decision inputs for:

- vacancy source registry / adapter capability model;
- source policy / retention metadata;
- URL/browser/manual ingestion boundaries;
- matching dimension model;
- Recruitment Knowledge Base design;
- external-content trust boundary and URL-fetch security architecture.

These must be resolved only in their appropriate future architecture phases.

## Relevant ADR Links

No accepted ADR was created by Phase 04.

Architecture decisions requiring ADR treatment remain deferred to Phase 05 or later.

## Files Created / Changed

Core operating/project layer from earlier phases remains unchanged except project state files.

Phase 03 technical research artifacts:

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

Phase 04 research artifacts:

- `research/integrations/vacancy-sources-matrix.md`
- `research/integrations/ats-career-platforms.md`
- `research/integrations/integration-strategies.md`
- `research/integrations/platform-terms.md`
- `research/recruitment/ats-and-screening.md`
- `research/recruitment/recruiting-ai.md`
- `research/recruitment/resume-practices.md`
- `research/recruitment/cover-letters.md`
- `research/recruitment/technical-hiring.md`
- `research/recruitment/market-comparison.md`
- `research/recruitment/employer-type-comparison.md`
- `research/security/external-content-threats.md`
- `research/PHASE-04-SUMMARY.md`

Updated navigation/state:

- `research/RESEARCH-INDEX.md`
- `.agents/state/STATUS.md`
- `.agents/state/NEXT.md`
- `.agents/state/BLOCKERS.md`

## Last Validation Result

Phase 04 research validation: **PASS**.

Validated:

- required named vacancy sources are represented in the matrix;
- API/auth/OAuth/feed claims are evidence-scoped or marked unknown;
- public/partner/customer/internal API distinctions are explicit;
- restrictions and fallback strategies are documented;
- recruitment topics and market comparisons are covered;
- confidence and unresolved findings are recorded;
- security research is present;
- no fake universal ATS score is introduced;
- no product code or accepted ADR is introduced.

Phase sequence validation: **PASS**.

Validated:

- Phase 03 technical research artifacts are now present;
- Phase 03 decision candidates remain non-ADR recommendations;
- Phase 04 research remains present and unchanged;
- the temporary sequencing blocker has been resolved;
- no product implementation was introduced;
- no premature accepted ADR was introduced.

## Next Phase

`05-architecture-decision-freeze`

Execution status: **READY**.

Phase 05 may now evaluate Phase 03 and Phase 04 evidence together before
accepting architecture decisions.
