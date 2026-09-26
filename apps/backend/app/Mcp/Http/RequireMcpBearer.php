<?php

namespace App\Mcp\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireMcpBearer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! preg_match('/^Bearer [A-Za-z0-9._~+\/-]+={0,2}$/', (string) $request->header('Authorization'))) {
            abort(401, 'Bearer authorization required.');
        }

        return $next($request);
    }
}
