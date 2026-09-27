<?php

namespace App\Mcp\Http;

use App\Mcp\OAuth\McpResource;
use App\Mcp\OAuth\RedirectUriValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AddMcpOAuthIssuer
{
    public function __construct(
        private readonly McpResource $resource,
        private readonly RedirectUriValidator $redirects,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! $response->isRedirection()) {
            return $response;
        }

        $location = $response->headers->get('Location');
        if (! is_string($location)) {
            return $response;
        }

        try {
            $parts = parse_url($location);
        } catch (\ValueError) {
            return $response;
        }

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])
            || array_key_exists('user', $parts) || array_key_exists('pass', $parts)
            || array_key_exists('fragment', $parts)) {
            return $response;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $callback = strtolower((string) $parts['scheme']).'://'.strtolower((string) $parts['host']).$port.($parts['path'] ?? '');
        if (! $this->redirects->allows($callback)) {
            return $response;
        }

        parse_str((string) ($parts['query'] ?? ''), $parameters);
        if (isset($parameters['iss']) && (! is_string($parameters['iss']) || ! hash_equals($this->resource->authorizationServerUri(), $parameters['iss']))) {
            return response()->json(['error' => 'server_error'], 500);
        }
        $parameters['iss'] = $this->resource->authorizationServerUri();
        $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        $url = $callback.($query !== '' ? '?'.$query : '');

        $response->headers->set('Location', $url);

        return $response;
    }
}
