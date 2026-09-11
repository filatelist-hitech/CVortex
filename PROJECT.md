# CVortex

## Product Purpose

CVortex is a personal Job Search OS.

Its purpose is to help users prepare truthful, relevant, traceable and internally consistent job applications using confirmed career information, vacancy context, employer history and research.

## Product Principles

### Truth-first

Never invent candidate experience, skills, achievements, responsibilities or other career facts.

### Traceability

Generated candidate-facing content must be traceable through:

`Generated Content → Claims → confirmed Career Facts`

### Consistency-first

Statements made to the same employer must remain consistent with previous applications, conversations and confirmed facts.

### Human Approval

CVortex does not automatically submit applications or send employer communications without explicit user approval.

### Deterministic Before AI

If a problem can be reliably solved with normal code, schema validation, rules or SQL, prefer that over LLM use.

### Provider-independent AI

AI architecture separates:

- Provider
- ModelPolicy
- Skill
- Agent
- Workflow
- Tool

Business logic must not depend directly on one LLM provider.

### Security-first

Vacancies, recruiter messages, websites and uploaded documents are untrusted input.

### Documentation-first

Important architecture decisions are documented using ADRs.

Project documentation is normal Markdown stored in Git and usable as an Obsidian Vault.

### No Premature Complexity

Do not introduce without demonstrated need:

- Kubernetes
- microservices
- Kafka
- event sourcing
- standalone vector databases
- GraphQL
- native mobile applications
- fine-tuning

## Approved Stack Direction

These directions are approved but do not imply fixed dependency versions.

### Backend

- PHP
- Laravel
- API-first architecture

### Frontend

- Next.js
- React
- TypeScript
- responsive PWA

### Data

- PostgreSQL

### Queue and Cache

- Redis
- Laravel Horizon

### Web

- Nginx

### Documents

- DOCX templates/generation
- LibreOffice headless for PDF conversion

### Design

- Figma is the source of visual truth.

### Documentation

- Markdown
- Git
- Obsidian-compatible documentation structure

## Deployment Direction

Initial deployment is local-first, targeting a Mac-based Docker Compose environment.

The architecture should permit later migration to VPS/cloud infrastructure without unnecessary core rewrites.

Docker Compose itself is not created during Phase 00.

## API-first

Desktop web, responsive PWA, future mobile clients and future browser integrations must consume a shared application API.

## Access Model

Registration is invite-only.

The system is multi-user.

Initial roles:

- admin
- user

Private user data must be isolated between users.

## AI Architecture

CVortex uses a provider-independent LLM architecture.

Concrete providers, models, model mappings and pricing are configuration/research concerns and must not be treated as permanent architecture facts.

## Pending Architecture Decision

The canonical location and lifecycle for CVortex runtime/product LLM Skills is intentionally not defined during Phase 00.

`.agents/` is reserved for DEVELOPMENT AGENTS and must not become the canonical location for runtime CVortex Skills.

The runtime Skill location will be decided during the AI Architecture phase.
