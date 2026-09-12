---
status: research-complete
date: 2026-09-12
phase: 03-technical-research
owner: CVortex
architecture_decision: none
---

# OpenAI API, Model Catalog and Cost Controls

## Question

What is the current OpenAI API model/cost landscape and which API features materially affect CVortex architecture?

## Current model catalog evidence

OpenAI's current guidance positions GPT-6 Astra as the most capable model, with GPT-5.6 Sol, Terra and Luna covering progressively cheaper capability tiers.

| Model | Intended position | Input / 1M | Cached input / 1M | Cache write | Output / 1M | Context | Max output |
|---|---|---:|---:|---:|---:|---:|---:|
| GPT-6 Astra | hardest end-to-end work | $10.00 | $1.00 | $12.50 / 1M | $50.00 | 1.05M | 128K |
| GPT-5.6 Sol | complex professional work | $4.00 | $0.40 | 1.25x uncached input = $5.00 / 1M | $20.00 | 1.05M | 128K |
| GPT-5.6 Terra | balance intelligence/cost | $2.00 | $0.20 | 1.25x uncached input = $2.50 / 1M | $12.00 | 1.05M | 128K |
| GPT-5.6 Luna | cost-sensitive high volume | $0.20 | $0.02 | 1.25x uncached input = $0.25 / 1M | $1.20 | 1.05M | 128K |

Source date: 2026-09-12. Pricing is deliberately treated as volatile configuration, not architectural truth.

Additional price behavior:

- **[E1-E4]** Requests above 272K input tokens have higher rates for the full request on these model families.
- **[E1-E4]** Batch/Flex can be cheaper than Standard; Astra documents Batch and Flex at 50% of Standard.
- **[E2]** GPT-5.6 Sol pricing is promotional at least through 2026-11-21, so any cost model that assumes these numbers forever is already wrong while being written.

## Structured Outputs

- **[E5]** OpenAI Structured Outputs supports strict JSON Schema output (`json_schema`, `strict: true`).
- For CVortex this should be used for semantic extraction/classification outputs and then followed by local schema/business-invariant validation.
- Structured output is not a Truth Guard by itself. A schema can guarantee a field exists while the model confidently puts nonsense in it.

## Prompt caching

- **[E6]** GPT-5.6+ supports automatic/explicit prompt caching with `prompt_cache_options`; current supported TTL is 30 minutes.
- **[E6]** On GPT-5.6+ `prompt_cache_key` is optional for cache optimization and can instead be used to separate customer/user accounting.
- **[E6]** Minimum cacheable visible prefix is documented as 1,024 tokens for GPT-5.6+.
- Design consequence: stable developer/system instructions, tool definitions and schemas belong before dynamic vacancy/user context. Avoid timestamps/random IDs at the front of prompts.

## Batch processing

- **[E7]** Batch provides 50% lower costs than synchronous APIs, a separate higher-rate pool and completion within 24 hours.
- **[E7]** A batch may contain up to 50,000 requests and a 200 MB input file.
- Appropriate CVortex uses: Recruitment KB refresh, historical re-analysis, bulk extraction/classification and evaluation runs.
- Inappropriate uses: interactive vacancy analysis, resume edits, cover generation or anything the user is waiting for now.

## Model-routing implication

Do not encode `Luna -> Terra -> Sol -> Astra` directly in domain services. Define logical capability classes such as:

- `extract_low_cost`
- `semantic_standard`
- `reasoning_advanced`
- `research_max`

Then map those classes to provider/model/snapshot/reasoning/cost ceilings in versioned configuration. The mapping must be validated with CVortex eval datasets before promotion.

## Cost accounting implication

Every LLM run should eventually capture at minimum:

- provider/model/snapshot or alias;
- logical model policy;
- input, cached-read, cache-write and output tokens where exposed;
- reasoning effort;
- latency;
- retries;
- estimated/actual price basis and pricing-version/effective date;
- application/user/skill/prompt version correlation.

This is not schema design yet; it is a Phase 03 requirement finding.

## Decision status

OpenAI as first provider is owner-approved. Concrete model mappings remain explicitly unaccepted until evals and Phase 05.

## Confidence

High for API features/pricing as of the research date; medium over time because model catalog and pricing are intentionally fast-changing.

## Citations

- **[E1] GPT-6 Astra model page**, OpenAI, accessed 2026-09-12: https://developers.openai.com/api/docs/models/gpt-6-astra
- **[E2] GPT-5.6 Sol**, OpenAI, accessed 2026-09-12: https://developers.openai.com/api/docs/models/gpt-5.6-sol
- **[E3] GPT-5.6 Terra**, OpenAI, accessed 2026-09-12: https://developers.openai.com/api/docs/models/gpt-5.6-terra
- **[E4] GPT-5.6 Luna**, OpenAI, accessed 2026-09-12: https://developers.openai.com/api/docs/models/gpt-5.6-luna
- **[E5] Structured model outputs**, OpenAI, accessed 2026-09-12: https://developers.openai.com/api/docs/guides/structured-outputs
- **[E6] Prompt caching**, OpenAI, accessed 2026-09-12: https://developers.openai.com/api/docs/guides/prompt-caching
- **[E7] Batch API**, OpenAI, accessed 2026-09-12: https://developers.openai.com/api/docs/guides/batch
- **[E8] Model catalog**, OpenAI, accessed 2026-09-12: https://developers.openai.com/api/docs/models
