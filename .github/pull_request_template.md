## Summary

Describe what changed, why it is needed, and the observable user/system outcome.

## Roadmap / release metadata

- GitHub Milestone: `M0 · Runnable Core` / `M1 · First Value` / ... / `roadmap:unversioned`
- Slice label: `slice:m1.x-*` / `roadmap:cross-cutting` / not applicable
- Type label(s): one or more canonical `type:*`
- Area label(s): one or more canonical `area:*`
- Priority: exactly one canonical `priority:*`
- Status: exactly one canonical `status:*`
- Assignee: at least one
- Target version: `v0.x.0` / prerelease / none
- Release impact: none / prerelease / patch / minor / breaking

Assign the native GitHub Milestone before merge when the change belongs to M0–M6. Cross-cutting work with no product-version target must use `roadmap:unversioned` instead of inventing a milestone.

Use only labels from `.github/labels.yml`. Apply the final `status:*` label after the required checkpoint comments so the final metadata event represents the complete PR state.

## Related work

- Issue/task:
- ADR/research:
- Depends on:

## Change type

- [ ] Feature
- [ ] Fix
- [ ] Refactor
- [ ] Test / evaluation
- [ ] Documentation
- [ ] Research
- [ ] Security
- [ ] Build / CI / repository maintenance

## Scope

### Included

- 

### Explicitly not included

- 

## Truth / provenance

- [ ] No candidate/employer claim is introduced without traceability to confirmed facts, or this change does not generate candidate claims
- [ ] AI-derived data remains distinguishable from confirmed facts
- [ ] External vacancy/recruiter/web/document content is treated as untrusted input

## Security / authorization

- [ ] Authorization and cross-user isolation were considered
- [ ] No secrets, credentials, API keys, private career data, recruiter messages, or production data are included
- [ ] Prompt injection / XSS / SSRF / IDOR / file handling risks were considered where relevant

## Validation

- [ ] Relevant automated tests pass or are not applicable
- [ ] New/changed behavior has appropriate tests/evals
- [ ] Error handling and observability were considered
- [ ] Migrations/backward compatibility were considered
- [ ] Documentation was updated where needed
- [ ] ADR/architecture diagrams were updated if an accepted design changed
- [ ] Applicable local/manual validation was performed
- [ ] Roadmap metadata matches `.github/roadmap.yml`
- [ ] `PR metadata / review checkpoint` comment is present
- [ ] `Governance / validation checkpoint` comment is present
- [ ] `scripts/check-pr-contract.sh` passes when available

## Release notes

Choose exactly one path:

- [ ] This change should appear in release notes
- [ ] `skip-changelog` is appropriate

- [ ] Breaking changes are labeled `breaking-change` and documented

Release tags are created only from validated `main` commits. Completing a slice or merging a PR does not automatically create a version.

## Notes / risks

List known risks, follow-up work, rollout/rollback considerations, or explicitly accepted limitations.
