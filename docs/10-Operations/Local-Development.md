---
title: Local Development
status: active
owner: project
created: 2026-09-12
updated: 2026-09-12
tags: [operations, local, docker, m0]
related:
  - "[[../02-Architecture/M0-Runtime|M0 Runtime]]"
  - "[[M0-Runbook]]"
---

# Local Development

## Prerequisites

Install only Git, Docker with Compose support, and Make. Host PHP, Composer, Node.js, npm, PostgreSQL and Redis are not required.

## Clean bootstrap

From the repository root:

```sh
make init
make up
```

Open `http://localhost:8080`. `make init` creates ignored root `.env` only when absent, generates local secrets, builds the images and installs locked dependencies in named volumes. Re-running it is safe and preserves an existing `.env`.

If port 8080 is already occupied, change both values in `.env` so application URLs remain coherent:

```dotenv
CVORTEX_PORT=18080
APP_URL=http://localhost:18080
```

Then run `make up` and open the configured port.

## Stable commands

| Command | Effect |
|---|---|
| `make init` | Create local config once, build and install locked dependencies |
| `make up` | Start the stack in the background |
| `make down` | Stop/remove containers and networks; preserve named volumes |
| `make restart` | Recreate the stack in dependency-safe order; preserve named volumes |
| `make test` | Run backend and frontend tests |
| `make lint` | Run Pint, PHPStan/Larastan, ESLint and TypeScript checks |
| `make logs SERVICE=backend` | Show the selected service's latest 200 log lines |
| `make shell SERVICE=backend` | Open a shell in a running service |
| `make shell SERVICE=backend COMMAND='php -v'` | Run one command in a running service |
| `make migrate` | Apply Laravel migrations to the running local database |

Do not use `docker compose down --volumes` in the normal workflow: it destroys the persistent M0 database and private-storage volumes.

## Local runtime contract

Frontend uses `next dev`; backend uses PHP-FPM. Source changes arrive through bind mounts while `vendor`, `node_modules` and `.next` stay container-managed. The browser uses only the same-origin `/api/v1` boundary and must not receive Docker service names or backend secrets.
