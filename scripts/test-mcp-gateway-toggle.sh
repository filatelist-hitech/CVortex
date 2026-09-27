#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

docker compose run --rm --no-deps backend php artisan package:discover --quiet --no-ansi

for enabled in false true; do
  route_json="$(docker compose run --rm --no-deps -e MCP_ENABLED="$enabled" backend php artisan route:list --json)"
  printf '%s' "$route_json" | php -r '
    $enabled = $argv[1] === "true";
    $routes = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $uris = array_column($routes, "uri");
    $surface = array_values(array_filter($uris, static fn (string $uri): bool =>
        str_contains($uri, "mcp") || str_starts_with($uri, "oauth/") || str_starts_with($uri, ".well-known/oauth-")
    ));

    if (! $enabled && $surface !== []) {
        fwrite(STDERR, "MCP_ENABLED=false exposed MCP/OAuth routes: ".implode(", ", $surface).PHP_EOL);
        exit(1);
    }

    if ($enabled) {
        foreach ([
            ".well-known/oauth-protected-resource",
            ".well-known/oauth-protected-resource/mcp",
            ".well-known/oauth-protected-resource/mcp/v1",
            ".well-known/oauth-authorization-server",
            "mcp/v1",
            "oauth/authorize",
            "oauth/token",
            "oauth/register",
        ] as $expected) {
            if (! in_array($expected, $uris, true)) {
                fwrite(STDERR, "MCP_ENABLED=true is missing route: ".$expected.PHP_EOL);
                exit(1);
            }
        }
    }

    printf("MCP_ENABLED=%s: %d MCP/OAuth routes verified.%s", $enabled ? "true" : "false", count($surface), PHP_EOL);
  ' "$enabled"
done
