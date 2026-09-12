# CVortex Project State

## Current Phase

`04-product-integrations-hiring-research`

Status: **completed with sequencing blocker**

## Completed Phases

- `00-ai-system-bootstrap`
- `01-project-knowledge-bootstrap`
- `02-research-plan` — confirmed by merged repository commit/research plan
- `04-product-integrations-hiring-research` — completed by explicit current task instruction

## Sequencing Conflict

`03-technical-research` completion was **not found** on `stage` or in repository commit history checked during Phase 04.

Before this Phase 04 run, `STATUS.md` and `NEXT.md` were stale and still reported Phase 01 / Phase 02 even though the Phase 02 research plan had already been merged.

Phase 04 was executed because the explicit current task required it. Do not infer that Phase 03 is complete.

## Current Repository State

The repository contains:

- development-agent operating layer and Git/GitHub governance;
- Phase 01 project knowledge base;
- Phase 02 research plan and research index;
- Phase 04 evidence for vacancy integrations, ATS/recruitment practices and external-content security.

No product implementation was introduced by Phase 04.

Phase 04 did not create:

- Laravel/Next.js product code;
- migrations or final ERD;
- vacancy adapters or scraping;
- browser extension code;
- live OAuth/job-board connections;
- Recruitment Knowledge Base tables;
- accepted architecture ADRs;
- fake/universal ATS scoring.

## Accepted Decisions

Owner-approved product directions remain unchanged:

- CVortex is a personal Job Search OS.
- Truth-first is mandatory.
- Generated Content → Claims → confirmed Career Facts.
- Employer-facing statements must remain consistent.
- Human approval is required before employer-facing actions.
- Deterministic solutions are preferred before LLM use.
- AI architecture must be provider-independent.
- Initial architecture direction is local-first and API-first.
- Registration is invite-only; the system is multi-user; MVP roles are `admin` and `user`.
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
- `main` is stable/release; `stage` is integration; normal work uses short-lived branches and PRs.

## Phase 04 Research Conclusions — NOT ADRs

High-confidence evidence supports these inputs for later architecture decisions:

- official/public ATS job-board APIs and feeds exist for several ATS platforms;
- partner/customer APIs must not be treated as public candidate APIs;
- undocumented frontend endpoints are not valid integration APIs;
- manual paste/file import is required as a universal fallback;
- LinkedIn, WWR, Dice and other restrictive sources require source-specific automation policy;
- matching/screening is multi-dimensional/vendor-specific, not one universal ATS percentage;
- external vacancy/web/document content must remain untrusted data;
- future URL ingestion requires SSRF-aware architecture.

These findings are research evidence only. Phase 05 must decide architecture after all prerequisite research is available.

## Pending Architecture Decisions

Previous pending decisions remain, plus Phase 04 decision inputs:

- runtime/product LLM Skill location/lifecycle;
- concrete provider/model mappings and pricing configuration;
- dependency/library versions;
- detailed data model/final ERD;
- vacancy source registry/adapter capability model;
- source policy/retention metadata model;
- URL/browser/manual ingestion boundaries;
- matching dimension model;
- Recruitment Knowledge Base design;
- external-content trust and URL-fetch security boundary.

## Phase 04 Deliverables

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
- updated `research/RESEARCH-INDEX.md`
- updated project state files.

## Last Validation Result

Phase 04 content validation: **PASS**.

Validated:

- every required Vacancy Source is represented;
- API/auth/OAuth/feed claims are evidence-scoped or marked unknown;
- public/partner/customer/internal distinctions are explicit;
- restrictions and fallbacks are documented;
- recruitment topics and market comparisons are covered;
- confidence and unresolved findings are recorded;
- security research is present;
- no product code or accepted ADR is introduced.

Phase sequence validation: **BLOCKED**.

Reason: Phase 03 technical research completion is not evidenced in the repository.

## Next Phase

Task-prescribed next phase:

`05-architecture-decision-freeze`

Execution status: **BLOCKED until Phase 03 technical research is completed or located and project state is reconciled.**
