---
title: Runtime Configuration and Admin System Settings Reference
status: accepted
owner: operations
created: 2026-10-24
updated: 2026-10-24
tags: [operations, configuration, settings, admin, security]
---

# Runtime Configuration & Admin System Settings

This document describes the operational architecture, classification rules, and administrative workflows for configuring CVortex.

## Architecture Baseline

CVortex follows a strict **Option C** configuration model:

```text
.env / Docker deployment
        ↓
Bootstrap / Infrastructure configuration (ENV_ONLY_BOOTSTRAP)

SettingDefinitionRegistry (Code)
        ↓
Canonical types, defaults, validation, and fallback definitions

system_settings (PostgreSQL)
        ↓
Encrypted overrides with optimistic concurrency

SettingsResolver
        ↓
Precedence resolution → Application Services

Admin Control Plane (/admin/settings)
        ↓
Browser UI for authorized administrators
```

### Invariant: `.env` Immutability
**The application never writes to `.env` at runtime.**
Any requirement to change bootstrap credentials, network bindings, database ports, or encryption keys (`APP_KEY`) must occur via deployment files, CI/CD, or container orchestration.

---

## Configuration Categories

Every application parameter belongs strictly to one classification:

| Category | Description | Storage | UI Editable? |
|---|---|---|---|
| `ENV_ONLY_BOOTSTRAP` | Root-of-trust secrets, container networking, database and Redis credentials, `APP_KEY`, cookie security flags. | `.env` / container env | ❌ Read-only diagnostic |
| `SYSTEM_RUNTIME_SETTING` | Global operational parameters: invitation TTL, rate limits, character extraction limits, AI models, token cost metrics. | Code + PostgreSQL `system_settings` | ✅ Admin UI |
| `SYSTEM_RUNTIME_SECRET` | System-managed third-party credentials (OpenAI API key, SMTP credentials). Encrypted at rest via `Crypt`. | PostgreSQL `system_settings` (encrypted) | ✅ Masked edit / clear only |
| `USER_SETTING` | User-scoped personal preferences and BYOK keys. | `user_ai_settings` / user profile | ❌ (Managed in `/settings`) |
| `OPERATIONAL_READ_ONLY` | Platform health: PHP/Laravel version, database connectivity, queue driver. | Dynamic / config | ℹ️ Diagnostic panel |

---

## Precedence Policy

When `SettingsResolver::get($key)` is queried:

1. **Hard Environment Lock**: If the definition specifies `env_locked === true` or environment policy overrides the setting, the environment value is returned.
2. **Database Override**: If an override exists in `system_settings`, its typed/decrypted value is returned.
3. **Environment/Config Fallback**: If an `env_fallback_key` is configured and present in `config(...)`, that value is returned.
4. **Application Default**: Canonical value defined in `SettingDefinition`.

### Resetting Overrides
An administrator can revert any database override via `POST /api/v1/admin/settings/reset`. The system immediately falls back to (3) or (4).

---

## Security and Secrets Handling

1. **Encryption at Rest**: All secrets are stored in the `encrypted_value` column of `system_settings` using Laravel's `Crypt::encryptString` (AES-256-CBC with `APP_KEY`).
2. **No Plaintext Leaks**:
   - `GET /api/v1/admin/settings` returns only `is_configured: true|false` and `value: null` for secrets.
   - Admin UI displays `•••••••••••• (введите новый для замены)`.
   - Audit logs record `redacted: true` without the secret string.
   - Standard stdout/stderr logs never include secrets.
3. **Optimistic Concurrency**:
   Each setting row has a `version` integer. When saving via `PATCH /api/v1/admin/settings`, the UI supplies `expected_version`. If another administrator modified the setting in the interim, the API rejects the request with HTTP 409 `SETTING_CONCURRENCY_CONFLICT`.

---

## AI Model Policies and Provider Resolution Notes

Operational parameters under `ai.model_policies.*` (such as `ai.model_policies.low_cost.model` and `ai.model_policies.standard.model`) map dynamically into `ModelPolicyResolver` for default extraction and generation pipelines:
- **OpenAI Pipelines**: Fully consume database overrides for model identifiers, `input_cost_micros`, and `output_cost_micros` at runtime.
- **Gemini Pipelines**: Model selection for Gemini currently evaluates static deployment config and individual user-level BYOK settings (`user_ai_settings`). Multi-model dynamic registry settings for Gemini are slated for a future iteration.

---

## Operational Verification

### Automated Connectivity Tests
Administrators can run real-time integration checks directly from `/admin/settings`:
- **OpenAI API**: Validates key validity against `/v1/models` and measures latency.
- **SMTP**: Connects to the configured mail host, performs HELO handshake, and measures latency.
- **Storage**: Performs test write/read/delete cycles on the default disk.

Every test execution is recorded in the `audit_events` log.
