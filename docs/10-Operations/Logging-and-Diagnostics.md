---
title: Logging and Diagnostics
status: implemented
owner: project
created: 2026-09-27
updated: 2026-09-29
tags: [operations, logging, diagnostics]
related: ["[[../03-ADR/ADR-0022-local-diagnostics|ADR-0022]]", "[[../00-Home/Error-Center-User-Guide|Error Center user guide]]", "[[M0-Runbook|M0 Runbook]]"]
---

# Logging and Diagnostics

For the sign-in flow, filters, status actions and user-visible error messages, start with the [Error Center user guide](../00-Home/Error-Center-User-Guide.md). This page is for the operator of the local Compose stack. The [diagnostics OpenAPI contract](../05-API/diagnostics.openapi.yaml) describes the protected endpoints.

## Pipeline and contract

Laravel writes JSON records to stderr in Compose. Every selectable non-null Laravel channel, including each member of a configured log stack, runs `StructuredLogs` before writing to its sink. `StructuredLogs` redacts nested sensitive keys, bearer credentials, credential-bearing headers, quoted or whitespace-delimited key/value secrets and sensitive URL query values before formatting. When an exception is present, the log keeps its class and at most twelve sanitized lines in `safe_stack`: the throw-site basename and line, then application caller frames, followed by framework frames, each with basename, line and class/function only. No arguments, absolute paths or exception messages are retained. Backend application events use dotted `event_name` identifiers and safe metadata only: timestamp, level, environment, service, component, error_code, exception_class, request_id, job_id, llm_run_id, application_id, user_id, route, queue/job/attempt, provider, operation, duration_ms and retryable when applicable. Omit inapplicable fields. Application code must not log request bodies, CV/recruiter/vacancy text, prompts, full provider responses, credentials or raw exception messages. `DEBUG` is development detail, `INFO` ordinary lifecycle, `WARNING` recovered/degraded operation, `ERROR` final user/job failure, and `CRITICAL` unavailable core dependencies or integrity failures. Validation and ordinary 4xx denials are not incidents.

Nginx replaces any client-supplied `X-Request-ID` with its own request ID before forwarding to Laravel. Laravel shares that trusted ID with logger context and returns it in the response. JSON API errors include stable `error.code`, safe `error.message`, `error.request_id`, and `error.retryable`; validation retains the Laravel `errors` map. Uncaught CLI/scheduler exceptions and unexpected HTTP exceptions are recorded as incidents while Laravel's normal reporting remains enabled. Known operator commands and the diagnostics prune command catch failures, record a safe console incident and print a `cli_…` reference. Final queue failures and unexpected MCP tool catches are recorded by the queue/MCP diagnostic boundary. Every boundary treats incident storage and logging as best-effort. HTTP exception responses retain only safe `Allow`, `Retry-After`, and rate-limit headers; cookies, redirects, arbitrary headers and malformed values are discarded. Provider retryability follows the failure category rather than generic HTTP status: transport, provider 429 and temporary-availability failures may be retried, while missing/invalid provider configuration, refusal, incomplete output and malformed output are terminal. Provider 429 returns `LLM_PROVIDER_RATE_LIMITED` and `retryable=true`. For 429, 408, 425 and 5xx provider responses, queued retries honor a bounded numeric `Retry-After` when supplied; without a valid delay they use the bounded exponential schedule. First-party HTTP 429 returns `RATE_LIMITED`. Configuration, refusal, incomplete and malformed categories have stable codes, and API responses, incidents and frontend messages share the same catalog. The frontend offers a Retry action only when the API sets the flag. Generated application output rejected by deterministic validation returns and records `LLM_OUTPUT_INVALID` and is linked to its LLM run and preparation. Queued payloads carry origin request/user IDs, workers add the job ID to log context, and final `JobFailed` events record queue, connection and attempt. Jobs back off for retryable provider categories and fail immediately for terminal ones. LLM failure records link to existing `llm_runs`/`vacancy_llm_runs` IDs and provider metadata. No second root correlation ID is created.

PostgreSQL `diagnostic_incidents` stores one row per fingerprint and lifecycle `OPEN`, `RESOLVED`, `IGNORED`, with category-derived retryability, impact and recovery action; `diagnostic_occurrences` stores recent detail including queue and connection when applicable. A retryable provider occurrence stores a validated integer retry delay from 0 to 86400 seconds when supplied; missing or invalid delays remain null, and no raw response header is stored. Unique extraction and analysis jobs keep their dispatch lock for both possible maximum 24-hour retry delays plus a ten-minute execution buffer, matching their three-attempt limit. Every error increments `occurrence_count`; approximately the latest thousand occurrences per fingerprint retain searchable references, with pruning every hundred events after that threshold. The same exception object is recorded once even when a synchronous queue failure bubbles into the request exception handler. `RESOLVED` reopens on recurrence, while `IGNORED` remains ignored. Effective admin status changes enter `audit_events`; a repeated no-op status request does not. Neither table is an audit log or an LLM accounting replacement. The admin-only Error Center offers severity/status/service/component/environment/code/provider/time filters, exact search for request/job/LLM/application IDs or code, paginated list, detail and lifecycle controls. Incident detail puts safe impact, retry status, next action and first/last seen before raw correlation metadata. Ordinary users cannot read or mutate diagnostics. Browser telemetry is authenticated, rate limited, accepts only the finite component allowlist `browser`, `app-root`, `global-root` and fixed event kinds, ignores supplied message/stack/user ID, and stores a server-derived user ID. If both diagnostic sinks fail, telemetry returns a safe 503 instead of claiming it was stored. Because browser code cannot choose other fingerprint fields, its open incident groups have finite cardinality; open groups remain retained under the lifecycle policy.

Horizon remains the queue execution/failure view. `make failed-jobs` lists sanitized final queue incidents through `diagnostics:failed-jobs`. Compose disables Laravel's raw failed-job database payload store; the existing project has no `failed_jobs` migration. Error Center shows a grouped incident with origin IDs; it does not mirror Horizon. Sensitive text is omitted from diagnostic storage, and stack lines retain only basenames, line numbers, class/function names and the throw-site marker (no arguments or absolute paths).

Unexpected 5xx failures reported on `/api/v1`, OAuth/MCP endpoints and OAuth discovery routes are persisted as incidents. Expected OAuth protocol/authentication rejections remain excluded. If `diagnostics:failed-jobs` cannot read PostgreSQL, it reports a safe CLI reference and exits nonzero; use the reference to search diagnostics when the database recovers. The command does not print SQL or driver exception details.

## Operator workflow

1. Ask for the safe error code, Reference ID, approximate time and action. Do not request the user's password, full CV, prompt or provider credential.
2. An active admin signs in and opens `/diagnostics` from the workspace link. Paste the exact request, job, LLM run or application ID, or error code in the primary search. Other filters combine with search; clear their chips if an expected result disappears. Provider compares exactly with the latest retained occurrence. `hours` filters `last_seen_at` to the last 24, 168 or 720 hours; the advanced dates also test `last_seen_at`. Default ordering is `OPEN` first, then CRITICAL/ERROR/WARNING, then newest; selectable sorts include last/first seen and occurrence count. The API returns 25 groups per page. Its list projection of the latest retained occurrence supplies operation/provider labels without exposing stack or identity data.
3. Read Cause, Impact, Recommended action and Retryable before technical context. The impact statement is category-level and does not prove data was unchanged; verify product state separately. Compare request/job/LLM/application IDs, attempt and provider with the product operation. Open one occurrence for its metadata. Technical details are closed initially; when needed, the throw site and application frames precede a separate framework disclosure. Detail returns the latest 20 retained occurrences; `occurrence_count` is the lifetime count for the group, even after old detail is pruned. A browser `digest` shown when telemetry fails is not necessarily a searchable request ID.
4. After confirming the actual operation recovered, set `RESOLVED`. A new matching failure reopens it. Use `IGNORED` only for a consciously accepted event; recurrence does not reopen that status. Status changes are audited. Neither state repairs the underlying operation.

| Scenario | Check | Expected boundary |
|---|---|---|
| API reports a safe 500 or provider 503 | Search its `Reference`; inspect `error_code`, `last_seen_at`, provider and related run | Response has no raw exception/stack; incident groups repeated failures |
| Career extraction or vacancy analysis fails in Horizon | Search request or job ID; compare `llm_run_id` and final `attempt` | Transient provider retries keep the product operation `PENDING` while queued; it becomes `FAILED` only when retries stop, and final queue failure creates one diagnostic occurrence |
| Browser fallback appears | Search returned Reference and `FRONTEND_RUNTIME_ERROR` around the time | Telemetry needs an authenticated session and available API/database; an unsent event may have no incident |
| Validation, authentication or authorization is rejected | Correct input/session/access; use request ID in short-lived logs if needed | Ordinary 4xx responses are not stored as incidents |
| Same error repeats | Compare `occurrence_count`, first/last seen and recent occurrences | One fingerprint stays one incident; older per-event IDs eventually expire |
| No Error Center result or PostgreSQL is down | Check health, then local backend/Horizon logs using the ID | Incident persistence can fail independently; stderr remains the fallback |

Useful local commands from the repository root:

```sh
docker compose ps
make logs SERVICE=backend
make logs SERVICE=horizon
make failed-jobs
```

`make logs-pretty SERVICE=backend` formats the last 200 JSON lines when Python 3 is installed on the host; `make logs` itself needs only Docker and Make. `make failed-jobs` reads PostgreSQL and will not work during a database outage. To find one reference in available backend logs, replace the placeholder with an exact safe ID:

```sh
docker compose logs --no-color --tail=200 backend | rg --fixed-strings '<request-id>'
```

The application logger sanitizes its records, but Compose also collects independent Nginx, PostgreSQL and other service output; their raw lines do **not** pass through Laravel's redactor. Request URLs or infrastructure diagnostics may appear there. Keep raw logs local and inspect them before sharing excerpts.

## Retention and fallback

Compose uses Docker's rotating `local` logs (`10m` × `5` per service); `make logs SERVICE=backend` and `docker compose logs` read them even when PostgreSQL is unavailable. `LOG_CHANNEL=daily` uses Laravel file retention where selected outside Compose. Every configured log channel and Laravel's emergency fallback applies `StructuredLogs`; if channel construction fails, the fallback retains only redacted message/context and a sanitized stack. `DIAGNOSTIC_OCCURRENCE_DAYS` defaults to 30; `DIAGNOSTIC_CLOSED_DAYS` defaults to 90. The scheduler runs `diagnostics:prune` daily. `make diagnostics-prune` runs the same **deleting** cleanup manually; use it only when applying the configured retention is intended. Open incidents are retained. Browser telemetry cannot create unlimited open groups because client-controlled fingerprint dimensions are restricted to finite allowlists; recent reference lookup is additionally bounded to approximately the latest thousand occurrences per fingerprint. This is local retention, not a backup policy.

If the UI is unavailable:

```bash
docker compose ps
docker compose exec -T backend php artisan route:list --path=api/v1/health
make logs SERVICE=backend
make logs SERVICE=frontend
make logs SERVICE=horizon
make failed-jobs
```

Check `/api/v1/health/live` and `/api/v1/health/ready` through the configured loopback Nginx port, then search the reported `request_id` in backend logs. If a job failed, compare `job_id` with Horizon/failed-jobs and the incident. For LLM failures inspect the linked run's safe provider/skill/status metadata; never copy prompts or keys into tickets. If PostgreSQL is down, use stderr and health probes until recovery. Incident `RESOLVED` means the symptom was handled; it is not proof that an external provider or product flow is healthy. There is no external log collector, alert delivery or long-term archive in this bounded implementation.
