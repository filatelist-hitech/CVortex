# Browser validation evidence

2026-10-07. Final result: **PASS — 127 checks**, Playwright Chromium **145.0.7632.6**. Machine-readable result: [browser-results.json](browser-results.json). All four final desktop and mobile screenshots were visually inspected.

Additional final checks passed: gallery images and four HTTP concept links, gallery desktop/mobile reflow, gallery JavaScript error check ([gallery-results.json](gallery-results.json), [screenshot](gallery-preview.png)); `node --check` for all eight prototype/server/validation JavaScript files; local HTML/Markdown link and asset audit; absence of placeholder copy; `git diff --check`.

## Reproduce

Start the isolated loopback preview from the repository root:

```bash
node research/design-redesign/preview-server.cjs
```

In a second terminal, run the bundled runtime used in this session:

```bash
CVORTEX_PLAYWRIGHT_MODULE='/Users/filatelist/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright' \
CVORTEX_CHROMIUM_PATH='/Users/filatelist/Library/Caches/ms-playwright/chromium-1208/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing' \
CVORTEX_PREVIEW_URL='http://127.0.0.1:8767' \
node research/design-redesign/validation/validate.cjs
```

Paths above are session-specific. On another machine, use an available Playwright module and Chromium executable via those environment variables; omitting `CVORTEX_PREVIEW_URL` uses local `file://` previews. No dependency was installed for this task.

## Coverage

- All four concepts: local font loaded, rendered text contrast, keyboard search, visible focus, Escape/modal focus return, loading/empty/error recovery, disabled approval in unavailable states, reduced motion, zero browser JavaScript errors and zero external requests.
- Reflow at 1440, 820, 720, 390 and 320 CSS pixels without horizontal page overflow; mobile controls at least 44 pixels.
- A: review filters, selected opportunity/evidence, unanalyzed-record boundary and exact interview date.
- B: selection persists across sections; unsupported edits remain blocked; accept, content approval and submission are distinct; rejection cannot approve content.
- C: capabilities change evidence and linked opportunities; pending facts require explicit confirmation; confirmation alone does not create a Claim or approved text.
- D: incomplete preparation and rejected wording keep the gate closed; approval alone does not record an application; explicit manual confirmation is required; employer conflict blocks continuation.

Minimum audited text contrast: A/B/D **5.92:1**, C **6.80:1**. This is a rendered-text audit, not a comprehensive accessibility certification. Screenshots use a 1440×1000 desktop viewport and a 390×844 mobile viewport; full-page PNG heights vary. Matching `*-viewport.png` files preserve the viewport dimensions.

## Findings fixed during validation

The first browser pass found horizontal overflow in A at 320 pixels; wrapping the filter controls fixed it. A later pass found a mobile tab in B narrower than 44 pixels; the minimum control width was corrected. The final full run passed after these fixes and the final interaction corrections. `initial-browser.json` is intermediate evidence, not the final verdict.

The Python Playwright helper could not reach its local preview server; the available bundled Node Playwright runtime and the research-only Node server supplied the successful final browser validation. No production service was started or tested.

## Limits

Synthetic fixtures and in-memory state only. No live AI, real application submission, database, external product account, production browser session, assistive-technology session or real-user usability test. A 720-pixel reflow check is not a measured browser 200% zoom test. B permits two exact fixture wordings; it does not implement semantic Truth Guard validation. Production suites were not run because production code is unchanged.

Recommendation remains an expert hypothesis awaiting user selection. **STOP before final design decisions or implementation.**
