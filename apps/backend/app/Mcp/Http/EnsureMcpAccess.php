<?php

namespace App\Mcp\Http;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user('api');
        if ($user === null || ! $user->tokenCan('mcp:use')) {
            abort(403, 'MCP scope required.');
        }

        $method = $request->input('method');
        $name = $method === 'tools/call' ? $request->input('params.name') : null;
        $write = $name === 'application_draft_submit';
        $bucket = $write ? 'write' : 'read';
        $key = 'mcp:'.$bucket.':'.$user->id;
        $limit = $write ? 10 : 120;
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            Log::warning('mcp.rate_limited', [
                'request_id' => $request->attributes->get('request_id'),
                'user_id' => (string) $user->id,
                'bucket' => $bucket,
            ]);
            abort(429, 'MCP rate limit exceeded.');
        }
        RateLimiter::hit($key, 60);

        $started = microtime(true);
        $status = 500;
        try {
            $response = $next($request);
            $status = $response->getStatusCode();

            return $response;
        } finally {
            Log::info('mcp.invocation', [
                'request_id' => $request->attributes->get('request_id'),
                'user_id' => (string) $user->id,
                'method' => is_string($method) && in_array($method, ['server/discover', 'initialize', 'tools/list', 'tools/call'], true) ? $method : 'other',
                'tool' => is_string($name) && in_array($name, ['vacancy_get', 'application_context_get', 'application_draft_submit'], true) ? $name : null,
                'status' => $status,
                'outcome' => $request->attributes->get('mcp_outcome', 'PROTOCOL'),
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
        }
    }
}
