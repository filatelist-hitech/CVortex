# CVortex

**Your career, in context.**

CVortex is a personal Job Search OS. It keeps career evidence connected to vacancy analysis and application drafts, so candidate-facing statements can be reviewed against confirmed facts.

## What works today

The current local Preview lets an invited user:

- add confirmed Career Facts manually or paste career/resume text for AI-assisted extraction and human review;
- paste vacancy text and compare its requirements with confirmed career evidence across seven dimensions;
- review an explainable application-priority recommendation and its gaps or unknowns;
- prepare resume recommendations and short or standard cover-letter drafts, edit or reject them, and explicitly approve content that passes Truth Guard.

The optional vacancy URL is saved as metadata; CVortex does not fetch it. File import, DOCX/PDF generation, employer history, interview preparation, outcome tracking and automatic application submission are not available in this Preview. AI-backed actions require provider configuration; a new local installation defaults to `AI_PROVIDER=none`.

CVortex is invite-only and in pre-release development. Preview 0.1 still needs recorded real-user validation and feedback. See the [current product status and roadmap](docs/01-Product/Roadmap.md) for the distinction between shipped slices and later plans.

## Run locally

Requirements: Git, Make, Docker with Compose, and a running Docker engine. From a fresh checkout:

```sh
git clone https://github.com/filatelist-hitech/CVortex.git
cd CVortex
make init
docker compose --env-file .env config --quiet
make up
make migrate
```

Check that the services are healthy and the application dependencies are ready:

```sh
docker compose ps
health_authority="$(docker compose port nginx 80)"
curl -fsS "http://${health_authority}/api/v1/health/ready"
```

Open the `APP_URL` value in the ignored root `.env` (default: `http://localhost:8080`). `make init` creates that file and generates local secrets. See [Local Development](docs/10-Operations/Local-Development.md) before changing database or port settings.

To create the first administrator and issue an invitation, follow [Access and first sign-in](docs/10-Operations/M1-Access-Core.md). A new install has no default account.

## Start here

- [User Guide](docs/00-Home/User-Guide.md) — sign-in, Career Facts, vacancies, recommendations, drafts, privacy and current limits.
- [Local Development](docs/10-Operations/Local-Development.md) — installation, environment, start/stop/update and troubleshooting.
- [Error Center guide](docs/00-Home/Error-Center-User-Guide.md) — what to do with a user-facing error.
- [Documentation Map](docs/00-Home/Documentation-Map.md) — user, operator, developer and architecture documents.
- [Product Roadmap](docs/01-Product/Roadmap.md) — what is implemented, active or planned.

## Project documentation

User instructions live under `docs/00-Home/` and local operation guides under `docs/10-Operations/`. Architecture, data/API contracts, security, AI and accepted decisions remain under their dedicated documentation areas. See the [Documentation Map](docs/00-Home/Documentation-Map.md) or [Architecture Decision Index](docs/03-ADR/INDEX.md).

## Privacy basics

Career, vacancy and draft data are private and owner-scoped. The local Compose database and private file storage persist in Docker named volumes. Nginx is the only host-published service and binds to loopback by default. Do not commit real resumes, recruiter correspondence, credentials or other personal career data. When an AI provider is configured, the relevant text for that operation is sent to that provider; keep secrets out of pasted source material.
