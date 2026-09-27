---
title: Logging and Diagnostics
status: implemented
owner: project
created: 2026-09-27
updated: 2026-09-27
tags: [operations, logging, diagnostics]
related: ["[[../03-ADR/ADR-0022-local-diagnostics|ADR-0022]]", "[[M0-Runbook|M0 Runbook]]"]
---

# Logging and Diagnostics

## Pipeline and contract

Laravel writes JSON records to stderr in Compose. `StructuredLogs` redacts case-insensitive sensitive keys recursively and replaces bearer/key-value secrets before formatting. Events use dotted `event_name` identifiers and safe metadata only: timestamp, level, environment, service, component, error_code, exception_class, request_id, job_id, llm_run_id, application_id, user_id, route, queue/job/attempt, provider, operation, duration_ms and retryable when applicable. Omit inapplicable fields. Do not log request bodies, CV/recruiter/vacancy text, prompts, full provider responses, credentials or raw exception messages. `DEBUG` is development detail, `INFO` ordinary lifecycle, `WARNING` recovered/degraded operation, `ERROR` final user/job failure, and `CRITICAL` unavailable core dependencies or integrity failures. Validation and ordinary 4xx denials are not incidents.

HTTP middleware validates or generates `X-Request-ID`, shares it with logger context and returns it in header. JSON API errors include stable `error.code`, safe `error.message`, `error.request_id`, and `error.retryable`; validation retains the Laravel `errors` map. Queued payloads carry origin request/user IDs, workers add the job ID to log context, and the final `JobFailed` event records queue context. LLM failure records link to existing `llm_runs`/`vacancy_llm_runs` IDs and provider metadata. No second root correlation ID is created.

PostgreSQL `diagnostic_incidents` stores one row per fingerprint and lifecycle `OPEN`, `RESOLVED`, `IGNORED`; `diagnostic_occurrences` stores recent detail. Every error increments `occurrence_count`; approximately the latest thousand occurrences per fingerprint retain searchable references, with pruning every hundred events after that threshold. `RESOLVED` reopens on recurrence, while `IGNORED` remains ignored. Admin status changes enter `audit_events`. Neither table is an audit log or an LLM accounting replacement. The admin-only Error Center offers severity/status/service/component/environment/code/time filters, exact search for request/job/LLM/application IDs or code, paginated list, detail and lifecycle controls. Ordinary users cannot read or mutate diagnostics. Browser telemetry is authenticated, rate limited, accepts only a bounded component and fixed kind, ignores supplied message/stack/user ID, and stores a server-derived user ID.

Horizon remains the queue execution/failure view. `make failed-jobs` lists sanitized final queue incidents through `diagnostics:failed-jobs`. Compose disables Laravel's raw failed-job database payload store; the existing project has no `failed_jobs` migration. Error Center shows a grouped incident with origin IDs; it does not mirror Horizon. Sensitive text is omitted from diagnostic storage, and stack frames retain only basename, line and function (no arguments or absolute path).

## Retention and fallback

Compose uses Docker's rotating `local` logs (`10m` × `5` per service); `make logs SERVICE=backend` and `docker compose logs` read them even when PostgreSQL is unavailable. `make logs-pretty SERVICE=backend` renders the same JSON in a readable local view without changing event semantics. `LOG_CHANNEL=daily` uses Laravel file retention where selected outside Compose. `DIAGNOSTIC_OCCURRENCE_DAYS` defaults to 30; `DIAGNOSTIC_CLOSED_DAYS` defaults to 90. The scheduler runs `diagnostics:prune` daily, and `make diagnostics-prune` invokes it manually. Open incidents are retained. This is local retention, not a backup policy.

If the UI is unavailable:

```bash
docker compose ps
docker compose exec -T backend php artisan route:list --path=api/v1/health
make logs SERVICE=backend
make logs SERVICE=frontend
make logs SERVICE=horizon
make failed-jobs
```

Check `/api/v1/health/live` and `/api/v1/health/ready` through the configured loopback Nginx port, then search the reported `request_id` in backend logs. If a job failed, compare `job_id` with Horizon/failed-jobs and the incident. For LLM failures inspect the linked run's safe provider/skill/status metadata; never copy prompts or keys into tickets. If PostgreSQL is down, use stderr and health probes until recovery. Incident `RESOLVED` means the symptom was handled; it is not proof that an external provider or product flow is healthy.
