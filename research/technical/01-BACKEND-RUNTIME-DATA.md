---
status: research-complete
date: 2026-09-12
phase: 03-technical-research
owner: CVortex
architecture_decision: none
---

# Backend Runtime and Data Stack

## Question

What current PHP, Laravel and PostgreSQL versions are technically appropriate candidates for a new CVortex installation in September 2026, while respecting the owner's fixed direction of PHP 8.4+, Laravel and PostgreSQL?

## Evidence

### PHP

- **[E1]** PHP 8.4 remains supported: active support until 2026-12-31 and security support until 2028-12-31.
- **[E1]** PHP 8.5 is supported longer: active support until 2027-12-31 and security support until 2029-12-31.
- PHP 8.6 is not a production candidate while it remains pre-release.

### Laravel

- **[E2]** Laravel 13 was released on 2026-03-17.
- **[E2]** Laravel 13 supports PHP 8.3 through PHP 8.5.
- **[E2]** Laravel 13 receives security fixes through 2028-03-17.
- **[E2]** Laravel 12 entered security-fixes-only status on 2026-08-13, making Laravel 13 the natural greenfield candidate unless a dependency blocks it.

### PostgreSQL

- **[E3]** PostgreSQL 18 is the newest stable supported major.
- **[E3]** Current minor at research date is 18.6; PostgreSQL explicitly recommends always running the current minor of a selected major.
- **[E3]** PostgreSQL 18 support ends 2030-11-14.
- **[E4]** PostgreSQL 19 Beta 3 exists but is not a production candidate.

## Findings

1. The owner's `PHP 8.4+` constraint does not require staying on 8.4. PHP 8.5 gives a longer active/security runway and is inside Laravel 13's supported range.
2. Laravel 13 is the strongest greenfield candidate. Choosing Laravel 12 for a new project would begin from a maintenance/security-only branch without an identified compatibility benefit.
3. PostgreSQL 18.x is the strongest greenfield candidate, with the exact minor kept current through routine patch upgrades.
4. Version pins belong in implementation/dependency policy later, not in permanent domain architecture.

## Candidate recommendation, not ADR

- PHP: **8.5 current patch** unless bootstrap validation finds an extension/library blocker.
- Laravel: **13.x current patch**.
- PostgreSQL: **18.x current minor**.

## Risks / validation needed

- Confirm all selected PHP extensions and document libraries support PHP 8.5.
- Run the future Composer dependency graph on both macOS arm64 dev and Linux container targets.
- PostgreSQL major upgrades remain explicit operational changes even if Docker makes them look deceptively casual.

## Confidence

High.

## Citations

- **[E1] PHP Supported Versions**, PHP.net, accessed 2026-09-12: https://www.php.net/supported-versions.php
- **[E2] Laravel Release Notes / Support Policy**, Laravel, accessed 2026-09-12: https://laravel.com/framework/docs/releases
- **[E3] PostgreSQL Versioning Policy**, PostgreSQL Global Development Group, accessed 2026-09-12: https://www.postgresql.org/support/versioning/
- **[E4] PostgreSQL Support**, release status, accessed 2026-09-12: https://www.postgresql.org/support/
