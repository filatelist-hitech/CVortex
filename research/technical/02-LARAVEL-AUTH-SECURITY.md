---
status: research-complete
date: 2026-09-12
phase: 03-technical-research
owner: CVortex
architecture_decision: none
---

# Laravel Authentication, Authorization and Security Controls

## Question

Which Laravel-native mechanisms best fit CVortex's invite-only, multi-user, first-party Next.js frontend and future external clients?

## Authentication evidence

- **[E1]** Laravel Sanctum explicitly recommends first-party SPA authentication via Laravel's session cookie and `web` guard rather than issuing API tokens to the first-party SPA.
- **[E1]** The SPA flow uses `/sanctum/csrf-cookie`, a session cookie and CSRF validation.
- **[E1]** Sanctum also supports API tokens, which can be reserved for later external clients or integrations.
- **[E2]** Fortify is a frontend-agnostic authentication backend. It can provide login/password-reset/email-verification style endpoints while a separate frontend supplies UI.
- **[E1][E2]** Fortify and Sanctum are explicitly designed to work together for SPA authentication.

## Authorization evidence

- **[E3]** Laravel distinguishes Gates and Policies. Gates fit global/non-resource abilities; Policies organize authorization around models/resources.
- **[E3]** Laravel guidance recommends Policies for robust resource-oriented applications.
- CVortex's cross-user isolation requirement means every user-owned resource must be authorized server-side. A frontend-provided `user_id` is never sufficient.

## Encryption evidence

- **[E4]** Laravel's encrypter uses OpenSSL AES ciphers and authenticates encrypted values with a MAC.
- **[E4][E5]** Laravel supports previous encryption keys for rotation through its encrypter configuration/API.
- This is appropriate for initial at-rest encryption of BYOK API credentials, but compromise of the application encryption key compromises values encrypted under it.

## Rate limiting evidence

Laravel rate limiting is built on the cache layer and can use Redis. For CVortex, rate limiting has two different jobs:

1. inbound abuse/control limits for login, invitations, imports and expensive API endpoints;
2. outbound provider quota shaping for LLM calls, where provider limits and per-user budgets are separate concerns.

Queue-level throttling must not be mistaken for HTTP rate limiting. They protect different boundaries.

## Findings

1. **First-party browser auth candidate:** Sanctum stateful cookie/session auth + CSRF.
2. **Auth endpoint implementation candidate:** Fortify headless endpoints or deliberately small custom endpoints using Laravel auth services. Invite-only registration will require custom invitation validation either way.
3. **Future machine/mobile/external clients:** Sanctum personal access tokens only where token auth is actually needed. Do not give the first-party SPA a bearer token just because APIs look more futuristic that way.
4. **Authorization:** Policies for user-owned resources; Gates for global admin capabilities. Ownership checks belong in backend policies/services and automated negative tests.
5. **BYOK encryption candidate:** Laravel Crypt/encrypter for MVP, with encrypted-at-rest columns, secret redaction, no API-key echo to frontend, key rotation procedure and strict log filtering.
6. **Rate limiting candidate:** Redis-backed limiter with separate dimensions for IP, authenticated user, endpoint class and expensive-operation budget.

## Threat notes

- Session auth does not remove XSS risk. CSP, output escaping and dependency hygiene remain required.
- Cookie auth requires correct SameSite/domain/secure-cookie configuration behind Nginx.
- Invite tokens must be random, expiry-bound, usage-limited, hashed at rest when practical, and checked transactionally to prevent reuse races.
- Authorization tests must include IDOR/cross-user attempts for every private aggregate.
- Encryption is not authorization. An encrypted secret can still leak through logs, error payloads or an over-permissive endpoint.

## Decision status

This Phase 03 research was candidate material at the time it was written. The M1.1 decision is now accepted in [ADR-0019](../../docs/03-ADR/ADR-0019-sanctum-stateful-first-party-auth.md); this research remains the evidence record and does not govern implementation.

## Confidence

High for Sanctum/Policies/Crypt. Medium-high for Fortify versus custom auth endpoints because invite-only UX requirements should be designed in Phase 06 before freezing that choice.

## Citations

- **[E1] Laravel Sanctum**, accessed 2026-09-12: https://laravel.com/framework/docs/sanctum
- **[E2] Laravel Fortify**, official package documentation family; Sanctum current docs reference Fortify for `/login`: https://laravel.com/framework/docs/fortify
- **[E3] Laravel Authorization**, accessed 2026-09-12: https://laravel.com/framework/docs/authorization
- **[E4] Laravel Encryption**, accessed 2026-09-12: https://laravel.com/framework/docs/encryption
- **[E5] Laravel 13 Encrypter API**, accessed 2026-09-12: https://api.laravel.com/docs/13.x/Illuminate/Encryption/Encrypter.html
- **[E6] Laravel Rate Limiting**, accessed 2026-09-12: https://laravel.com/framework/docs/rate-limiting
