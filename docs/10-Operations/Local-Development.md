---
title: Local Development
status: active
owner: project
created: 2026-09-12
updated: 2026-10-03
tags: [operations, local, docker, m0]
related:
  - "[[../02-Architecture/M0-Runtime|M0 Runtime]]"
  - "[[M0-Runbook]]"
  - "[[Logging-and-Diagnostics|Logging and Diagnostics]]"
---

# Local Development

## Prerequisites

Install Git, Docker with Compose support, and Make. Host PHP, Composer, Node.js, npm, PostgreSQL and Redis are not required. The optional `make logs-pretty` command also needs host Python 3.

## First launch

From the repository root:

```sh
make init
docker compose --env-file .env config --quiet
make up
make migrate
```

`make init` must run before the first `make up`: it creates the ignored root `.env` when absent, generates local secrets, builds the images and installs locked dependencies in named volumes. It also repairs a missing or unsafe runtime database password. Re-running it is safe, but it preserves existing `.env` values, including `POSTGRES_DB`; it does not rename or recreate a database in an existing PostgreSQL volume.

The `docker compose ... config --quiet` check catches missing required variables before containers start. `make migrate` is the repository migration entry point: it first provisions the restricted `cvortex_app` role and then runs Laravel migrations through the privileged `migration` service.

After the stack starts, verify the result:

```sh
docker compose ps
health_authority="$(docker compose port nginx 80)"
curl -fsS "http://${health_authority}/api/v1/health/ready"
```

The readiness endpoint must return a successful response. It probes the runtime PostgreSQL and Redis dependencies; `/api/v1/health/live` only validates the HTTP/application path. The command asks Compose for Nginx's effective published address, so it honors the configured `CVORTEX_PORT` with Compose's dotenv parsing without executing or printing `.env`. Open the configured `APP_URL` in a browser only after this check passes.

If port 8080 is already occupied, change both values in `.env` so application URLs remain coherent:

```dotenv
CVORTEX_PORT=18080
APP_URL=http://localhost:18080
```

Then run `make up` and open the configured port.

## Recover a database-name mismatch

If `make migrate` fails with an error such as:

```text
FATAL: database "cvortex1" does not exist
```

the running PostgreSQL volume was initialized with a different `POSTGRES_DB` than the current `.env`. The failure happens in `10-runtime-role.sh`, before Laravel migration code runs. This is a configuration mismatch, not a reason to delete the database volume.

Stop the stack without removing volumes and inspect the databases that actually exist:

```sh
make down
make up
docker compose exec -T postgres sh -lc \
  'psql -U "$POSTGRES_USER" -d postgres -Atc "SELECT datname FROM pg_database ORDER BY datname;"'
```

If the expected database is present under another name, update only `POSTGRES_DB` in the ignored root `.env` to that existing name. Keep `POSTGRES_USER`, `POSTGRES_PASSWORD`, `POSTGRES_RUNTIME_USER` and `POSTGRES_RUNTIME_PASSWORD` consistent with the initialized volume, then recreate the services and retry:

```sh
make down
docker compose --env-file .env config --quiet
make up
make migrate
```

If the required database is not listed, stop before changing credentials or storage. Identify which Compose project and named volume contain the intended data; do not run `docker compose down --volumes` in the normal recovery path. That command destroys the persistent PostgreSQL and private-storage volumes.

## Existing checkout checklist

Use this sequence when the checkout already has an ignored `.env` or an existing PostgreSQL volume:

1. Run `make init` and review the non-secret values in `.env`, especially `CVORTEX_PORT`, `APP_URL` and `POSTGRES_DB`.
2. Run `docker compose --env-file .env config --quiet`.
3. Start with `make up` and confirm `docker compose ps` reports healthy services.
4. Run `make migrate` before opening the application.
5. Check the loopback health endpoint and then use the browser.

Never copy a `.env` from another checkout blindly: its database name and credentials may belong to a different named volume.

## Stable commands

| Command | Effect |
|---|---|
| `make init` | Create local config once, build and install locked dependencies |
| `make up` | Start the stack in the background |
| `make down` | Stop/remove containers and networks; preserve named volumes |
| `make restart` | Recreate the stack in dependency-safe order; preserve named volumes |
| `make test` | Refresh Laravel package discovery, verify MCP route toggling, and run backend and frontend tests |
| `make lint` | Run Pint, PHPStan/Larastan, ESLint and TypeScript checks |
| `make logs SERVICE=backend` | Show the selected service's latest 200 log lines |
| `make logs-pretty SERVICE=backend` | Format the selected service's latest 200 JSON log lines (requires host Python 3) |
| `make failed-jobs` | List sanitized final queue failures from diagnostics |
| `make diagnostics-prune` | Apply configured diagnostic retention immediately; deletes expired records |
| `make shell SERVICE=backend` | Open a shell in a running service |
| `make shell SERVICE=backend COMMAND='php -v'` | Run one command in a running service |
| `make migrate` | Apply Laravel migrations to the running local database |

Do not use `docker compose down --volumes` in the normal workflow: it destroys the persistent M0 database and private-storage volumes.

For user-facing errors, admin investigation, correlation IDs and log fallback, see the [Error Center user guide](../00-Home/Error-Center-User-Guide.md) and [Logging and Diagnostics](Logging-and-Diagnostics.md).

## Local runtime contract

### Optional local MCP Gateway

MCP is disabled by default. The only required toggle is `MCP_ENABLED=false` (default) or `MCP_ENABLED=true` for local protocol testing. These optional, non-secret overrides are read only when the advertised URL differs from `APP_URL`:

| Variable | Default | Purpose |
|---|---|---|
| `MCP_ENABLED` | `false` | Enables MCP and its OAuth/discovery routes |
| `MCP_RESOURCE_URL` | `<APP_URL origin>/mcp/v1` | Canonical protected-resource URI expected on OAuth requests and tokens |
| `MCP_AUTHORIZATION_SERVER_URL` | `<APP_URL origin>` | Exact OAuth issuer and endpoint origin |

For Secure MCP Tunnel, set `MCP_RESOURCE_URL` to the canonical resource URL ChatGPT receives from the tunnel. The 401 `resource_metadata` challenge follows that URL's authority and path; it does not trust Host/forwarded headers. Set `MCP_AUTHORIZATION_SERVER_URL` independently to a reachable issuer because the tunnel does not automatically tunnel OAuth authorization/token endpoints.

For a local Inspector run, keep the default local URLs. Enable MCP in the ignored root `.env`, generate Passport signing keys once as the PHP-FPM user with `docker compose exec -T -u www-data backend php artisan passport:keys`, inspect migrations with `docker compose exec -T backend php artisan migrate:status`, and apply only pending migrations through the repository workflow. Recreate the backend and Horizon so the flag is loaded. Keep keys in ignored private backend storage; never print or commit tokens. Nginx remains loopback-bound. If Laravel routes are cached, clear the route cache after changing the flag. Obtain a user-scoped OAuth token through the authorization flow and use MCP Inspector's Streamable HTTP transport. Browser cookies and shared static bearer tokens do not authenticate MCP.

Neither gateway initialization, OAuth, discovery nor either read tool requires `OPENAI_API_KEY` or an available `LlmProvider`. Keep outbound AI configuration independent. In production set `APP_DEBUG=false`; safe MCP client responses do not prevent framework logs from containing local exception detail. Secure MCP Tunnel settings belong to the separate tunnel-client process/profile, not CVortex `.env`: `CONTROL_PLANE_TUNNEL_ID` identifies the selected tunnel; `CONTROL_PLANE_API_KEY` is the runtime secret used by `doctor`/`run` and must come from the user's secret manager/environment. `OPENAI_ADMIN_KEY` is a separate administrative secret for tunnel CRUD and must not be given to the long-lived daemon. Never commit or log any tunnel secret. Runtime use requires Tunnels Read + Use; create/edit access requires Read + Manage.

Local Inspector validation does not establish ChatGPT connectivity. OpenAI Secure MCP Tunnel can carry MCP traffic and OAuth discovery, but it does not provision or automatically tunnel the authorization server; its issuer, authorization and token endpoints must remain reachable for OAuth. Before a remote connection, verify actual account/workspace entitlement, the exact canonical MCP resource and issuer, token checks, tunnel/workspace association and tunnel permissions. Do not open inbound router/firewall ports or expose the local server publicly as a shortcut. See [MCP Gateway architecture](../02-Architecture/MCP-Gateway.md), [validation evidence](MCP-Gateway-Validation.md) and [current OpenAI/MCP research](../../research/technical/11-MCP-GATEWAY-FOUNDATION.md).

Frontend uses `next dev`; backend uses PHP-FPM. Source changes arrive through bind mounts while `vendor`, `node_modules` and `.next` stay container-managed. The browser uses only the same-origin `/api/v1` boundary and must not receive Docker service names or backend secrets.
