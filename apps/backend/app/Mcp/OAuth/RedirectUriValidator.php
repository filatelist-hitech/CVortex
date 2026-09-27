<?php

declare(strict_types=1);

namespace App\Mcp\OAuth;

final class RedirectUriValidator
{
    public function allows(string $uri): bool
    {
        try {
            $parts = parse_url($uri);
        } catch (\ValueError) {
            return false;
        }

        if (! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || array_key_exists('user', $parts)
            || array_key_exists('pass', $parts)
            || array_key_exists('query', $parts)
            || array_key_exists('fragment', $parts)) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';
        $port = $parts['port'] ?? null;

        if (str_contains($path, '%') || str_contains($path, '\\')) {
            return false;
        }

        if ($scheme === 'https') {
            return ($port === null || $port === 443)
                && $host === 'chatgpt.com'
                && $this->isApprovedChatGptCallbackPath($path);
        }

        if ($scheme !== 'http' || ! is_int($port) || $port < 1) {
            return false;
        }

        return $path === '/oauth/callback' && $this->isLoopbackHost($host);
    }

    private function isApprovedChatGptCallbackPath(string $path): bool
    {
        if ($path === '/connector_platform_oauth_redirect') {
            return true;
        }

        $segments = explode('/', $path);

        if (count($segments) !== 4
            || $segments[0] !== ''
            || $segments[1] !== 'connector'
            || $segments[2] !== 'oauth') {
            return false;
        }

        $callbackId = $segments[3];

        return $callbackId !== '.'
            && $callbackId !== '..'
            && preg_match('/\A[A-Za-z0-9._~-]{1,128}\z/', $callbackId) === 1;
    }

    private function isLoopbackHost(string $host): bool
    {
        if ($host === 'localhost') {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $packed = inet_pton($host);

            return is_string($packed)
                && ord($packed[0]) === 127
                && inet_ntop($packed) === $host;
        }

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $address = substr($host, 1, -1);

            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                return false;
            }

            $packed = inet_pton($address);

            return is_string($packed)
                && inet_ntop($packed) === '::1';
        }

        return false;
    }
}
