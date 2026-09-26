# Blockers

- Preview 0.1 has no recorded real-user end-to-end validation or observed feedback in the repository. The mandatory M1.4 acceptance criteria remain unverified, so Preview 0.1 is not PASS and the M2 planning task is not ready to start until that evidence is recorded.
- The known Compose frontend build with `NODE_ENV=development` fails while prerendering `/_global-error`; `NODE_ENV=production` passes. It is tracked by `frontend-build-environment-follow-up` as a separate environment issue and does not block production-mode Preview validation.
- ChatGPT MCP E2E is NOT_VALIDATED. This local deployment has no externally reachable HTTPS/tunnel endpoint; the complete OAuth authorization-code flow and Passport `resource` audience propagation/verification remain untested. Current account/workspace entitlement and Secure MCP Tunnel billing/credit requirements are UNKNOWN. These are connection gates, not blockers for local MCP operation or the existing Responses API path.
