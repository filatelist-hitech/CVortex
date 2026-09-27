<?php

return [
    'enabled' => (bool) env('MCP_ENABLED', false),
    'resource' => env('MCP_RESOURCE_URL'),
    'authorization_server' => env('MCP_AUTHORIZATION_SERVER_URL'),
    // DCR is handled by RegisterOAuthClientController's structured URI policy.
    'redirect_domains' => [],
    'custom_schemes' => [],
];
