---
title: Concept B — Design Reconciliation and Bounded Roadmap
status: completed
owner: project
created: 2026-10-07
updated: 2026-10-08
tags: [design, reconciliation, evidence, roadmap]
related: ["[[Design-Foundation]]", "[[Application-Workspace]]", "[[Design-Handoff]]", "[[../03-ADR/ADR-0024-git-design-reference-workflow|ADR-0024]]"]
---

# Concept B — Design Reconciliation

## Decision and scope

**Concept B / Application Workspace selected and design reconciliation completed.** The owner selected the mental model in the supplied task brief; this is product authority, not an inferred winner from research scores. The exact exploratory pixels remain review inputs. Canonical target IA: **Today / Opportunities / Career / Employers / Settings**.

Mode: `ARCHITECTURE`, repository-native workflow, one agent, no subagents. Explicit bounded user override of the Preview pointer. Reused the directly related `docs/ux-redesign-concepts` branch at `a62ff0899ea9a2dd2266eb5a972d618f803c9952`, equal to locally recorded `stage`/`origin/stage`; no remote synchronization claim. Pre-existing research and STATUS changes were preserved. No production implementation, token-value change, installation, Figma use, backend/database work or publication.

## Source discovery and evidence boundaries

Targeted repository inventory covered product/design indexes, Design Foundation, Figma Handoff, Error Center UX, Phase 06 product/system/data/AI design, Principles/Scope/Glossary/Roadmap, architecture baseline, accepted ADR-0004/0007/0009/0012/0013/0014/0016/0017/0022/0023 boundaries, actual Git tokens and frontend conventions. `apps/frontend/AGENTS.md`, `package.json`, `page.tsx`, `access-shell.tsx`, `career-workspace.tsx`, `vacancy-workspace.tsx`, `globals.css` established presentation/availability without editing production.

| Evidence | Observation | Limit |
| --- | --- | --- |
| Live Concept B at `http://127.0.0.1:8767/concept-b/index.html` | In-app browser rendered the actual file; desktop screenshot/AX showed list + task + inspector. Selected Cedar then Northstar; section change retained opportunity. | Synthetic, in-memory fixtures only; no production correctness proof. |
| Live evidence disclosure | Content → Claim CL-08 → CONFIRMED F-12/F-18 → source/confirmation; separate approval copy. Close restored trigger focus. | Fixture validation; not semantic Truth Guard implementation. |
| Live review | Unsupported edit `Led a production Playwright team.` returned BLOCK; Accept then produced ACCEPTED and enabled a separate Approve control. | No employer submission; unsupported-edit gate recognizes only exact fixture wordings. |
| Live 390×844 mobile screenshot/AX | Opportunity selector replaces desktop list; task follows, inspector is stacked farther down. | Canonical mobile hierarchy deliberately changes this into list → workspace → evidence levels. No fresh comprehensive responsive/AT certification. |
| Live error simulation | Analysis unavailable, preserved-source copy, Retry simulation and disabled approval visible. | Whole-screen prototype simulation is not the desired production error placement. |
| Current UI at `http://127.0.0.1:8080/` | Real CVortex Sign in page rendered with the existing wordmark/tagline/navy/gradient mark. | Browser was unauthenticated; protected Career/Vacancy screens were not inspected live. No login/provider operation. |
| Current frontend source | Career root embeds VacancyWorkspace; vacancy detail is sequential source/chat/requirements/analysis/draft review. Current states, provenance and cooldown controls exist. | Source evidence, not measured usability or live authenticated E2E. |
| Existing Error Center screenshots | [1440 detail](evidence/error-center-remediation-1440-detail.png) and [390 detail](evidence/error-center-remediation-390-detail.png) visually inspected: decision-first panel, correlation and collapsed technical details. | Previously captured synthetic production-build evidence; not fresh incident data or current authenticated capture. |
| Existing concept screenshots/results | [Desktop](../../research/design-redesign/concept-b/concept-b-viewport.png), [mobile](../../research/design-redesign/concept-b/concept-b-mobile-viewport.png), [prior 127-check report](../../research/design-redesign/validation/README.md). | Historical research checks were not rerun or relabelled as reconciliation-run tests. Fresh browser screenshots are in the conversation tool outputs. |

The `agent-browser` CLI was unavailable. Browser policy rejected `file://`; the allowed HTTP view used the existing loopback preview restricted to the research directory. An attempted preview start found the port already occupied; the existing working preview was inspected, not killed or replaced. This did not expose repository files publicly.

No separate approved logo asset was found in the targeted `brand`/frontend inventory. Preserve CVortex wordmark and the existing gradient mark; do not invent an approved logo or replace one held outside the repository. Verify any owner-supplied asset before substituting it. This does not block documenting the selected direction.

## Reconciliation matrix

Status meaning: **KEEP** preserves a decision; **MODIFY** preserves its principle while changing presentation/process; **SUPERSEDE** replaces an authority/choice with explicit history; **DEPRECATE** removes a practice without deleting its historical evidence; **CONFLICT** names an unresolved decision. Resolved conflicts record the resolution explicitly. Rows cover significant reusable decisions, not every CSS declaration.

| Existing decision | Source | Status | Concept B decision | Reason | Required action |
| --- | --- | --- | --- | --- | --- |
| CVortex, tagline, existing identity | [PROJECT](../../PROJECT.md), [Foundation](Design-Foundation.md#intent-and-authority), frontend `globals.css` | KEEP | Same brand and current mark | Selection is UX direction, not rebranding | Foundation retained; external logo asset verification before replacement |
| Dark-first navy/cyan/blue/violet and brand type | [Foundation](Design-Foundation.md#token-model), [tokens](../../brand/tokens/cvortex.tokens.json) | KEEP | Same semantic palette/type | Prototype differences are not palette approval | No JSON values changed |
| Figma required visual/component authority | [ADR-0013](../03-ADR/ADR-0013-figma-visual-source.md), [PROJECT](../../PROJECT.md) | SUPERSEDE | Git-reviewed reference workflow | Confirmed owner plan constraint prevents reliable required canvas review | ADR-0024; old ADR marked superseded; active authority references updated |
| Mandatory Figma pages/bootstrap/parity gate | [Figma Handoff](Figma-Handoff.md) | DEPRECATE | Optional auxiliary Figma only | Required bootstrap is no longer useful | Whole handoff marked historical/superseded, link to Design Handoff |
| Git-held DTCG single machine authority | [ADR-0014](../03-ADR/ADR-0014-git-design-tokens.md) | KEEP | Same file/format/alias authority | Determinism survives workflow change | Figma-role amendment only; no second token file |
| Figma/tool output vs real human review | [Figma Handoff](Figma-Handoff.md), [Foundation](Design-Foundation.md) | MODIFY | Human reviews revision/state/viewport of local references/components | A screenshot/tool call still does not approve a baseline | Design Handoff review manifest; reference foundation task |
| Current long Career → Vacancy → draft screen | Frontend `career-workspace.tsx`, `vacancy-workspace.tsx` | SUPERSEDE | Opportunity list + task surface + optional inspector | Repeated scrolling separates a decision from context | Target contract accepted; migrate only in bounded later shell tasks |
| Product flow from career truth to analysis/materials/history | [Phase 06 Product](../01-Product/Phase-06-Product-Design.md#user-flows) | MODIFY | Same dependencies, contextual navigation and returns | Preserve the flow without a forced wizard | Product design links the new presentation contract |
| Independent future Documents/Research pages | [Scope](../01-Product/Scope.md), prior [research IA](../../research/design-redesign/research/ux-model-and-ia.md) | MODIFY | Contextual materials/findings, later subordinate libraries | Artifacts support opportunity work | Canonical IA records placements; no routes removed |
| Companies terminology / employer ownership | [Glossary](../01-Product/Glossary.md), [System Design](../02-Architecture/Phase-06-System-Design.md#component-contracts) | KEEP | Employers is UI label over Company/private context | UI naming cannot remodel ownership/entities | Glossary adds presentation terms only |
| Proposed Today/Opportunities/Career/Employers/Settings | Prior [research IA](../../research/design-redesign/research/ux-model-and-ia.md) | MODIFY | Adopt after checking domain/product boundaries | Today is a projection; Employers needs real association; Settings retains utilities | Workspace IA table is canonical; research stays dated |
| Vacancy and Application distinction/cardinality | [Data Design](../04-Data/Phase-06-Data-Design.md), [Glossary](../01-Product/Glossary.md) | KEEP | Opportunity is a UI context before/after Application | Current preparation is not a sent application | Label vacancy/preparation honestly; no Opportunity schema/API |
| Prototype Prepare/Apply/Interview stages | [Concept B](../../research/design-redesign/concept-b/index.html) and fixtures | DEPRECATE | Use actual domain enums separately from analysis/recommendation/review | Fixture labels would falsely invent lifecycle | State mapping is a future bounded implementation decision |
| Header title/status/metadata | Current vacancy source/detail, [Concept B](../../research/design-redesign/concept-b/index.html) | MODIFY | Stable identity + exact state + next action; secondary salary/format | Action and blocker outrank decorative metadata | Workspace header contract; don't infer unknowns |
| Four prototype local selectors | [Concept B](../../research/design-redesign/concept-b/index.html) | MODIFY | Overview / Requirements & Match / Materials / Employer context / History | Group related views; avoid nine competing tabs | Local links, contextual subnavigation, capability-aware states |
| Persistent prototype inspector | [Concept B](../../research/design-redesign/concept-b/index.html) | MODIFY | Explicit selection; close/pin; drawer or mobile level | Evidence must stay reachable without squeezing work width | Inspector contract, scoped reset/focus/stale behavior |
| Evidence before persuasion / progressive disclosure | [Foundation](Design-Foundation.md#principles-and-content-hierarchy) | KEEP | Evidence summary plus selected trace | Needed for dense review without a permanent graph | Workspace provenance contract |
| FACT → CLAIM → GENERATED CONTENT | [ADR-0009](../03-ADR/ADR-0009-strict-truth-guard.md), [Principles](../01-Product/Principles.md) | KEEP | Reverse trace from selected generated wording | Confirmation and provenance remain hard invariants | No approval bypass or pending evidence support |
| PASS/BLOCK/USER_RESOLUTION_REQUIRED | [Foundation](Design-Foundation.md#interaction-and-semantic-states), [AI Design](../06-AI/Phase-06-AI-Design.md) | KEEP | Exact validation outcomes and explicit repair path | Validation is not content approval | Inline block survives closed inspector |
| PENDING/CONFIRMED/REJECTED/DEPRECATED Facts | [Data Design](../04-Data/Phase-06-Data-Design.md), current Career UI | KEEP | Text-labelled states and human Career review | AI cannot certify truth | Preserve history and revalidation on return |
| Accept/Edit/Reject and explicit content approval | [Scope](../01-Product/Scope.md#application-package), current draft panel | KEEP | Proposal decision remains separate from exact-revision approval | Existing human gate is essential | Feature acceptance tests preserve gating |
| Inline AI vs dedicated chat | Current `vacancy-chat.tsx`, [ADR-0023](../03-ADR/ADR-0023-chatgpt-plan-chat.md) | MODIFY | Inline review primary; analysis chat remains contextual | A chat does not replace structured validated review | Keep context preview/consent/save-draft/approve boundaries |
| Fake universal ATS score prohibited | [Scope](../01-Product/Scope.md#vacancy-workflow), current seven dimensions | KEEP | Explainable dimensions/classes, unknowns and source access | No evidence for universal probability | No aggregate score; confidence scope documented |
| Numeric extraction confidence in current disclosure | Frontend `vacancy-workspace.tsx` | MODIFY | Review-source label; number only with useful interpretation | Uncalibrated model signal is not match truth | Future requirements slice changes copy; no code change here |
| Failed/stale/loading/empty and exact retry | [Error Center UX](Error-Center-UX.md), current Vacancy/draft UI | KEEP | Operation-scoped states inside workspace | Global error screen unnecessarily loses work context | Preserve current fences/cooldown; specify next-action recovery |
| Provider cooldown; Reject available | [Error Center UX](Error-Center-UX.md#safe-content-rules) | KEEP | Existing bounded Retry-After gating | UI navigation must not reopen provider operations | Include per-action states in review acceptance |
| Employer Memory participates in consistency | [Phase 06 Product](../01-Product/Phase-06-Product-Design.md), [ADR-0009](../03-ADR/ADR-0009-strict-truth-guard.md) | KEEP | Commitments/used claims summary before chronology | Employer history must affect decisions, not just fill a timeline | Planned owner/association-scoped feature; no invented current memory |
| Imported company name sufficient for memory | Prototype fixtures vs current STATUS association boundary | CONFLICT — RESOLVED | Independently confirmed employer association required | Source name is untrusted and ambiguous | Workspace contract keeps current disabled boundary; backend feature separately gated |
| Conversation import / research trust | [ADR-0017](../03-ADR/ADR-0017-untrusted-external-content.md), [Data Design](../04-Data/Phase-06-Data-Design.md) | KEEP | Label source/date; propose pending facts only | Prompt injection/source truth must not cross confirmation | No HTML execution, automatic fact confirmation or memory merge |
| Shared API / server-controlled transitions | [ADR-0004](../03-ADR/ADR-0004-api-first-contract.md), [System Design](../02-Architecture/Phase-06-System-Design.md) | KEEP | UI is a presentation layer | Workspace naming cannot alter enforcement | No API/backend/schema change |
| Same-owner facts/claims/context and no admin bypass | [ADR-0007](../03-ADR/ADR-0007-multi-user-ownership.md), [Data Design](../04-Data/Phase-06-Data-Design.md#ownership) | KEEP | Inspector/review obey owner/revision fences | Preserving context cannot preserve another user's data | No PII in fixtures; async selection acceptance |
| Human submission and external communication | [PROJECT](../../PROJECT.md), [Phase 06 Product](../01-Product/Phase-06-Product-Design.md) | KEEP | Approval is not sent/applied | No automatic employer action | Separate future manual record only when supported |
| Documents pipeline / exact versions | [ADR-0016](../03-ADR/ADR-0016-deterministic-document-rendering.md), [Roadmap](../01-Product/Roadmap.md) | KEEP | Materials hosts versions/export when implemented | UI cannot invent DOCX/PDF package | Gate on Preview + bounded M2 contract |
| Diagnostics decision-first admin surface | [Error Center UX](Error-Center-UX.md), [ADR-0022](../03-ADR/ADR-0022-local-diagnostics.md) | KEEP | Admin utility; workspace failures stay inline | Diagnostics isn't application-level evidence inspector | Existing route/privacy/retry/focus semantics retained; Figma gate removed |
| Breakpoints and responsive PWA | [Foundation](Design-Foundation.md#responsive-pwa-strategy), tokens | MODIFY | Pressure-based pane collapse; mobile level navigation | Three panes cannot just shrink | Same breakpoint values; no native mobile scope |
| 44px targets, contrast, focus, semantics, reduced motion | [Foundation](Design-Foundation.md#accessibility-acceptance-baseline) | KEEP | Per-level/pane/diff acceptance | Dense layout cannot weaken accessibility | Real zoom/AT and production validation remain future acceptance |
| Typography/space/radius/shadow/layer scales | [Foundation](Design-Foundation.md#layout-shape-motion-and-layers), tokens | KEEP | Reuse semantic roles, avoid prototype literals | Prototype has noncanonical radii/backgrounds | Token mapping ledger below; no value churn |
| Lucide and PascalCase component names | [Foundation](Design-Foundation.md#iconography), old handoff naming | KEEP | Same naming/source; Figma-specific instance naming optional | Existing style should survive source change | No icon package installed; license/font validation before production assets |
| Foundation generic taxonomy | [Foundation](Design-Foundation.md#component-taxonomy-and-patterns) | MODIFY | Primitive/component/pattern/feature levels for workspace | Prevent over-abstraction and mass screen rewrites | Taxonomy extended in the existing Foundation |
| Prototype CSS/search/demo controls/font asset | [prototype CSS](../../research/design-redesign/assets/prototype.css), [design plan](../../research/design-redesign/research/design-plan.md) | DEPRECATE | Exploration-only implementation decisions | Gallery/footer/fixed fixtures are not production UX | Reference normalization removes demo chrome; existing licensed research files preserved |
| Source-of-truth contradiction | PROJECT + ADR-0013 vs owner Figma constraint | CONFLICT — RESOLVED | ADR-0024 replaces mandatory Figma; token authority retained | Conflict needs explicit versioned resolution | Current docs/index/state updated; historical records labelled |

## Token reconciliation ledger

| Category | Tokens / observations | Decision / required action |
| --- | --- | --- |
| **compatible** | All existing `color.*`, `surface.*`, `text.*`, `border.*`, `accent.*`, `state.*`; font family/size/weight/lineHeight/tracking; space/radius/shadow/motion/breakpoint/layer | Keep values and aliases. No token has been proven obsolete by the new mental model. Current production uses only a manually derived subset; automation/parity is unverified. |
| **requires extension** | Planned component-role aliases: `workspace.list.selectedSurface`, `workspace.list.selectedText`, `workspace.inspector.surface`, `workspace.review.proposedSurface`, `workspace.status.stale`, `workspace.status.unknown` | In the first task justify each consumed role and add only needed aliases to existing `surface.interactive`, `text.primary`, `surface.elevated`, `state.warning` as appropriate. These paths are specification proposals, not present JSON tokens. Unknown and stale need text regardless of color. No new primitive colors required. |
| **obsolete** | None established | Do not delete tokens because a prototype did not use them. Figma-specific sync instructions are obsolete process, not obsolete token values. |
| **visual prototype-only** | 6/10px radii, custom state-badge backgrounds/borders, `#75e6f1` hover, fixed pane sizing, 18px headings, literal z-index and letter-spacing, demo/gallery chrome | Normalize into canonical semantic aliases/type/radius/layer scales during reference task. Do not copy these literals into the token JSON or production theme. |

Selection treatment may use current semantic tokens first. Component aliases are justified extensions only when needed for maintainable mapping; no broad “workspace theme” alongside the existing set. Brand font licensing/coverage/loading in production remains a bounded validation obligation; the research OFL/font asset is not automatically an approved production acquisition.

## Bounded frontend roadmap

This is a **conditional frontend design breakdown**, authorized by this reconciliation brief, not a new M2–M6 implementation backlog. Only **B01** has an executable task spec/NEXT pointer. Later rows need a fresh bounded task and explicit authorization; they must respect [Roadmap](../01-Product/Roadmap.md) and Preview gates. No row authorizes backend/schema/API work. Accessibility/state checks are part of every row, not postponed to B11.

| ID / bounded outcome | Dependencies / gate | Acceptance criteria |
| --- | --- | --- |
| **B01 — Reference foundation** | This reconciliation / ADR-0024 | One loopback, token-derived normalized HTML reference and state manifest; source/hash/aliases resolved, prototype literals reconciled; synthetic states and human-review status explicit. Production unchanged. [Exact task](../../.agents/tasks/application-workspace-reference-foundation.md). |
| **B02 — Global shell/navigation** | B01 reference + separate implementation authorization | Five canonical destinations/availability copy; existing login/logout/register and `/diagnostics` preserved; same-origin API, keyboard/current location/deep-link/back behavior verified. No fake Employers/Settings capabilities. |
| **B03 — Opportunity list** | B02, existing Vacancy endpoints | Existing saved vacancies only; search/selection/filter/scroll preserved; loading/empty/failed/permission/stale-selection states; no invented lifecycle or employer joins. |
| **B04 — Workspace shell** | B03 | Stable selected-vacancy/preparation header + local sections; exact statuses and known/unknown metadata; unsaved navigation guard; safe operation errors; existing draft contract unchanged. |
| **B05 — Context inspector** | B04, existing owner-bound evidence payloads | Explicit selected evidence, close/pin, same-record context only, reset on opportunity switch, stale/late-response handling and keyboard return; mobile full evidence level. |
| **B06 — Requirements & Match** | B04/B05, current normalized requirements/deterministic match | Source wording and seven dimensions, gaps/unknowns/risks; no universal score; stale/re-analysis/cooldown states; evidence stays scoped and untrusted source labelled. |
| **B07 — Recommendation/evidence review** | B05/B06, current preparation/revision services | Canonical reason/evidence/risk/diff sequence; Accept/Edit/Reject separate from exact approval; missing/conflicting/pending/deprecated evidence blocks correctly; selection races and provider cooldown preserved. |
| **B08 — Employer context integration** | B04/B05/B07 + separately implemented confirmed Company association/Employer Memory contract; Preview/M2/M4 gate as applicable | Approved draft vs used statement distinguished; commitments/salary/claims/conversations/history with exact sources; contradiction blocking; absent association has unavailable state. No name-only join. Current task cannot supply the missing domain capability. |
| **B09 — Materials workspace** | B04/B07; B08 only where actual consistency contract required | Resume recommendations and Cover drafts use current revisions/approval. Full ResumeVersion/files/DOCX/PDF/export require Preview evidence + authorized M2 implementation; no fake document preview/download. |
| **B10 — Complete responsive transformation** | B02–B07 plus any authorized B08/B09 surface | List → workspace → context with Back/focus/unsaved state preserved; 320/390/768/1024/1440, real 200% zoom, long RU/EN, safe-area/touch and offline boundaries verified. Initial mobile behavior is already required in earlier slices. |
| **B11 — Accessibility and visual regression consolidation** | B01 fixtures, B02–B10 implemented subset | Pin compatible local test tooling/capture environment; reviewed normal/loading/error/stale/blocked/approval screenshots; intentional diff review; automated checks + keyboard/manual screen-reader evidence; no hosted upload requirement. Earlier slices retain their own checks. |
| **B12 — Legacy presentation cleanup** | Implemented replacements, relevant acceptance PASS and separately authorized cleanup | Inventory obsolete presentation and reference clones; retain endpoint/state/auth/history behavior; remove only proven unused UI after navigation/evidence/review regression checks; do not delete old routes/data opportunistically. |

## Changed files

27 Markdown files are changed by this block (pre-existing research files and their prior STATUS entry are preserved):

- Design: `docs/07-Design/Design-Foundation.md`, `Application-Workspace.md`, `Design-Handoff.md`, `Concept-B-Reconciliation.md`, `Figma-Handoff.md`, `Error-Center-UX.md`.
- Decisions: `docs/03-ADR/ADR-0013-figma-visual-source.md`, `ADR-0014-git-design-tokens.md`, `ADR-0024-git-design-reference-workflow.md`, `INDEX.md`.
- Product: `PROJECT.md`; `docs/01-Product/Principles.md`, `Scope.md`, `Glossary.md`, `Phase-06-Product-Design.md`.
- Architecture: `docs/02-Architecture/Architecture-Baseline.md`, `Phase-06-System-Design.md`.
- Documentation/evidence navigation: `docs/00-Home/Documentation-Map.md`, `CURRENT-PROJECT-STATE.md`; `docs/10-Operations/ChatGPT-Plan-Chat.md` (design parity wording only).
- Execution: `.agents/state/STATUS.md`, `NEXT.md`, `BLOCKERS.md`; `.agents/tasks/application-workspace-reference-foundation.md`, `README.md`, `phase-07-design-foundation.md` (historical note only).
- Research navigation: `research/design-redesign/README.md` (subsequent-decision note only); prototypes, research conclusions and prior validation files unchanged.

## Validation and remaining limits

Fresh execution evidence: repository architecture write preflight; actual in-app-browser rendering/interaction and screenshots as listed above; targeted source/index/decision audit; structural/frontmatter/local-link/table/ADR consistency checks; canonical-token reference audit; protected-file hash comparison; `git diff --check`. Final document audit: **27 Markdown files, 20 parsed YAML frontmatters, 271 links, 44 tables, no broken links/errors**. Token audit: **113 leaves / 28 alias resolutions**; **378 protected production/token/prototype file hashes unchanged**. Both the architecture override preflight and B01 task/NEXT routing preflight passed; the latter validates routing only, it does not start implementation.

No new production test/lint/build run is required for this documentation-only change; no production file changed. Prior 127-check evidence remains prior-run evidence. Production visual regression, normalized reference/human pixel review, real zoom, screen-reader and real-user acceptance are not claimed. Existing quota, Preview and build-environment blockers remain open.

**STOP:** reconciliation ends here. NEXT points to B01 only; later production/domain work needs its own authorization.
