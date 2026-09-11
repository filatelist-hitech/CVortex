# CVortex

**Your career, in context.**

CVortex is a personal Job Search OS for adapting resumes to vacancies, generating cover letters, preserving employer context, preparing for interviews, and tracking the effectiveness of a job search.

## Project principles

- **Truth-first:** candidate statements must be grounded in confirmed career facts.
- **Traceability:** generated content must remain traceable through claims to confirmed facts.
- **Consistency-first:** previous applications and employer-specific claims must not silently contradict each other.
- **Human approval:** CVortex does not automatically submit applications.
- **Deterministic before AI:** use code, validation, SQL, and rules when they are more reliable than an LLM.
- **Local-first / API-first:** local Docker Compose first, with a path to VPS/cloud later.
- **Security-first:** vacancies, recruiter messages, websites, and imported documents are untrusted input.

## Stack direction

- Backend: PHP 8.4+ / Laravel
- Frontend: Next.js / React / TypeScript
- Database: PostgreSQL
- Queue/cache: Redis + Laravel Horizon
- Web: Nginx
- Documents: DOCX templates + LibreOffice headless to PDF
- Infrastructure: Docker Compose
- Mobile MVP: responsive PWA

Concrete dependency and model versions are intentionally not fixed here before research.

## Branches

- `main` — stable/release branch.
- `stage` — integration and staging branch.
- `feature/*`, `fix/*`, `chore/*`, `docs/*`, `ci/*` — short-lived branches based on `stage`.
- `hotfix/*` — emergency fixes based on `main`, merged back to both `main` and `stage`.

Normal development pull requests target `stage`. Promotion to `main` happens through a separate release PR.

See [CONTRIBUTING.md](CONTRIBUTING.md) for the repository workflow.

## Sensitive data

Do not commit real resumes, recruiter correspondence, API keys, tokens, credentials, production exports, or other private career data to the repository. Use sanitized fixtures for tests and examples.
