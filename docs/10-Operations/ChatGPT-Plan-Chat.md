---
title: Local ChatGPT Plan Vacancy Chat
status: active
owner: project
created: 2026-10-04
updated: 2026-10-04
tags: [operations, chatgpt, oauth, vacancies]
related: [../03-ADR/ADR-0023-chatgpt-plan-chat.md, ../02-Architecture/MCP-Gateway.md]
---

# Local ChatGPT Plan Vacancy Chat

Three independent paths exist: OpenAI API-key inference uses API billing; outbound Sign in with ChatGPT uses the connected account's eligible plan; inbound MCP exposes owned CVortex data to external ChatGPT. MCP never supplies or pays for inference. There is no automatic API-key fallback.

## Setup and user flow

Set `CHATGPT_PLAN_ENABLED=true` in ignored local `.env`. Keep `CHATGPT_CALLBACK_URI=http://127.0.0.1:8080/api/v1/chatgpt/callback`; run `make migrate`, recreate backend after environment changes, and reload Nginx after `nginx -t`. The callback uses 127.0.0.1, not localhost. The browser may display CVortex at localhost; the pending owner-bound OAuth state links the callback to that user without depending on cross-host session cookies.

Open a saved vacancy → Chat → ChatGPT plan connection → Continue with ChatGPT. Complete consent in the browser, return and Refresh connection. The connection must show Connected and Using ChatGPT plan. A dismissible first-use notice distinguishes plan usage from API billing. Manage usage links to ChatGPT settings. Refresh loads the account's current model catalog; select a model. Verify connection sends only a greeting, without vacancy or career data.

The server checks each submitted slug against the authenticated user's owned connection and its current account catalog before saving it as the thread preference. The active `user_selected_chatgpt_plan` ModelPolicy must also permit account-catalog selection for the plan provider. The plan adapter fetches the connection's catalog again immediately before Responses inference, so a stale saved preference fails with `MODEL_NOT_AVAILABLE`; a policy mismatch returns `MODEL_NOT_ALLOWED`. Catalogs are fetched live and are not shared or persisted. Neither error triggers a different model or API-key provider.

Analyze vacancy uses a versioned server-side skill. Ordinary messages can discuss the analysis. CVortex stores messages, selected provider/model, source/evidence references and run status. Reload chat retrieves stored history; opening the connection panel restores an available previous selection. Streaming partial output is saved as INTERRUPTED on failure, never as completed analysis. Reload recovers stale streaming leases after 150 seconds. A connection or model error requires user action; CVortex does not silently switch billing paths or indefinitely retry.

Save analysis accepts only completed structured output with exact supported source excerpts and current CONFIRMED evidence. It creates DRAFT / AI_GENERATED and separate normalized draft requirement rows. A single confirmed fact can support multiple proposed requirement matches. If validation rejects paraphrased labels, ask the assistant to use verbatim source phrases and a single-clause excerpt; do not weaken source validation. Draft notes and proposed matches remain advisory. Approve analysis is a separate first-party action: it checks current source/evidence, recomputes existing deterministic matching and promotes requirements to the normal vacancy workflow. Conflicting existing canonical requirements cannot be replaced by this MVP.

The raw vacancy source and Career Facts are never modified by chat/save/approval. New career evidence must use the existing human review workflow. Only explicitly human-confirmed entries or approved proposals become CONFIRMED.

## Context and limits

The deterministic ContextBuilder supplies the current snapshot (up to 25,000 characters), up to 20 relevant confirmed facts (8,000-character aggregate), up to 20 normalized requirements, bounded current approved analysis, up to eight approved prior employer statements and at most 12 completed historical messages within a 12,000-character budget. Imported and generated material is explicitly untrusted data. Employer Memory and Career Track aggregates do not yet exist; absent contexts are explicit. No ChatGPT conversations, memory or files are accessed.

Chat endpoints are limited to ten requests per minute per user and endpoint. Provider limits remain authoritative. Supported inference request fields are model, instructions, bounded input, store=false, stream=true. Success requires response.completed with completed status. No hosted MCP hop, previous_response_id, conversation storage or unsupported token-limit field is used. The text-stream and JSON contracts are preview behavior, verified against official documentation on 2026-10-04; recheck them when changing the provider.

## Credentials and recovery

The private persistent host identity is a UUIDv4 URN, stored separately from credentials under storage/app/private. Back it up when preserving a local installation. Connections contain owner-bound issued client ID, verified subject, granted scopes, encrypted access/refresh tokens, expiry and optional earliest refresh time. Verified ID tokens are discarded; optional id_token_hint is not used. Preserve APP_KEY and the database securely; losing the encryption key prevents credential recovery. Tokens are never returned to the browser or diagnostics.

OAuth attempts use encrypted expiring cache entries, state, nonce and S256 PKCE. Issued client IDs are retained before exchange, including permission refusal/exchange failure, for reconnect. Refresh is serialized under a PostgreSQL row lock; replacement tokens commit together. Invalid/revoked grants clear credentials; transient failures preserve them. Disconnect attempts remote revocation and always removes local credentials. If remote revocation cannot be verified, the UI directs the user to remove access in ChatGPT settings.

## Evidence boundary

Mocked provider tests establish request contracts, model-policy/catalog enforcement, connection isolation and failure behavior; PostgreSQL harness establishes RLS, composite owner relations, token rotation serialization, draft idempotency and concurrent approval. Live browser verification is recorded separately in the task evidence. Neither a model appearing in the catalog nor a successful greeting proves vacancy/draft acceptance. Product Figma parity and an external client's refreshed tool cache must be verified separately.
