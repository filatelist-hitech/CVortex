---
status: research-complete
date: 2026-09-12
phase: 03-technical-research
owner: CVortex
architecture_decision: none
---

# Design Tokens, Figma MCP and Code Connect

## Goal

Determine how CVortex can keep Figma as visual source of truth while also having deterministic, versioned design tokens consumable by code.

## DTCG format

- **[E1][E2]** Design Tokens Community Group 2025.10 is the first stable release and is described by the group as production-safe.
- **[E2]** It is a Community Group specification, not a W3C Standards Track Recommendation.
- **[E3]** A draft updated 2026-09-08 exists, but the latest published stable version remains 2025.10. CVortex should not build production tooling against draft-only behavior.

## Style Dictionary

- **[E4]** Style Dictionary 5.5.0 is the current release found in June 2026.
- **[E5]** Style Dictionary supports DTCG concepts, but its own documentation says the latest 2025.10 format does **not yet have full support** and work is still in progress in v5.
- Therefore Style Dictionary is a strong transformer candidate, not a no-research autopick.

## Canonical-source options

### Option A: Git DTCG JSON canonical, Figma synchronized/derived

Pros:
- version control, review, reproducibility, CI validation;
- straightforward generation of CSS variables and Tailwind mappings;
- architecture/tooling does not require Figma availability to build code.

Cons:
- requires an explicit Figma sync workflow;
- designers can create drift if editing variables directly without sync discipline.

### Option B: Figma Variables canonical, export to Git

Pros:
- designer-native authoring.

Cons:
- export/sync tooling becomes critical infrastructure;
- harder deterministic review if Figma changes race with Git;
- build pipeline should not depend on live Figma.

### Interpretation of "Figma is visual source of truth"

This does not necessarily require Figma to be the machine-readable canonical token store. A defensible split is:

- Figma: canonical visual/component design and reviewed variable semantics;
- Git DTCG JSON: canonical build artifact/source for code generation;
- sync/check tooling: detects drift between them.

This distinction must become an ADR in Phase 05, not a silent assumption.

## Figma MCP evidence

- **[E6]** Figma MCP can extract variables/components/layout context and can write native content back to canvas.
- **[E7]** Remote MCP is the recommended connection workflow.
- **[E8]** Write-to-canvas supports Codex and can create/modify frames, components, variables and auto layout.
- **[E8]** Writing requires a Full seat plus edit permission; Dev seats are read-only.
- **[E8]** Current write-to-canvas limitations include beta quality, 20 KB response limit per call, no image asset support, no custom fonts, and manual component publishing before Code Connect completes.

## Code Connect evidence

- **[E9][E10]** Figma moved new Code Connect integrations to framework-agnostic TypeScript template files.
- **[E9]** As of 2026-08-17 legacy framework-specific parsers are no longer actively maintained.
- New CVortex integration should therefore use template files, not start on the legacy React parser path.

## Candidate recommendation, not ADR

1. Use **DTCG 2025.10 stable** as the target token interchange format.
2. Spike **Style Dictionary 5.5+** against the exact token types CVortex needs and document unsupported DTCG corners.
3. Prefer **Git-held DTCG tokens as build canonical** while Figma remains visual/component source of truth, subject to Phase 05 ADR.
4. Use Figma **Remote MCP** where available.
5. Treat write-to-canvas as assisted authoring requiring human review, not an autonomous design authority.
6. Use **Code Connect template files** for new component mappings.
7. Build drift checks rather than pretending bidirectional synchronization is free.

## Confidence

High on Figma/Code Connect current capabilities; medium on token canonical-source choice until workflow spike proves sync ergonomics.

## Citations

- **[E1] DTCG homepage**, accessed 2026-09-12: https://www.designtokens.org/
- **[E2] DTCG FAQ / production readiness**, accessed 2026-09-12: https://www.designtokens.org/faq/
- **[E3] DTCG 2025.10 stable format**, accessed 2026-09-12: https://www.designtokens.org/TR/2025.10/format/
- **[E4] Style Dictionary releases**, accessed 2026-09-12: https://github.com/style-dictionary/style-dictionary/releases
- **[E5] Style Dictionary DTCG support**, accessed 2026-09-12: https://styledictionary.com/info/dtcg/
- **[E6] Figma MCP Introduction**, accessed 2026-09-12: https://developers.figma.com/docs/figma-mcp-server/
- **[E7] Figma Remote MCP setup**, accessed 2026-09-12: https://developers.figma.com/docs/figma-mcp-server/remote-server-installation/
- **[E8] Figma MCP Write to Canvas**, accessed 2026-09-12: https://developers.figma.com/docs/figma-mcp-server/write-to-canvas/
- **[E9] Code Connect migration to template files**, accessed 2026-09-12: https://developers.figma.com/docs/code-connect/templates-migration-guide/
- **[E10] Code Connect template files**, accessed 2026-09-12: https://developers.figma.com/docs/code-connect/template-files/
