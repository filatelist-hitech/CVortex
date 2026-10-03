# CVortex

**Your career, in context.**

CVortex is a personal Job Search OS for preparing truthful, relevant application drafts from confirmed career facts and vacancy context.

## Current status

M1.1–M1.3 are implemented. The M1.4 application-draft implementation is in `stage`, but Preview 0.1 still awaits recorded real-user end-to-end acceptance and feedback. M2 has not started and remains gated on that evidence.

## What works today

- Invite-only sign-in and a private, owner-scoped career workspace.
- Add confirmed Career Facts manually without AI, or paste career text for provider-assisted extraction into facts that remain pending until reviewed.
- Paste vacancy text, inspect extracted requirements, compare them with confirmed evidence across seven dimensions, and review an explainable priority recommendation.
- Prepare resume recommendations and short or standard cover-letter drafts; review, edit, accept or reject them, then explicitly approve content that passes Truth Guard.

AI-backed extraction, vacancy analysis and draft generation require a configured provider. New installations default to `AI_PROVIDER=none`. A vacancy URL is metadata only; CVortex does not fetch the page.

## Preview workflow

```mermaid
flowchart LR
  SignIn[Invite and sign in] --> Facts[Confirm career facts]
  Facts --> Vacancy[Paste vacancy text]
  Vacancy --> Match[Review match and gaps]
  Match --> Drafts[Prepare and review drafts]
  Drafts --> Guard[Truth Guard]
  Guard --> Approval[Explicit user approval]
  Approval --> Stop([Stop before submission])
```

The full [Process Map](docs/00-Home/Process-Map.md) covers provenance, local startup and diagnostics.

## Run locally

Requirements: Git, Make, Docker with Compose, and a running Docker engine. From a fresh checkout:

```sh
git clone https://github.com/filatelist-hitech/CVortex.git
cd CVortex
make init
docker compose --env-file .env config --quiet
make up
make migrate
health_authority="$(docker compose port nginx 80)"
curl -fsS "http://${health_authority}/api/v1/health/ready"
```

After readiness succeeds, open the `APP_URL` value in the ignored root `.env` (default: `http://localhost:8080`). See [Local Development](docs/10-Operations/Local-Development.md) for setup and troubleshooting. To create the first administrator and invite a user, follow [Access Core](docs/10-Operations/M1-Access-Core.md).

## Documentation

- [User Guide](docs/00-Home/User-Guide.md) — sign-in, Career Facts, vacancies and application drafts.
- [Process Map](docs/00-Home/Process-Map.md) — the current Preview workflow and supporting processes.
- [Error Center guide](docs/00-Home/Error-Center-User-Guide.md) — what users and administrators do when an operation fails.
- [Documentation Map](docs/00-Home/Documentation-Map.md) — user, operator, product and technical references.
- [Product Roadmap](docs/01-Product/Roadmap.md) — implementation status and future milestones.

## Current boundaries

The Preview does not generate DOCX/PDF application packages or submit applications. Inbound MCP is disabled by default and read-only when enabled. Career, vacancy and draft data can contain sensitive personal information; do not commit real career data or credentials.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for the repository workflow.
