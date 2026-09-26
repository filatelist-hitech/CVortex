<?php

use App\Mcp\CvortexServer;
use App\Mcp\Http\EnsureMcpAccess;
use App\Mcp\Http\RequireMcpBearer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

if (config('mcp.enabled')) {
    Route::middleware('throttle:30,1')->group(fn () => Mcp::oauthRoutes());

    Mcp::web('/mcp/v1', CvortexServer::class)->middleware([
        RequireMcpBearer::class,
        'auth:api',
        'active-user',
        EnsureMcpAccess::class,
        'db-owner-context',
    ]);
}
