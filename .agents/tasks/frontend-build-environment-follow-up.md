---
title: Frontend production-build environment follow-up
status: backlog
owner: project
created: 2026-09-19
updated: 2026-09-19
tags: [task, frontend, environment]
---

# Frontend production-build environment follow-up

Investigate the pre-existing Compose development-container build failure caused by running `next build` with `NODE_ENV=development`. Production-mode build is the supported validation path and passes; do not mix this environment correction into M1.3R unless new evidence ties it to Vacancy Core.
