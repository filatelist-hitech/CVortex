# Documentation Scoped Instructions

These instructions apply to files under `docs/`.

## Format

Documentation must use Markdown.

Important documents should include frontmatter containing appropriate metadata such as:

- title;
- status;
- owner;
- created;
- updated;
- tags;
- related.

## ADR Discipline

Material architectural decisions must be represented by ADRs.

Do not silently introduce architectural decisions only inside general documentation.

Accepted ADRs must not be contradicted without an explicit update/superseding decision.

## Diagrams

Use Mermaid where a text-based engineering diagram reasonably represents the system.

Keep diagrams maintainable and Git-reviewable.

## Obsidian

The documentation tree may be used as an Obsidian Vault.

However, documents must remain readable and useful in Git-based viewers without Obsidian.

## Architecture

No undocumented architectural decisions.

If documentation reveals a missing material architecture decision, create or propose the appropriate ADR instead of burying the choice in prose.
