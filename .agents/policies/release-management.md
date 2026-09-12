# Release Management Policy

## Purpose

Define how CVortex milestones map to product versions, when tags/releases are allowed, and what evidence is required before publishing.

Canonical milestone/version mapping lives in `.github/roadmap.yml`. Product scope and milestone exit criteria live in `docs/01-Product/Roadmap.md`.

## Milestone versus version

A GitHub Milestone is a delivery container. A version is a reproducible product/runtime release.

They are related but not identical:

- finishing a task or slice does not create a version;
- closing a GitHub Milestone does not automatically create a tag;
- a release may be skipped when the milestone output is only an internal checkpoint;
- tags are created only from validated `main` commits.

## Current mapping

| Milestone | Product checkpoint | Target version | Release rule |
| --- | --- | --- | --- |
| M0 Runnable Core | technical runtime baseline | `v0.1.0-alpha.1` | optional prerelease after actual runtime validation |
| M1 First Value | Preview 0.1 | `v0.1.0` | first required coherent product preview |
| M2 Real Application Package | MVP 0.2 | `v0.2.0` | practical application-package MVP |
| M3 Imports & Integrations | integration expansion | `v0.3.0` | normal minor release when accepted |
| M4 Employer Journey | employer-context workflow | `v0.4.0` | normal minor release when accepted |
| M5 Outcomes & Analytics | outcome-learning workflow | `v0.5.0` | normal minor release when accepted |
| M6 Distribution & Hardening | distribution/operations baseline | `v0.6.0` | normal minor release when accepted |

`v1.0.0` is a separate production-stability decision after M6. Completing M6 does not silently declare API/product contracts stable.

## Semantic Versioning

Use SemVer with `v` prefix:

- `v0.N.0-alpha.K` for early prereleases;
- `v0.N.0-beta.K` when feature scope is largely present but validation remains;
- `v0.N.0-rc.K` for release candidates;
- `v0.N.0` for accepted milestone releases;
- `v0.N.PATCH` for backward-compatible bug/security fixes;
- `v1.0.0` only after an explicit stability decision.

Do not tag every slice. M1.1–M1.4 are delivery slices inside the `v0.1.0` target, not four artificial product releases.

## Release source

Normal flow:

1. bounded branches merge into `stage`;
2. milestone exit criteria and relevant checks pass;
3. `stage → main` release PR carries `release:promotion`;
4. validation passes on the exact `main` commit;
5. create the version tag from that `main` commit;
6. create GitHub Release from the same tag;
7. review generated release notes before publication.

Hotfix flow:

1. `hotfix/*` from `main`;
2. validate narrow fix;
3. PR to `main` with `release:hotfix`;
4. publish next PATCH version when warranted;
5. propagate fix back to `stage`.

Never tag `stage`, a feature branch or an arbitrary commit.

## Release eligibility

Release is allowed only when applicable checks are complete:

- target commit is on `main`;
- milestone exit criteria are actually met;
- required CI/status checks pass;
- no unresolved P0 blocker exists;
- migrations/backward compatibility are understood;
- security/authorization impact is reviewed;
- documentation matches behavior;
- accepted ADRs match released architecture;
- no secret/private candidate data is present;
- release notes accurately describe the change set.

M0 may remain unreleased even after completion. If published, its preferred first tag is `v0.1.0-alpha.1`.

M1 `v0.1.0` is not eligible until all four First Value slices form the end-to-end Preview 0.1 workflow.

## Tags

- Never move or reuse a published tag.
- Never delete/recreate a published tag to hide a mistake.
- Correct a bad release with a new version.
- Use monotonically advancing SemVer.
- Do not use date-only release tags.

## Release notes

`.github/release.yml` is the canonical generated-note grouping.

Release promotion PRs are excluded from generated notes because they are containers for already reviewed changes, not product changes themselves.

Generated notes must be reviewed for breaking changes, migrations, security implications, known limitations, rollback notes and accidental private data.

## Prohibited behavior

Do not:

- create decorative versions for internal task completion;
- equate a GitHub Milestone with a release artifact;
- create a tag merely because a PR merged;
- publish before validation;
- claim `v1.0.0` because the roadmap reached M6;
- use version labels as a substitute for the canonical roadmap mapping.
