# Research Workflow

## Goal

Produce decision-grade evidence with the smallest sufficient research/context footprint.

Follow `.agents/policies/research.md` and `.agents/policies/resource-usage.md`.

## READ MINIMUM CONTEXT

Understand only the decision/question that the research must support.

Read the current task spec, directly relevant existing research, and accepted ADRs that constrain the question. Do not recursively load all research/docs.

## DEFINE QUESTION

State concisely:

- research question;
- why it matters;
- decision affected;
- freshness requirements;
- stopping condition for sufficient evidence.

## COLLECT SOURCES

Work sequentially by default.

Prefer:

1. official/primary sources;
2. standards/vendor documentation;
3. strong secondary engineering/research sources;
4. community sources only as supplementary evidence.

Do not collect multiple redundant sources merely to increase source count. Broaden only when evidence conflicts, confidence remains low, the claim is high-risk, or the task explicitly requires breadth.

## EXTRACT

For each material finding record only what is useful for a decision:

- source and URL;
- publication/update/access date when relevant;
- finding;
- evidence;
- confidence;
- freshness/review date when relevant.

## COMPARE

Record conflicting evidence and material alternatives. Do not manufacture a comparison when one authoritative source settles a narrow factual question.

## INFER

Clearly separate sourced facts from inference and recommendation.

## DECISION IMPACT

Explain what project decision the finding affects. Do not make an accepted architecture decision inside a research-only phase unless explicitly authorized.

## REVIEW DATE

Add `review_after` when information is likely to become stale, especially APIs, pricing, models, vendor capabilities, terms and restrictions.

## COMPLETE

Save the smallest coherent research artifact, update indexes/state only where needed, and report concisely.

Do not rerun completed research without a concrete reason.
