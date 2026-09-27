<?php

namespace App\Mcp\Http;

use App\Mcp\OAuth\McpResource;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireMcpOAuthResource
{
    public function handle(Request $request, Closure $next): Response
    {
        $resource = $request->input('resource');
        if (! is_string($resource) || ! hash_equals(app(McpResource::class)->resourceUri(), $resource)) {
            return response()->json([
                'error' => 'invalid_target',
                'error_description' => 'The resource parameter must match the CVortex MCP endpoint.',
            ], 400);
        }

        return $next($request);
    }
}
