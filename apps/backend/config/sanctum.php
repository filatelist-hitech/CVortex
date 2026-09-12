<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1,%s,%s',
        Sanctum::currentApplicationUrlWithPort(),
        Sanctum::currentRequestHost(),
    ))),
    'guard' => ['web'],
    'expiration' => null,
    'token_prefix' => '',
    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
