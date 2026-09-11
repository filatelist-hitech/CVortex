# Release Management Policy

## Purpose

Define when CVortex may create Git tags and GitHub Releases, how versions are named, and what evidence is required before publishing a release.

## Current phase rule

Do not create a Git tag or GitHub Release for repository bootstrap, research-only phases, documentation-only phases, or intermediate `stage` state.

A tag marks a reproducible product/runtime milestone, not the completion of an internal checklist.

## Versioning

Use Semantic Versioning once releases begin:

`MAJOR.MINOR.PATCH`

Examples:

- `v0.1.0-alpha.1` — early technical preview before a stable MVP;
- `v0.1.0` — first coherent pre-1.0 product milestone;
- `v0.1.1` — backward-compatible bug/security fixes for that milestone;
- `v0.2.0` — new backward-compatible capability set;
- `v1.0.0` — first contract-stable production release.

While the product is below `1.0.0`, incompatible changes are allowed only when documented and clearly called out in release notes.

## First eligible release

The first tag/release becomes eligible only after M0 / repository bootstrap reaches a runnable system milestone where the documented local stack can actually be started and validated.

Before that point, no version tag is required.

If M0 is published as a technical preview, prefer:

`v0.1.0-alpha.1`

Do not create that tag merely because Phase 00-07 documentation exists.

## Release source

All release tags must point to a commit on `main`.

Normal flow:

1. short-lived branches merge into `stage`;
2. validated `stage` is promoted to `main` through a release pull request;
3. required validation passes on the exact `main` commit;
4. create the version tag from that `main` commit;
5. create the GitHub Release from the same tag;
6. use generated release notes as a starting point and review them before publishing.

Never tag an arbitrary feature branch or unmerged `stage` commit as a release.

## Tag rules

- Use the `v` prefix: `v0.1.0`, not `0.1.0`.
- Never move or reuse a published version tag.
- Never delete and recreate a published tag to hide mistakes.
- If a published release is bad, publish a correcting version.
- Release tags must be unique and monotonically advance according to SemVer.
- Do not create date-only tags for normal releases.

## Release notes

`.github/release.yml` defines the canonical generated-release-note grouping.

Pull requests should carry meaningful type labels so release notes remain useful:

- `breaking-change`;
- `type:security`;
- `type:feature`;
- `type:bug`;
- `type:docs`;
- `type:research`;
- `type:refactor`;
- `type:test`;
- `type:chore`.

Use `skip-changelog` only for changes that truly should not appear in release notes.

Generated notes must be reviewed for:

- breaking changes;
- migrations or upgrade steps;
- security implications;
- known limitations;
- rollback considerations;
- documentation links;
- accidentally exposed secrets or private data.

## Release gate

A release is allowed only when applicable checks are complete:

- the target commit is on `main`;
- required CI/status checks pass once CI exists;
- migrations/backward compatibility are understood;
- authorization/security impact is reviewed;
- no known P0 blocker remains;
- documentation reflects released behavior;
- accepted ADRs match the released architecture;
- no secrets, API keys, private career data, recruiter messages, or production data are present;
- release notes accurately describe the change set.

## Hotfix releases

For an urgent production/release fix:

1. branch `hotfix/*` from `main`;
2. validate the narrow fix;
3. merge through PR into `main`;
4. immediately propagate the same fix back to `stage`;
5. publish the next PATCH version when a release is warranted.

Do not overwrite the previous release tag.

## Prohibited behavior

Do not:

- tag every phase, commit, or merged PR;
- publish empty/decorative releases;
- create a release directly from `stage`;
- reuse a tag name for different content;
- publish before validation just to obtain downloadable artifacts;
- treat a GitHub Release as proof that the software was actually validated.

## Completion checks

Release-management changes are complete only when:

- this policy, `.github/release.yml`, and label taxonomy are consistent;
- version/tag naming remains unambiguous;
- the release source is `main`;
- release notes can be traced back to reviewed PRs;
- no release is created merely to mark internal progress.
