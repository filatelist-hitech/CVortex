# CVortex runtime AI assets

This directory is the canonical repository location for versioned **product runtime** AI Skills, prompt definitions and their validation fixtures. It is intentionally separate from `.agents/`, which is reserved for development-agent instructions.

M1.2 introduces the bounded `career.fact-extraction` Skill. M1.3 adds `vacancy.requirement-extraction`, which extracts only source-supported vacancy requirements and never performs matching or recommendation. M1.4 adds `application-draft-generation` for structured recommendations and two cover variants, plus `application-truth-review` for semantic support review; deterministic code still owns truth, provenance, authorization and approval. Each Skill's manifest, prompt, output schema and synthetic adversarial fixtures are versioned together under its skill version directory. Truth review v3 batches independent draft-item reviews and requires an exact, gap-free segmentation of each candidate item before PASS can be stored. Deterministic validation permits factual spans only when they exactly match a current Claim statement; other spans may contain only whitespace or punctuation, so model PASS cannot strengthen a Claim. Provider adapters load these assets through application-owned contracts; the assets do not select a concrete provider or model.

## ChatGPT plan / controlled draft extension — 2026-10-04

[ADR-0023](../docs/03-ADR/ADR-0023-chatgpt-plan-chat.md) defines a distinct OAuth plan provider and one controlled MCP vacancy-analysis draft save. See [ChatGPT Plan Chat](../docs/10-Operations/ChatGPT-Plan-Chat.md) for implemented context budgets, credentials, errors and user flow, and [MCP architecture](../docs/02-Architecture/MCP-Gateway.md) for the adapter contract. API-key billing remains independent; MCP is not inference.

The versioned vacancy-plan-chat/v1 skill owns trusted instructions, the quick-action user prompt and the output schema. Chat business logic uses StreamingProvider, a dynamic user-selected model and the existing VacancyLlmRun provenance layer.
