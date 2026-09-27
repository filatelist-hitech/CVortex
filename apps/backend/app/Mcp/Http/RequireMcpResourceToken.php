<?php

namespace App\Mcp\Http;

use App\Mcp\OAuth\McpResource;
use Closure;
use Illuminate\Http\Request;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RequireMcpResourceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = (new Parser(new JoseEncoder))->parse((string) $request->bearerToken());
            if (! $token instanceof Plain) {
                return $this->unauthorized();
            }

            $resource = $token->claims()->get('resource');
            $issuer = $token->claims()->get('iss');
            $expected = app(McpResource::class);

            if (is_string($resource) && hash_equals($expected->resourceUri(), $resource)
                && is_string($issuer) && hash_equals($expected->authorizationServerUri(), $issuer)) {
                return $next($request);
            }
        } catch (Throwable) {
            // Passport's auth:api middleware has already verified the JWT signature and token record.
        }

        return $this->unauthorized();
    }

    private function unauthorized(): Response
    {
        return response()->json(['error' => 'invalid_token'], 401)
            ->header('WWW-Authenticate', app(McpResource::class)->challenge());
    }
}
