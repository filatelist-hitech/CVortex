<?php

return [
    'enabled' => (bool) env('CHATGPT_PLAN_ENABLED', false),
    'callback_uri' => env('CHATGPT_CALLBACK_URI', 'http://127.0.0.1:8080/api/v1/chatgpt/callback'),
    'host_file' => storage_path('app/private/chatgpt-host-id'),
    'authorize_url' => 'https://auth.openai.com/api/accounts/authorize',
    'token_url' => 'https://auth.openai.com/api/accounts/oauth/token',
    'discovery_url' => 'https://auth.openai.com/.well-known/openid-configuration',
    'resource' => 'https://api.openai.com/v1',
    'scopes' => 'openid profile email offline_access resource.invoke chatgpt.tokens.use.direct',
];
