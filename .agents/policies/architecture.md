# Architecture Policy

## Purpose

Keep CVortex simple, traceable, reversible and aligned with accepted architecture decisions.

## Mandatory Rules

- Read relevant accepted ADRs before architecture changes.
- Prefer simple modular architecture.
- Use deterministic solutions before AI when reliable.
- Maintain API-first and local-first principles.
- Preserve provider independence in AI architecture.
- Keep user data isolated by ownership.
- Prefer reversible decisions where uncertainty exists.
- Document material decisions through ADRs.

## Prohibited Behavior

Do not introduce without demonstrated need:

- Kubernetes;
- microservices;
- Kafka;
- event sourcing;
- standalone vector databases;
- GraphQL;
- native mobile;
- fine-tuning.

Do not silently override accepted ADRs.

Do not fix concrete library versions, LLM model names or pricing without required research.

## Completion Checks

- Relevant ADRs were reviewed.
- Alternatives were considered for material decisions.
- The simplest sufficient option was selected.
- Security and authorization implications were considered.
- Documentation matches the decision.
- Any ADR changes are explicit.
