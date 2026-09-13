# Blockers

`m1-2-career-core` remains **CHANGES_REQUIRED** after the 2026-09-13 independent adversarial audit.

Blocking findings:

- cross-owner CareerSource provenance can produce a `PASS` Claim;
- semantic-upgrade validation/evals are insufficient;
- `USER_RESOLUTION_REQUIRED` has no executable semantics;
- confirmed-only matching query path is absent;
- live applied PostgreSQL schema is incompatible with the current Career models/services;
- private Career text is present in ordinary exception logs;
- confirmed-fact supersession and required coverage gaps remain.

Do not start `m1-3-vacancy-core` until remediation is implemented and independently reviewed as `PASS`.
