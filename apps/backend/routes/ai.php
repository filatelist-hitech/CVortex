<?php

use App\Mcp\CvortexServer;
use App\Mcp\Http\AuthenticateMcpBearer;
use App\Mcp\Http\Controllers\RegisterOAuthClientController;
use App\Mcp\Http\EnsureMcpAccess;
use App\Mcp\Http\RequireMcpBearer;
use App\Mcp\Http\RequireMcpResourceToken;
use App\Mcp\OAuth\McpResource;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

if (config('mcp.enabled')) {
    Route::middleware('throttle:30,1')->group(function (): void {
        $protectedResourceMetadata = static function (McpResource $resource) {
            return response()->json([
                'resource' => $resource->resourceUri(),
                'authorization_servers' => [$resource->authorizationServerUri()],
                'scopes_supported' => ['mcp:use'],
                'bearer_methods_supported' => ['header'],
            ]);
        };

        // Keep the canonical path-specific document and the root/first-segment
        // discovery paths used by OAuth clients such as OpenAI's tunnel-client.
        Route::get('/.well-known/oauth-protected-resource/mcp/v1', $protectedResourceMetadata)
            ->name('mcp.oauth.protected-resource.cvortex');
        Route::get('/.well-known/oauth-protected-resource/mcp', $protectedResourceMetadata)
            ->name('mcp.oauth.protected-resource.mcp');
        Route::get('/.well-known/oauth-protected-resource', $protectedResourceMetadata)
            ->name('mcp.oauth.protected-resource.root');
        Route::get('/.well-known/oauth-authorization-server', static function (McpResource $resource) {
            return response()->json([
                'issuer' => $resource->authorizationServerUri(),
                'authorization_endpoint' => $resource->url('/oauth/authorize'),
                'token_endpoint' => $resource->url('/oauth/token'),
                'registration_endpoint' => $resource->url('/oauth/register'),
                'response_types_supported' => ['code'],
                'response_modes_supported' => ['query'],
                'grant_types_supported' => ['authorization_code', 'refresh_token'],
                'token_endpoint_auth_methods_supported' => ['none'],
                'code_challenge_methods_supported' => ['S256'],
                'scopes_supported' => ['mcp:use'],
                'authorization_response_iss_parameter_supported' => true,
            ]);
        })->name('mcp.oauth.authorization-server.cvortex');

        Mcp::oauthRoutes();

        // Replace the package DCR action so registrations use CVortex's URI policy.
        Route::post('/oauth/register', RegisterOAuthClientController::class);
    });

    Mcp::web('/mcp/v1', CvortexServer::class)->middleware([
        RequireMcpBearer::class,
        AuthenticateMcpBearer::class,
        'active-user',
        EnsureMcpAccess::class,
        RequireMcpResourceToken::class,
        'db-owner-context',
    ]);
}
