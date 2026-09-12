---
status: research-complete
date: 2026-09-12
phase: 03-technical-research
owner: CVortex
architecture_decision: none
---

# Component Primitive Options

## Goal

Select a short list of accessible, unstyled/headless primitive layers that can implement the CVortex Figma design system without becoming the visual source of truth themselves.

## Option A: Base UI

Evidence:
- **[E1]** Open-source, unstyled/headless React components.
- Accessibility is a primary focus; components follow WAI-ARIA patterns and are tested across browsers, screen readers and devices.
- Works with Tailwind and maintained bundlers including Turbopack.
- React 17+ support gives ample compatibility margin for React 19.

Fit for CVortex:
- Strong visual independence.
- Good match for a custom technical design system.
- Current active development and modern complex primitives make it a strong spike candidate.

## Option B: Radix Primitives

Evidence:
- **[E2][E3]** Low-level, unstyled, accessible primitives with focus management, keyboard navigation and WAI-ARIA patterns.
- Mature ecosystem and proven design-system use.

Fit:
- Very safe known quantity.
- Excellent fallback if Base UI compatibility or component coverage is weaker in the actual CVortex prototype.

## Option C: React Aria

Evidence:
- **[E4]** Unstyled components/hooks with accessibility, internationalization and interaction behavior built in.
- Strong cross-device/input-modality testing.

Fit:
- Particularly attractive if internationalization and complex interaction semantics dominate.
- API shape can be more conceptually involved than lighter primitive libraries.

## shadcn/ui position

Treat shadcn/ui as a **code distribution/scaffolding approach**, not as the CVortex design system and not as visual source of truth. Its generated components may accelerate implementation, but tokens/components still belong to CVortex and must map to Figma.

## Preliminary comparison

| Criterion | Base UI | Radix | React Aria |
|---|---|---|---|
| Unstyled/headless | Strong | Strong | Strong |
| Accessibility | Strong | Strong | Strong |
| Tailwind fit | Strong | Strong | Strong |
| Complex interactions | Strong/current | Strong/mature | Very strong |
| i18n depth | Good | Good | Strongest signal |
| Design-system ownership | Excellent | Excellent | Excellent |
| Preliminary CVortex fit | **Spike first** | Backup/benchmark | Spike where complex/i18n matters |

## Candidate recommendation, not ADR

Run a Phase 07 component spike comparing at least:

- Dialog/Drawer
- Select/Combobox
- Tooltip
- Tabs
- Dropdown
- Table-adjacent interactions
- mobile focus/touch behavior

Start the spike with **Base UI**, compare against Radix and React Aria on the hardest controls, and decide from measured accessibility/API/design-token fit.

## Confidence

Medium-high. Library choice depends on concrete component ergonomics and Figma mapping, which are intentionally not implemented in Phase 03.

## Citations

- **[E1] Base UI About**, accessed 2026-09-12: https://base-ui.com/react/overview/about
- **[E2] Radix Primitives Introduction**, accessed 2026-09-12: https://www.radix-ui.com/primitives/docs/overview/introduction
- **[E3] Radix Accessibility**, accessed 2026-09-12: https://www.radix-ui.com/primitives/docs/overview/accessibility
- **[E4] React Aria Getting Started**, accessed 2026-09-12: https://react-spectrum.adobe.com/react-aria/getting-started.html
