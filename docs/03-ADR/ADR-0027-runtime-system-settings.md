---
title: ADR-0027 — Runtime System Settings and Administrative Configuration Control Plane
status: accepted
decision_nature: DERIVED_ARCHITECTURAL_DECISION
owner: project
created: 2026-10-24
updated: 2026-10-24
tags: [architecture, configuration, admin, security, settings]
related: [ADR-0001, ADR-0002, ADR-0004, ADR-0005, ADR-0007, ADR-0010, ADR-0011, ADR-0015, ADR-0019, ADR-0022]
---

# ADR-0027 — Runtime System Settings and Administrative Configuration Control Plane

## Context

CVortex deployment relies on environment variables (`.env`) for bootstrap, networking, secrets, and container infrastructure. However, application-level operational parameters (such as default invitation TTL, login rate limits, character extraction budgets, active AI models, and email parameters) require frequent tuning by administrators without redeployment, container restarts, or manual SSH access.

Modifying `.env` dynamically from web PHP workers (`file_put_contents` followed by `config:clear`) is an established anti-pattern in containerized environments: it breaches immutable container immutability, causes race conditions between parallel PHP-FPM workers, risks file truncation, and poses severe security hazards (such as secret corruption or command injection).

## Decision

Adopt **Option C: Code-defined Typed Settings Registry with Encrypted Database Overrides and Strict Precedence Resolution**.

```mermaid
flowchart TD
    subgraph Bootstrap [Deployment / Bootstrap Boundary]
        ENV[".env / Docker Compose"] --> INFRA["Infrastructure & Security Root of Trust (APP_KEY, DB credentials, Ports)"]
    end

    subgraph Registry [Application Code Boundary]
        REG["SettingDefinitionRegistry\n(Code-defined metadata, types, validation rules, fallback keys)"]
    end

    subgraph Storage [Persistence Boundary]
        DB[("system_settings Table\nJSON values + Crypt encrypted_value + versioning")]
    end

    subgraph Resolution [Runtime Resolution Precedence]
        LOCK{"Is Environment Locked?"}
        LOCK -- Yes --> RET_ENV["Environment Locked Value"]
        LOCK -- No --> DB_CHECK{"Has DB Override?"}
        DB_CHECK -- Yes --> RET_DB["Decrypted DB Override"]
        DB_CHECK -- No --> FALLBACK_CHECK{"Has ENV Fallback?"}
        FALLBACK_CHECK -- Yes --> RET_FALLBACK["Config/ENV Fallback"]
        FALLBACK_CHECK -- No --> RET_DEF["Application Default"]
    end

    subgraph AdminUI [Admin Control Plane (/admin/settings)]
        CTRL["AdminSettingsController (Role: admin only)"]
        UI["React Admin Settings UI (Categories, Badges, Dirty State, Diagnostics)"]
    end

    REG --> Resolution
    DB --> Resolution
    Resolution --> AppServices["Application Services (AI, Auth, Storage, Invitations)"]
    CTRL --> DB
    UI <--> CTRL
```

### 1. The Core Immutability Invariant
The application **never** modifies `.env` at runtime. `.env` remains reserved exclusively for bootstrap configuration (`ENV_ONLY_BOOTSTRAP`), such as `APP_KEY`, PostgreSQL credentials, Redis host, container ports, session cookie security flags, and logging sinks.

### 2. Code-First Setting Definitions (`SettingDefinitionRegistry`)
Settings are declared centrally in version-controlled PHP classes (`App\Settings\SettingDefinitionRegistry`). Each setting defines:
- `key` (hierarchical string: e.g. `general.app_name`, `auth.invitation_ttl_days`, `ai.providers.openai.api_key`);
- `group` and optional `subgroup`;
- `label` and `description`;
- `type` (`string`, `integer`, `boolean`, `enum`, `secret`, `url`);
- `default` value;
- `validationRules` (Laravel validation array);
- `isSecret`, `adminEditable`, `runtimeMutable`, `requiresRestart`, `dangerous`, `testable`;
- `envFallbackKey` (optional config key mapping for deployment-level initial defaults);
- `envLocked` (flag indicating that deployment environment overrides runtime edits).

### 3. Precedence Hierarchy
For any requested setting, `SettingsResolver` evaluates:
1. **Hard Environment Lock**: If `env_locked === true` or explicitly pinned via environment policy, the environment value is authoritative and immutable in UI.
2. **Database Runtime Override**: Decrypted override stored in the `system_settings` table.
3. **Environment/Config Fallback**: Value derived from `config(...)` if declared via `env_fallback_key`.
4. **Application Default**: Canonical code default declared in `SettingDefinition`.

Admin users may reset a database override at any time (`POST /api/v1/admin/settings/reset`), immediately falling back to (3) or (4).

### 4. Database Schema and Optimistic Concurrency
Settings overrides are persisted in `system_settings`:
- `id` (ULID primary key);
- `key` (unique string index);
- `value` (JSON column for non-secret typed values);
- `encrypted_value` (text column using Laravel `Crypt::encryptString` for secrets);
- `version` (integer incremented on each mutation for optimistic locking to detect concurrency collisions);
- `updated_by` (foreign key to `users.id` tracking administrative identity).

### 5. Secret Protection Invariant
Secrets (such as API keys and SMTP passwords):
- Are stored encrypted at rest via AES-256-CBC (`Crypt`);
- Are **never** returned in plaintext over the API (`is_configured: bool` and `masked: true` only);
- Are excluded from audit event metadata (recorded as `redacted: true` with zero key leakage);
- Are excluded from application and Nginx logs;
- Can be replaced by typing a new value or cleared via explicit `clear_secret: true`.

### 6. Separation of System vs. User Settings
- **System Settings** (`/admin/settings`): Global administrative controls affecting all users and background workers.
- **User Settings** (`/settings`): Personal user-scoped preferences and BYOK credentials (e.g. `UserAiSetting`).

### 7. Read-Only Environment Diagnostics
To support operations without exposing dangerous runtime editability or secrets, `AdminSettingsController::environment` supplies a read-only diagnostics payload (`APP_ENV`, PHP/Laravel versions, database status and runtime role, Redis connectivity, storage driver, and encryption status without ever leaking `APP_KEY`).

## Consequences

- **Zero downtime configuration**: Administrators can adjust timeouts, model selections, invitation policies, and feature thresholds instantly without restarts.
- **Audited operations**: Every update, reset, and connectivity ping produces a structured, sanitized `AuditEvent` with actor attribution.
- **Fail-safe fallback**: In case of a database reset, cache eviction, or empty table, the application immediately falls back to known-safe code defaults or environment values.
- **Memory & Cache efficiency**: Settings are resolved with cache layering in Redis / in-memory repositories with automated invalidation on mutations.
- **Optimistic concurrency**: Multiple administrators cannot silently overwrite each other's changes; concurrent updates return HTTP 409 `SETTING_CONCURRENCY_CONFLICT`.

## Alternatives Rejected

1. **Option A (Web-based `.env` Editor)**: Rejected due to container immutability violations, process concurrency hazards, and severe privilege escalation risks.
2. **Option B (Unstructured Dynamic Database Key-Value Store)**: Rejected because untyped, code-less database settings invite schema drift, unvalidated inputs, missing fallbacks, and zero compile-time or static analysis safety.
