---
title: QA-004 execution evidence
status: verified-local / awaiting-review
created: 2026-10-12
updated: 2026-10-12
tags: [qa, career, truth-guard, synthetic]
---

# QA-004 evidence

Base: `5cfe18d` (`origin/stage` at preflight). Explicit owner override of NEXT;
one agent, sequential execution. This package is separate from the historical
2026-10-11 audit. QA-001–003 and historical outcomes are not reclassified.

## Reproduction and implementation

Career previously rendered Claim badge/statement without the existing PATCH action.
`CareerClaim` exposes native details/fieldset/radios, Russian explanations,
source/type/excerpt, explicit submit, busy/error/retry/result announcements and
focus on the result. No default selection or mutation on hydration. Eligibility
uses owner-scoped GET fact_ids, CONFIRMED, exact assertion, nonempty provenance
and absence of a confirmed superseding fact. Backend remains the final authority.

Existing contract: GET `/api/v1/career`; PATCH
`/api/v1/career/claims/{id}/resolve` with `{career_fact_id}`. No production backend,
endpoint, schema, dependencies or token changes. HTTP success can still carry BLOCK;
UI never infers PASS from transport success. Repeated valid resolution is accepted
by the existing service; other invalid evidence can keep the result BLOCK.

## Environment and reproduction

Only synthetic users/facts/vacancies. Dedicated Compose project `cvortexqa004`,
PostgreSQL volume `cvortexqa004_postgres_data`, DB `cvortex_qa004_synthetic`,
runtime `qa004_app` (not migration role), Redis and Horizon. Development DB is
never a connection target. Backend, Horizon and fakeprovider use only internal
networks; browser edge exposes Nginx on `127.0.0.1:18084`. Mock provider has no
outbound client. Browser records external request origins. No real AI call.

Use current repository `compose.yaml` plus `compose.override.yaml` in this directory.
Private env file outside Git needs normal `.env.example` keys, unique synthetic
Postgres credentials/APP_KEY, POSTGRES_DB=cvortex_qa004_synthetic,
POSTGRES_USER=qa004_migration, POSTGRES_RUNTIME_USER=qa004_app, CVORTEX_PORT=18084,
APP_URL=http://127.0.0.1:18084, AI_PROVIDER=openai,
OPENAI_BASE_URL=http://fakeprovider:8000/v1,
OPENAI_API_KEY=synthetic-canary, both model variables=qa-deterministic.
No real provider key belongs in this environment.

```bash
docker compose --env-file "$QA_ENV_FILE" -p cvortexqa004 -f compose.yaml -f docs/09-QA/evidence/qa004-2026-10-12/compose.override.yaml up -d --no-build postgres redis fakeprovider backend frontend
docker compose --env-file "$QA_ENV_FILE" -p cvortexqa004 -f compose.yaml -f docs/09-QA/evidence/qa004-2026-10-12/compose.override.yaml run --rm --no-deps migration php artisan migrate --force
docker compose --env-file "$QA_ENV_FILE" -p cvortexqa004 -f compose.yaml -f docs/09-QA/evidence/qa004-2026-10-12/compose.override.yaml up -d --no-build horizon nginx
```

Provision `seed.php` via `docker exec -i` into QA backend, with QA_RUN_ID (fresh
lowercase alphanumeric ID each run) and QA_SYNTHETIC_PASSWORD privately supplied.
Seed refuses another DB and creates users only; Career ambiguity is created by
browser actions. Run `node scripts/test-qa004-browser.cjs` with QA_RUN_ID,
QA_SYNTHETIC_PASSWORD, QA_PLAYWRIGHT_MODULE (existing Playwright package),
QA_CHROMIUM_PATH and QA_OUTPUT_DIR. Passwords/env are never copied into evidence.
Repository's existing JavaScript Playwright workflow is reused without installing
another runner. Read-only HTTP GET checks accompany actual UI mutations; fixture
provisioning is separate. Resolution/reanalysis/generation/approval use actual UI,
no console mutation, direct resolution workaround or forced Claim status.

## Validation and limitations

Results and exact invocations are recorded in [validation.json](validation.json): frontend 131 tests (23 QA-004), Career 52/417 and Application 19/253 PASS; 15 browser scenarios PASS. Pint/PHPStan and scoped sequential security/diff review PASS. This is the executing agent review, not independent review.
`browser.json` records targeted scenario results and sanitized path/status events.
Screenshots contain synthetic statements only. The first workflow attempt exposed
an incorrect harness expectation: approval buttons exist but are disabled until
acceptance; that failure is retained separately. Initial local tests exposed a
legacy frontend fixture without fact_ids/raw PASS expectations, an unsupported
User::factory fixture, and a missing runtime-ai mount; corrected runs are separate.
Fresh TypeScript initially lacked generated Next LayoutProps; build generated it.

Automated keyboard/focus/naming/reflow checks are smoke evidence. Native 200% zoom,
audible screen-reader and named human visual/accessibility gates remain OPEN.
Preview 0.1, live-provider readiness, release and other QA defects remain outside
this task. No merge, auto-approval or application submission.
