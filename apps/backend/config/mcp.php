<?php

return [
    'enabled' => (bool) env('MCP_ENABLED', false),
    'authorization_server' => null,
    'redirect_domains' => [
        'https://chatgpt.com/connector/oauth/',
    ],
    'custom_schemes' => [],
];
