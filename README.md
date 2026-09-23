# CVortex

**Your career, in context.**

CVortex is a personal Job Search OS for adapting resumes to vacancies, generating cover letters, preserving employer context, preparing for interviews, and tracking the effectiveness of a job search.

## Project status

CVortex is in active pre-release development.

The completed slices are **M0 · Runnable Core**, **M1.1 · Access Core**, and **M1.2 · Career Core**. M1.3 Vacancy Core PR #27 has six actionable review findings under remediation. **M1.4 · Application Draft** remains blocked until PR #27 merges into `stage` and has not started.

M1.2 is **not the MVP**. M1.1–M1.4 together form M1's first complete workflow and target Preview 0.1 (`v0.1.0`). M2 targets the practical application-package MVP (`v0.2.0`). A completed slice or merged PR does not create a release; release tags are created only from validated `main` commits.

The accepted release roadmap is:

| Milestone | Product checkpoint | Target version | Release mode |
|---|---|---|---|
| M0 · Runnable Core | Runnable technical baseline | `v0.1.0-alpha.1` | Optional prerelease |
| M1 · First Value | First truthful end-to-end workflow / Preview 0.1 | `v0.1.0` | Preview |
| M2 · Real Application Package | First practical application package | `v0.2.0` | MVP |
| M3 · Imports & Integrations | Safe imports and supported source integrations | `v0.3.0` | Minor |
| M4 · Employer Journey | Employer/recruiter context and interview workflow | `v0.4.0` | Minor |
| M5 · Outcomes & Analytics | Evidence-based job-search analytics | `v0.5.0` | Minor |
| M6 · Distribution & Hardening | VPS/cloud path and operational hardening | `v0.6.0` | Minor |

Release tags are created from validated `main` commits only. Completing a milestone on `stage` does not itself create a Git tag or GitHub Release. `v1.0.0` remains a separate explicit stability decision after M6.

See [docs/01-Product/Roadmap.md](docs/01-Product/Roadmap.md) and [.github/roadmap.yml](.github/roadmap.yml) for the canonical roadmap and release metadata.

## M0 quick start

Host prerequisites:

- Git;
- Docker with Compose;
- Make.

From a clean checkout:

```bash
make init
make up
```

CVortex is exposed through Nginx on the loopback interface at `http://127.0.0.1:8080` by default. Override the port with `CVORTEX_PORT` in the root `.env` when needed.

Useful commands:

```bash
make test
make lint
make logs SERVICE=backend
make shell SERVICE=backend
make down
```

`make down` is intentionally non-destructive for the persistent PostgreSQL and private-storage state defined by M0.

## Project principles

- **Truth-first:** candidate statements must be grounded in confirmed career facts.
- **Traceability:** generated content must remain traceable through claims to confirmed facts.
- **Consistency-first:** previous applications and employer-specific claims must not silently contradict each other.
- **Human approval:** CVortex does not automatically submit applications.
- **Deterministic before AI:** use code, validation, SQL, and rules when they are more reliable than an LLM.
- **Local-first / API-first:** local Docker Compose first, with a path to VPS/cloud later.
- **Security-first:** vacancies, recruiter messages, websites, and imported documents are untrusted input.

## Current stack

- Backend: PHP / Laravel
- Frontend: Next.js / React / TypeScript
- Database: PostgreSQL
- Queue/cache: Redis + Laravel Horizon
- Web: Nginx
- Infrastructure: Docker Compose
- Documents: DOCX templates + LibreOffice headless to PDF are planned for M2
- Distribution: responsive web first; broader PWA/distribution hardening belongs to later milestones

Exact runtime and dependency versions are pinned in the implementation artifacts and lockfiles rather than duplicated here. The repository files are the version authority for the current runtime.

## Branches

- `main` — stable/release branch.
- `stage` — integration and staging branch.
- `feature/*`, `fix/*`, `chore/*`, `docs/*`, `ci/*` — short-lived branches based on `stage`.
- `hotfix/*` — emergency fixes based on `main`, merged back to both `main` and `stage`.

Normal development pull requests target `stage`. Promotion to `main` happens through a separate release PR.

See [CONTRIBUTING.md](CONTRIBUTING.md) for the repository workflow.

## Sensitive data

Do not commit real resumes, recruiter correspondence, API keys, tokens, credentials, production exports, or other private career data to the repository. Use sanitized fixtures for tests and examples.
