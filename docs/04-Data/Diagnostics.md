---
title: Diagnostics data model
status: implemented
owner: project
created: 2026-09-27
updated: 2026-09-29
tags: [data, diagnostics, erd]
related: ["[[../03-ADR/ADR-0022-local-diagnostics|ADR-0022]]", "[[../10-Operations/Logging-and-Diagnostics|Logging and Diagnostics]]"]
---

# Diagnostics data model

```mermaid
erDiagram
  DIAGNOSTIC_INCIDENT ||--o{ DIAGNOSTIC_OCCURRENCE : groups
  USER |o--o{ DIAGNOSTIC_OCCURRENCE : caused_by
  DIAGNOSTIC_INCIDENT {
    ulid id PK
    char fingerprint UK
    string status
    string severity
    string error_code
    string service
    string component
    string environment
    string message
    boolean retryable
    string impact
    string recovery_action
    string exception_class
    bigint occurrence_count
    timestamp first_seen_at
    timestamp last_seen_at
  }
  DIAGNOSTIC_OCCURRENCE {
    ulid id PK
    ulid incident_id FK
    string request_id
    string job_id
    ulid llm_run_id
    ulid application_id
    ulid user_id FK
    string route
    string error_ref
    string operation
    string provider
    string queue
    string connection
    smallint attempt
    text safe_stack
    timestamp created_at
  }
```

`fingerprint` is SHA-256 of stable error code, component, exception class and operation. `occurrence_count` counts every occurrence, including those pruned from the bounded detail history. Approximately the latest thousand occurrences per fingerprint retain searchable references. `status` is `OPEN`, `RESOLVED` or `IGNORED`; recurrence reopens `RESOLVED` and an ignored incident remains ignored. `retryable`, `impact` and `recovery_action` come from the stable error category, not the exception text. `safe_stack` is bounded to the throw site plus at most eleven caller frames, with application frames first; the UI keeps framework frames collapsed. `request_id`, `job_id`, `llm_run_id` and `application_id` are optional correlation references rather than cross-domain foreign keys: failed dependencies must not block an incident. The `user_id` foreign key is nullable and becomes null when a user is removed.

This is system diagnostics, not an owner-scoped Career or Application aggregate. Only an authenticated active admin can query or mutate it through the API. User IDs and technical frames are admin-visible metadata; no candidate content, provider prompt, credential or arbitrary frontend text is stored. Indexes cover fingerprint, status/time, code/time, incident/time and correlation IDs. Active incidents survive retention; old occurrences and closed incidents are pruned according to the operational policy.
