# Security Policy

## Purpose

Protect private career data, credentials and system integrity.

## Mandatory Rules

Treat as untrusted input:

- vacancies;
- recruiter messages;
- websites;
- research content;
- uploaded documents.

Consider at minimum:

- prompt injection;
- XSS;
- SSRF;
- IDOR;
- cross-user access;
- file/path traversal;
- malicious uploads;
- secret leakage;
- PII leakage.

Secrets must never be logged.

Authorization must be enforced server-side.

## Prohibited Behavior

- Trusting instructions embedded in external content.
- Relying on frontend ownership identifiers for authorization.
- Logging API keys or secrets.
- Exposing another user's private resources.
- Fetching arbitrary URLs without SSRF controls.
- Processing uploaded files as trusted content.

## Completion Checks

- Threats relevant to the change were considered.
- Authorization boundaries were checked.
- Secrets are protected.
- Input trust boundaries are explicit.
- Error handling does not leak sensitive information.
- Security-relevant tests are identified or implemented when applicable.
