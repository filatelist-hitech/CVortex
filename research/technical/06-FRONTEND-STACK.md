---
status: research-complete
date: 2026-09-12
phase: 03-technical-research
owner: CVortex
architecture_decision: none
---

# Frontend Stack Research

## Question

What are the current stable/LTS candidates for the owner-approved Next.js + React + TypeScript + Tailwind frontend direction?

## Next.js

- **[E1]** Next.js 16.x is Active LTS; 15.x is Maintenance LTS.
- **[E2]** On 2026-08-25 Vercel published a security release and instructed users to upgrade to 16.3.3 or 15.5.24 for two Critical vulnerabilities.
- A new CVortex project should not start below those patched versions.
- Canary builds are explicitly not production recommendations.

## React

- **[E3]** React 19.3 was released 2026-09-09 and is current.
- It includes stable View Transitions and Fragment Refs and adds Trusted Types support in React DOM.
- React security history around Server Components reinforces the need to follow patch releases rather than pin a major and forget it exists.

## TypeScript

- **[E4]** TypeScript 6.0 is current and is a transition release toward TypeScript 7's native compiler.
- It introduces breaking/default changes and deprecations, including configuration behavior changes.
- For a greenfield project, use strict typing and explicitly define `tsconfig` rather than relying on defaults that changed between releases.

## Tailwind

- **[E5]** Tailwind CSS 4.3 is current.
- Tailwind 4 has modern browser requirements; CVortex's PWA browser support policy should explicitly match them before bootstrap.
- Tailwind's CSS-first theme facilities are useful outputs/consumers, but should not automatically become the cross-tool canonical design-token source.

## Candidate recommendation, not ADR

- Next.js: **16.3.3 or later patched stable 16.x**, verified again at bootstrap time.
- React: **19.3 current patch** compatible with the selected Next.js release.
- TypeScript: **6.0 current patch**, `strict: true`, explicit project settings and no casual `any`.
- Tailwind: **4.3 current patch**.
- Prefer App Router for a greenfield Next.js application unless Phase 06 identifies a concrete blocker. This is a candidate implementation direction, not an accepted architecture decision in this phase.

## Compatibility caveat

The versions above were researched independently. The bootstrap phase must resolve an actual dependency graph and run build/test smoke checks. Do not copy four newest version numbers into `package.json` and call that architecture.

## Security notes

- Treat framework patch upgrades as operational security work.
- For Next.js image optimization and server-side features, track advisories and update quickly.
- CSP/Trusted Types support should be considered during the security design rather than retrofitted after UI implementation.

## Confidence

High on current versions; medium-high on exact combined version pins until install/build validation.

## Citations

- **[E1] Next.js Support Policy**, accessed 2026-09-12: https://nextjs.org/support-policy
- **[E2] Next.js Blog, August 2026 Security Release**, accessed 2026-09-12: https://nextjs.org/blog
- **[E3] React 19.3**, React Team, 2026-09-09: https://react.dev/blog/2026/09/09/react-19-3
- **[E4] TypeScript 6.0 release notes**, updated 2026-09-08: https://www.typescriptlang.org/docs/handbook/release-notes/typescript-6-0.html
- **[E5] Tailwind CSS v4.3**, 2026-05-08: https://tailwindcss.com/blog/tailwindcss-v4-3
