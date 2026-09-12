---
title: ADR-0015 — File Storage Abstraction with Local Initial Backend
status: accepted
decision_nature: DERIVED_ARCHITECTURAL_DECISION
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [architecture, files, storage, security]
related: [ADR-0002, ADR-0005, ADR-0007, ADR-0016, ADR-0017]
---

# ADR-0015 — File Storage Abstraction with Local Initial Backend

## Context

CVortex stores uploaded source documents and generated DOCX/PDF artifacts. The initial local deployment needs filesystem storage, while future hosted deployment may require S3-compatible object storage. Domain logic must not depend on absolute paths.

## Decision Drivers

- local-first simplicity and later portability;
- strict ownership and non-public handling of PII;
- safe filenames/path boundaries;
- transactional metadata and traceability.

## Options

1. Storage interface with local-filesystem adapter initially and metadata in PostgreSQL.
2. Use absolute/local paths directly in domain records.
3. Deploy S3/MinIO immediately.
4. Store all binaries inside PostgreSQL.

## Comparison

| Option | Local fit | Cloud migration | Security control | Complexity |
|---|---|---|---|---|
| Abstracted local | High | High | High | Moderate |
| Direct paths | High | Low | Low | Low initially |
| S3/MinIO now | Medium | High | High | High now |
| DB binaries | Medium | Medium | High | Operationally heavy |

## Decision

Domain/application logic addresses files through a storage abstraction and stable file identity, never a provider-specific path. The initial adapter stores binaries on a controlled local filesystem. PostgreSQL stores ownership, lifecycle, provenance, media/validation metadata and opaque storage reference; binary bytes remain in storage.

Original user filenames are metadata only and never determine trusted paths. Storage defaults to private access. A later S3-compatible adapter may replace the filesystem without rewriting domain logic. This ADR does not introduce S3, MinIO or a detailed file schema.

## Consequences

### Positive

- portable storage backend and controlled path handling;
- metadata participates in relational ownership/provenance;
- local implementation stays small.

### Negative / Risks

- metadata/binary consistency needs cleanup/reconciliation rules;
- backups must cover database and files coherently;
- abstraction needs capability discipline to avoid lowest-common-denominator design.

## Security and Validation Impact

Use generated names, canonical paths, outside-webroot storage, authorization on every access, size/type/signature checks and parser isolation. Prevent traversal, cross-user access and guessing. Logs and URLs must not expose local paths or secrets.

## Reversibility and Revisit Triggers

Add an object-storage adapter when hosted availability, scale or operations require it. Migration preserves stable file IDs and verifies hashes/metadata.

## References

- [PROJECT.md](../../PROJECT.md).
- [External Content Threats](../../research/security/external-content-threats.md).
- [Document Pipeline](../../research/technical/09-DOCUMENT-PIPELINE.md).

## Supersedes

None.

## Superseded By

None.
