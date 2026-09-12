# CVortex Project State

## Last Completed Phase

`07-design-foundation`

Status: **completed**

## Next Authorized Phase

`08-repository-bootstrap`

Execution authority is defined by `NEXT.md` and active blockers in `BLOCKERS.md`. Do not advance automatically.

## Completed Phases

- `00-ai-system-bootstrap`
- `01-project-knowledge-bootstrap`
- `02-research-plan`
- `03-technical-research`
- `04-product-integrations-hiring-research`
- `05-architecture-decision-freeze`
- `06-product-data-ai-security-design`
- `07-design-foundation`

## Phase 07 Outcome

`07-design-foundation` is **completed**. Independent review verdict: **PASS**. It establishes a documentation/token baseline only: Git-held DTCG 2025.10 tokens at `brand/tokens/cvortex.tokens.json`, dark-first visual semantics, typography, layout/motion/layer rules, component taxonomy, truth-first status patterns, accessibility acceptance criteria, and a deterministic Figma bootstrap/handoff.

Figma visual/component authority and Git machine-token authority remain aligned with accepted ADR-0013 and ADR-0014. A live [CVortex Design System](https://www.figma.com/design/2D7jymN0KxoUMUodh6v8Hd) file was created with Git token revision `624c53c` recorded in the handoff, but the connected Starter plan reached its Figma MCP call limit before canvas discovery or writes. The file is not a reviewed baseline: no pages, token import, components or visual review are claimed, and the reproducible manual bootstrap remains documented.

### Validation and known limitations

Validated: JSON parsing; all token aliases resolve; key opaque text/control/state contrast targets pass against `surface.default` (text at least 4.5:1, controls/states at least 3:1); changed-document Markdown links resolve; `git diff --check` and `git diff --check stage...HEAD` pass; final state consistency confirms Phase 07 completed, the task lifecycle status completed, no stale Phase 07 blocker, and `NEXT.md` points only to `08-repository-bootstrap`. This phase does not validate rendered UI, font licensing/loading, Figma Variables availability, visual review, assistive technology, or a DTCG transformer. The live Figma file exists, but its canvas was not available after the Starter-plan MCP rate limit, so the documented manual bootstrap remains the real capability limitation. No product/business implementation, dependencies, migrations, or infrastructure were introduced.

## Phase 06 Outcome

`06-product-data-ai-security-design` is **completed**. Independent re-review verdict: **PASS** (Critical/High/Medium/Low: 0/0/0/0). The accepted Phase 05 ADR baseline remains authoritative.

No product code, migration, dependency or infrastructure artifact was created in this documentation-design phase.

### Validation and known limitations

Validated: `git diff --check`; `git diff --check stage...HEAD`; Markdown-link and frontmatter checks; independent review of Truth Guard, ownership isolation, threat/audit matrices, component contracts and conceptual claim reuse.

Known limitations: Phase 06 is documentation-only; implementation and runtime tests are intentionally deferred. No repository-local Mermaid renderer/validator is available.

## Phase 05 Outcome

Phase 03 and Phase 04 evidence has been converted into an accepted architecture baseline. The canonical inventory is [Architecture Decision Index](../../docs/03-ADR/INDEX.md); the compact system view is [Phase 05 Architecture Baseline](../../docs/02-Architecture/Architecture-Baseline.md).

Accepted ADRs are authoritative within their scopes. Future changes to accepted decisions require an explicit amending or superseding ADR. Concrete dependency versions, LLM provider/model mappings, prices and implementation details remain changeable unless a later ADR deliberately freezes them.

No product code, application skeleton, dependency, migration, Docker Compose stack, ERD, OpenAPI specification, Figma screen or runtime Skill was created.

## Accepted Decisions

| ADR | Decision | Nature |
|---|---|---|
| [ADR-0001](../../docs/03-ADR/ADR-0001-laravel-core-backend.md) | Laravel as the Core Application Backend | OWNER_CONSTRAINT |
| [ADR-0002](../../docs/03-ADR/ADR-0002-postgresql-primary-database.md) | PostgreSQL as the Primary Durable Database | OWNER_CONSTRAINT |
| [ADR-0003](../../docs/03-ADR/ADR-0003-redis-queues-horizon.md) | Redis-backed Laravel Queues and Horizon | OWNER_CONSTRAINT |
| [ADR-0004](../../docs/03-ADR/ADR-0004-api-first-contract.md) | Shared API-first Application Boundary | OWNER_CONSTRAINT |
| [ADR-0005](../../docs/03-ADR/ADR-0005-local-first-docker-compose.md) | Local-first Docker Compose Deployment Baseline | OWNER_CONSTRAINT |
| [ADR-0006](../../docs/03-ADR/ADR-0006-monorepo-strategy.md) | Incremental Monorepo Strategy | DERIVED_ARCHITECTURAL_DECISION |
| [ADR-0007](../../docs/03-ADR/ADR-0007-multi-user-ownership.md) | Shared-schema Multi-user Ownership and Isolation | OWNER_CONSTRAINT |
| [ADR-0008](../../docs/03-ADR/ADR-0008-invite-only-access.md) | Invite-only Registration Boundary | OWNER_CONSTRAINT |
| [ADR-0009](../../docs/03-ADR/ADR-0009-strict-truth-guard.md) | Strict Truth Guard and Provenance Invariant | OWNER_CONSTRAINT |
| [ADR-0010](../../docs/03-ADR/ADR-0010-provider-independent-llm.md) | Provider-independent LLM Boundary | OWNER_CONSTRAINT |
| [ADR-0011](../../docs/03-ADR/ADR-0011-logical-model-policy.md) | Logical Capability-based Model Policy | OWNER_CONSTRAINT |
| [ADR-0012](../../docs/03-ADR/ADR-0012-git-markdown-obsidian.md) | Git Markdown Documentation with Obsidian as Interface | OWNER_CONSTRAINT |
| [ADR-0013](../../docs/03-ADR/ADR-0013-figma-visual-source.md) | Figma as the Reviewed Visual Source of Truth | OWNER_CONSTRAINT |
| [ADR-0014](../../docs/03-ADR/ADR-0014-git-design-tokens.md) | Git-held DTCG Tokens as Machine-readable Canonical Source | RESEARCH_BACKED_DECISION |
| [ADR-0015](../../docs/03-ADR/ADR-0015-file-storage-abstraction.md) | File Storage Abstraction with Local Initial Backend | DERIVED_ARCHITECTURAL_DECISION |
| [ADR-0016](../../docs/03-ADR/ADR-0016-deterministic-document-rendering.md) | Deterministic DOCX-to-PDF Rendering Pipeline | OWNER_CONSTRAINT |
| [ADR-0017](../../docs/03-ADR/ADR-0017-untrusted-external-content.md) | Untrusted External Content Boundary | RESEARCH_BACKED_DECISION |
| [ADR-0018](../../docs/03-ADR/ADR-0018-runtime-ai-skills-location.md) | Repository Runtime AI Skills Location | DERIVED_ARCHITECTURAL_DECISION |

## Conflicts Resolved

- The previous `BLOCKERS.md` still claimed Phase 03 was missing. Current repository contents, commit state and the already-reconciled status prove Phase 03 complete; the stale blocker was removed.
- `research/technical/DECISION-CANDIDATES.md` describes OpenAI as the owner-fixed first provider, while higher-priority `PROJECT.md` makes it only a possible first implementation choice. The accepted baseline follows `PROJECT.md`: provider/model selection is mutable configuration. Research remains unchanged as dated evidence.
- The Phase 04 summary's missing-Phase-03 note is retained as historical context; later repository state explicitly reconciled it.

No pre-Phase-05 accepted ADR existed, so no ADR was superseded.

## Deferred Decisions

Deferred to later bounded phases:

- physical database tables and API/OpenAPI contracts;
- authentication package and invitation endpoint details;
- concrete runtime Skill file layout and loader implementation; `.agents/` remains reserved for development agents and `/runtime-ai/` is canonical;
- concrete provider adapters, model mappings, prices and routing thresholds;
- dependency/runtime versions and compatibility validation;
- queue topology, supervisors and retry parameters;
- component primitive library and token/Figma workflow (Phase 07);
- design-token transformation/synchronization tooling;
- document generator library and exact LibreOffice build;
- retention rules and ingestion adapters;
- future semantic-search implementation.

## Files Created / Changed

- `docs/02-Architecture/Architecture-Baseline.md`
- `docs/03-ADR/INDEX.md`
- `docs/03-ADR/ADR-0001-laravel-core-backend.md` through `ADR-0017-untrusted-external-content.md`
- `docs/00-Home/Documentation-Map.md`
- `docs/01-Product/Phase-06-Product-Design.md`
- `docs/02-Architecture/Phase-06-System-Design.md`
- `docs/04-Data/Phase-06-Data-Design.md`
- `docs/06-AI/Phase-06-AI-Design.md`
- `docs/08-Security/Threat-Model.md`
- `docs/03-ADR/ADR-0018-runtime-ai-skills-location.md`
- `runtime-ai/README.md`
- `brand/tokens/cvortex.tokens.json`
- `docs/07-Design/Design-Foundation.md`
- `docs/07-Design/Figma-Handoff.md`
- `research/RESEARCH-INDEX.md` (navigation/current phase state only; research evidence unchanged)
- `.agents/state/STATUS.md`
- `.agents/state/NEXT.md`
- `.agents/state/BLOCKERS.md`

## Validation Result

Phase 05 documentation/repository validation: **PASS**.

Validated:

- 17 unique ADR IDs; every required ADR exists and is `accepted`;
- every accepted ADR has frontmatter, decision nature, Context, Options, Comparison, Decision, Consequences and References;
- the ADR Index links exactly once to every ADR and local Markdown/research links resolve;
- related ADR IDs resolve and no supersession metadata is inconsistent;
- ModelPolicy uses logical capability tiers and mutable provider/model configuration;
- no concrete provider/model mapping or price is frozen as architecture;
- architecture/home/research-index/state documentation is consistent with the ADR set;
- owner constraints and research-vs-source conflicts are explicitly preserved/resolved;
- no product code, migration, dependency or implementation scaffold was added;
- `git diff --check` passes and the only pre-existing unrelated file remains untracked and untouched.

No repository-provided Markdown/link/frontmatter validator exists; deterministic repository-local Ruby/shell checks were executed instead.

## Next Bounded Work

`08-repository-bootstrap` — ready for its separately authorized execution.
