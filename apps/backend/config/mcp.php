<?php

return [
    'enabled' => (bool) env('MCP_ENABLED', false),
    'authorization_server' => null,
    // DCR is handled by RegisterOAuthClientController's structured URI policy.
    'redirect_domains' => [],
    'custom_schemes' => [],
];
