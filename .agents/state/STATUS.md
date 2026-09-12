# CVortex Project State

## Last Completed Foundation Phase

`07-design-foundation` — **completed / PASS**.

Completed foundation sequence:

- `00-ai-system-bootstrap`
- `01-project-knowledge-bootstrap`
- `02-research-plan`
- `03-technical-research`
- `04-product-integrations-hiring-research`
- `05-architecture-decision-freeze`
- `06-product-data-ai-security-design`
- `07-design-foundation`

## Roadmap reset

The former horizontal Phase 08–12 implementation order is superseded by the accepted value-driven roadmap in `docs/01-Product/Roadmap.md`.

Accepted architecture is unchanged. The reset changes sequencing/task size, not Laravel/PostgreSQL/Redis/API-first/Truth Guard/provider-independence/ownership/design/document-rendering decisions.

Old Phase 08–12 task specs are preserved under `.agents/tasks/legacy/` as requirement inventory and are not execution authority.

Hardened pre-reset references preserved exactly:

- Phase 08 final hardened spec from commit `6ca25b09550b8315913f06382dff28f8d326aa06`;
- Phase 09 final hardened spec from commit `9267f15545cca70a245798a58179742789cf2737`.

Their necessary M0/First-Value decisions were extracted into the new bounded specs.

## Active implementation roadmap

```text
M0 Runnable Core
→ M1.1 Access Core
→ M1.2 Career Core
→ M1.3 Vacancy Core
→ M1.4 Application Draft
→ Preview 0.1
→ M2 Real Application Package
→ M3 Imports & Integrations
→ M4 Employer Journey
→ M5 Outcomes & Analytics
→ M6 Distribution & Hardening
```

Only M0 and M1 slices have execution specs now. M2+ detail is intentionally created just-in-time after real product validation.

## Current repository state

- no Laravel/Next.js application exists yet;
- no Docker Compose M0 exists yet;
- no product migrations/domain code exist yet;
- runtime AI directory remains an architecture/canonical-location baseline rather than implemented Skills;
- Phase 07 design tokens exist and may be consumed by M0;
- Figma live file exists but canvas review remains limited by previously recorded MCP Starter-plan capability/rate limitation.

## Next authorized task

`m0-runnable-core`

Authority is `.agents/state/NEXT.md` + blockers + `.agents/tasks/m0-runnable-core.md`.

## Validation of roadmap-reset branch

Documentation/task migration only. No product runtime behavior, dependency, migration or infrastructure change is claimed by the reset itself.

Before executing M0, review the actual diff and ensure all legacy specs are preserved and `NEXT.md` points only to M0.
