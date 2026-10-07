<?php

namespace App\Mcp\OAuth;

use InvalidArgumentException;

final class McpResource
{
    public const DRAFT_WRITE_SCOPE = 'mcp:draft:write';

    public function resourceUri(): string
    {
        $configured = $this->configured('mcp.resource');

        return $configured === null
            ? $this->origin().'/mcp/v1'
            : $this->normalizeUrl($configured, 'MCP_RESOURCE_URL');
    }

    public function authorizationServerUri(): string
    {
        $configured = $this->configured('mcp.authorization_server');

        return $configured === null
            ? $this->origin()
            : $this->normalizeUrl($configured, 'MCP_AUTHORIZATION_SERVER_URL');
    }

    public function protectedResourceMetadataUri(): string
    {
        $resource = $this->urlParts($this->resourceUri(), 'MCP_RESOURCE_URL');
        $path = rtrim((string) ($resource['path'] ?? ''), '/');

        return $this->originFromParts($resource).'/.well-known/oauth-protected-resource'.$path;
    }

    public function url(string $path): string
    {
        return $this->authorizationServerUri().'/'.ltrim($path, '/');
    }

    public function challenge(): string
    {
        return 'Bearer resource_metadata="'.$this->protectedResourceMetadataUri().'", scope="mcp:use"';
    }

    private function origin(): string
    {
        $parts = $this->urlParts((string) config('app.url'), 'APP_URL');

        return $this->originFromParts($parts);
    }

    /** @return array{scheme: string, host: string, port?: int, path?: string} */
    private function urlParts(string $url, string $setting): array
    {
        try {
            $parts = parse_url($url);
        } catch (\ValueError) {
            $parts = false;
        }

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])
            || array_key_exists('user', $parts) || array_key_exists('pass', $parts)
            || array_key_exists('query', $parts) || array_key_exists('fragment', $parts)) {
            throw new InvalidArgumentException($setting.' must be an absolute HTTP(S) URL without credentials, query or fragment.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException($setting.' must use HTTP or HTTPS.');
        }

        return [
            ...$parts,
            'scheme' => $scheme,
            'host' => strtolower((string) $parts['host']),
        ];
    }

    /** @param array{scheme: string, host: string, port?: int} $parts */
    private function originFromParts(array $parts): string
    {
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port;
    }

    private function normalizeUrl(string $url, string $setting): string
    {
        $parts = $this->urlParts($url, $setting);
        $path = rtrim((string) ($parts['path'] ?? ''), '/');

        return $this->originFromParts($parts).$path;
    }

    private function configured(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && trim($value) !== '' ? rtrim(trim($value), '/') : null;
    }
}
