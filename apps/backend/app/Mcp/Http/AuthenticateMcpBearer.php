<?php

namespace App\Mcp\Http;

use App\Mcp\OAuth\McpResource;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AuthenticateMcpBearer
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = Auth::guard('api')->user();
        } catch (Throwable $exception) {
            Log::notice('mcp.authentication_rejected', [
                'request_id' => $request->attributes->get('request_id'),
                'exception_type' => $exception::class,
            ]);

            return $this->unauthorized();
        }

        if (! $user instanceof User) {
            Log::notice('mcp.authentication_rejected', [
                'request_id' => $request->attributes->get('request_id'),
                'exception_type' => 'Unauthenticated',
            ]);

            return $this->unauthorized();
        }

        Auth::shouldUse('api');
        $request->setUserResolver(static fn (?string $guard = null) => Auth::guard($guard ?? 'api')->user());

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json(['error' => 'unauthorized'], 401)
            ->header('WWW-Authenticate', app(McpResource::class)->challenge());
    }
}
