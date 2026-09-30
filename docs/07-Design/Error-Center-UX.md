---
title: Error Center UX specification
status: draft
owner: project
created: 2026-09-28
updated: 2026-09-29
tags: [design, diagnostics, accessibility]
related: ["[[Design-Foundation]]", "[[Figma-Handoff]]", "[[../03-ADR/ADR-0022-local-diagnostics|ADR-0022]]"]
---

# Error Center UX

The admin task is to identify the failed operation, reason, impact, retry decision and next action without opening a trace. The design follows `Problem → Cause → Impact → Action → Correlation → Technical details`. Figma [CVortex Design System](https://www.figma.com/design/2D7jymN0KxoUMUodh6v8Hd) contains a draft `04 Error Center · draft` page with desktop and mobile incident detail patterns. The file previously had no screen, component or variable content. This proposal is not a human-reviewed Figma baseline; Git tokens remain authoritative for machine values.

## Data audit

| UX field | Existing source | Available | Backend addition |
| --- | --- | --- | --- |
| Cause | Stable `error_code`, safe `message`, `ErrorCatalog` taxonomy | Yes, deterministic mapping for known codes | No |
| Retryable | Incident `retryable` from `ErrorCatalog` | Yes | No |
| Recommended action | Incident `recovery_action` | Yes | No |
| Affected operation | Latest occurrence `operation`; list uses `latest_operation` projection | Yes when recorded | Small list projection; do not guess when absent |
| Provider and attempt | Latest occurrence `provider`, `attempt`; list uses `latest_provider` | Yes when recorded | Small list projection for provider |
| Impact | Incident `impact` | Yes, category-level statement | No; never infer data integrity |
| Data changed | No committed-write evidence in diagnostics | No | No; say that diagnostics cannot confirm changes |
| Failure category | Stable `error_code` | Coarse but sufficient | No |
| Duration and provider HTTP status | Not in incident/occurrence response | No | No; omit from UI |
| Retry-After | Validated provider exception delay | Yes when known | Bounded 0–86400 integer on the occurrence; show unknown otherwise |
| Correlation | Occurrence request/job/LLM/application IDs | Yes, at most 20 recent events | No |
| Frequency | Incident count and first/last seen | Yes | No |
| Global metrics | Paginated list only | No reliable global aggregates | No; show page context, not invented totals |
| Related incidents | Exact correlation search, no relation endpoint | Searchable manually | No graph or inferred relationship |

## Design direction

- **Color:** `surface.canvas #070B18`, `surface.default #0B1224`, `surface.elevated #111C35`, `text.primary #F7F9FC`, `accent.primary #35D6E8`, and semantic warning/danger values from `brand/tokens/cvortex.tokens.json`. These are existing tokens, not new palette choices.
- **Type:** Space Grotesk throughout. Page title 32/600, incident title 24/600, body 16/400, compact metadata 14/400, code 12–14/400. No exception class in primary copy.
- **Layout:** Left-aligned, capped reading width. List has prominent search, one compact filter line, active chips, a restrained summary strip, then scannable rows. Detail uses a single highlighted decision panel for cause/impact/action/retryability; status and frequency sit by the title. Correlation and occurrences use rows, technical details a closed disclosure.
- **Responsive:** One column below 768 px; at 390 px filters open in a scrollable disclosure with separate Quick and Advanced sections and a sticky Apply/Clear footer. Metadata stacks, IDs truncate visually while copy uses full value. Occurrence rows retain compact day, month and local time; the full year, seconds and timezone remain in the accessible label. Desktop gives the decision panel a strong visual axis; mobile keeps the same reading order.
- **Accessibility:** 44 px controls, text plus color for severity/status, native buttons and disclosures, focus ring, labelled search/filter/copy controls, live result and mutation feedback, reduced motion, local time-zone label on exact timestamps. Detail loading names the selected incident; the loaded heading receives focus only when focus has not moved elsewhere. A failed detail load moves keyboard focus to its alert when focus has not moved elsewhere. Keyboard return from detail restores focus to the originating incident row after the list loads, or to the list heading when that row is absent; mouse navigation does not force focus changes.

```text
Desktop:  Search ─────────────── Reload
          Quick filters · More filters
          [Open] [Critical] [Visible results]
          Incident rows: title / cause / status / frequency

Detail:  Back · status actions
          Title / code / frequency
          ┌ Cause → Impact → Action → Retryable ┐
          Context · Correlation
          Recent occurrences (closed rows)
          Technical details (closed)

Mobile:   Search
          Filters disclosure · chips
          List cards / one-column detail
          Technical details at the end
```

The one strong visual moment is the decision panel. Repeated equal-weight cards would bury the action. The design deliberately uses quiet dividers for context and occurrence rows. The Figma draft is an implementation proposal; visual comparison and human review are still needed before it becomes approved visual authority.

## Visual evidence

The implementation was checked at 1440 px desktop, 820 px tablet and 390 px mobile widths with synthetic incidents. The decision panel remains in the initial viewport at each size; technical details stay collapsed and no page-level horizontal overflow was observed. These screenshots are synthetic fixtures, not production diagnostics:

- [Desktop list](evidence/error-center-desktop-list.png)
- [Desktop detail](evidence/error-center-desktop-detail.png)
- [Mobile detail](evidence/error-center-mobile-detail.png)

The Figma draft was created before implementation. Figma's Starter-plan call limit prevented a final screenshot and alignment pass, so the saved design specification and browser screenshots record the finished presentation pending Figma review.

Figma visual verification pending due tool/plan limitation.

Remediation browser evidence uses synthetic data and the production frontend build. List, filter disclosure and detail were exercised at every requested width; the run also covered exact-action retry, status success/failure, keyboard detail focus, search-plus-filter empty state and no horizontal overflow:

| Width | List | Filters | Detail |
| --- | --- | --- | --- |
| 1440 px | [List](evidence/error-center-remediation-1440-list.png) | [Filters](evidence/error-center-remediation-1440-filters.png) | [Detail](evidence/error-center-remediation-1440-detail.png) |
| 1280 px | [List](evidence/error-center-remediation-1280-list.png) | [Filters](evidence/error-center-remediation-1280-filters.png) | [Detail](evidence/error-center-remediation-1280-detail.png) |
| 820 px | [List](evidence/error-center-remediation-820-list.png) | [Filters](evidence/error-center-remediation-820-filters.png) | [Detail](evidence/error-center-remediation-820-detail.png) |
| 390 px | [List](evidence/error-center-remediation-390-list.png) | [Filters](evidence/error-center-remediation-390-filters.png) | [Detail](evidence/error-center-remediation-390-detail.png) |

## Safe content rules

Titles and causes come only from a closed code-to-label map and safe API strings. Unknown codes use a generic failure title and explicitly unknown cause; never interpret exception class or stack as a diagnosis. Category-level impact remains category-level. The UI never asserts that data was unchanged. Full IDs are copied, but only shortened forms appear by default. Diagnostic summary includes only code, incident ID, latest available request ID, known operation and last-seen time. No prompt, resume, provider response, secrets or absolute path enters the view or clipboard.

The provider filter compares the latest retained occurrence's provider exactly, matching the list label. Search and filters remain combined; an empty combined result names the filter interaction and offers a search-preserving reset. Retry state stores the failed list query/page, detail ID or status target explicitly. A status retry repeats only its idempotent PATCH, and status controls lock during that request. Browser event kinds have human labels, while cause copy states that the root cause was not captured.

Synchronous application-draft operations preserve only a numeric `Retry-After` from 0 to 86400 seconds on retryable API errors. A positive delay is included in the safe recovery message and disables generation, edit, accept and approve until the delay expires; Reject remains available because it does not call the provider. The browser stores only the bounded expiry timestamp so remounting or reloading the draft flow does not bypass the wait. An absent or invalid header does not invent a delay.
