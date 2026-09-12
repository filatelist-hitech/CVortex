---
title: Phase 06 Security Threat Model
status: accepted
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [security, threat-model, phase-06]
related: ["[[../02-Architecture/Phase-06-System-Design|System Design]]", "[[../04-Data/Phase-06-Data-Design|Data Design]]", "[[../03-ADR/ADR-0017-untrusted-external-content|ADR-0017]]"]
---

# Phase 06 Security Threat Model

Assets are private career/application data, files, credentials, provider budget, trusted instructions, availability and audit/provenance. External vacancies, recruiter messages, uploads, HTML/web/API responses and tool output are **untrusted data, never instruction**.

| Threat | Surface / impact | Preventive and detective controls | Residual risk / validation |
|---|---|---|---|
| Prompt injection | external text coerces workflow/tool/context; false facts or exfiltration | separate trusted prompt/data, policy-gated tools, minimal context, raw snapshots, schema/Truth Guard, safe run audit | semantic evasion remains; adversarial vacancy/message/upload fixtures |
| SSRF | future URL fetcher reaches private/metadata services | allowlisted protocols/sources, DNS/IP and private/link-local validation, redirect revalidation, time/size limits, isolated egress and request logs | DNS races; test redirects, encodings and blocked ranges |
| XSS | HTML/rich text/generated content executes in browser | treat as text, sanitise at rendering boundary, escape by default, CSP; log sanitiser decisions | sanitizer defects; malicious markup regression fixtures |
| Traversal/files | uploaded/generated name reaches filesystem or parser | generated opaque names, normalised storage refs outside webroot, ownership checks, signature/type/size limits, isolated converter | parser zero-days; traversal and malformed-file tests |
| IDOR/cross-user leak | IDs, nested routes, queues, cache, files or AI context disclose PII | server-derived actor/owner scope everywhere, non-enumerating denial, scoped cache/jobs/context, explicit/audited admin action | missed new query path; matrix tests for read/write/list/file/job/context |
| Malicious upload | spoofed MIME, macro, archive bomb, malformed PDF/DOCX | inspect bytes, reject/limit archives/macros/size, resource/time limits, sandbox parser, quarantine status | parser vulnerabilities; bomb/spoof/corrupt fixtures |
| Secret leakage | logs, prompts, exports, audit or documents disclose keys | encrypted credential storage, masked display/no post-store frontend return, redaction, least privilege, rotation/deletion audit | operator error; log/prompt/export redaction tests |
| PII leakage | excessive LLM context, provider transfer, backups/exports | minimised context, ownership scope, retention/deletion/export policy, encrypted access-controlled backups, provider disclosure review | provider/backup compromise; context and restore-access tests |
| Credential abuse | system/BYOK key theft or cross-user use | encrypted-at-rest owner-scoped resolution, no client round trip after storage, rotate/delete, usage anomalies and safe audit | valid-key abuse; tenancy/rotation/revocation tests |

Security validation is required before each future ingestion, fetcher, parser, credential or context implementation; human approval does not mitigate a failed technical control.
