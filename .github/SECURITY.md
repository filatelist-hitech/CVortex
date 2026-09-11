# Security Policy

## Current status

CVortex is in pre-product bootstrap. There is no supported production release yet.

Do not interpret the absence of a production release as permission to publish secrets, credentials, private career data, recruiter messages, exploit details, or other sensitive information in public issues.

## Reporting a security issue

Prefer GitHub private vulnerability reporting through the repository **Security** tab when it is available and enabled.

If private reporting is not available, contact the repository owner through a private channel before disclosing exploitable details publicly.

Do not open a public issue containing:

- API keys, tokens, credentials, or secrets;
- private resume/career information;
- recruiter or employer correspondence;
- production or real-user data;
- working exploit payloads against a deployed CVortex instance;
- sensitive infrastructure details that materially increase exploitability.

A sanitized public tracking issue may be created later after the sensitive details are removed.

## Security-sensitive areas

CVortex must treat the following as security-sensitive by design:

- authentication and invite-only registration;
- authorization and cross-user isolation;
- LLM/provider credentials;
- uploaded resumes and documents;
- vacancy/recruiter/web content as untrusted input;
- prompt injection boundaries;
- SSRF in external content fetching;
- XSS and HTML injection;
- file traversal and malicious uploads;
- PII and career-history confidentiality;
- logs, traces, generated artifacts, and backups;
- employer-facing generated content and provenance.

## Supported versions

There are currently no released/supported versions.

Once releases begin, this section must be updated with the supported version range and security-fix policy.

## Disclosure expectations

Security fixes must follow the repository Git workflow and release policy unless an active incident requires a narrowly scoped emergency hotfix.

Never weaken branch protection, authorization, validation, or secret-handling requirements merely to ship a security fix faster.
