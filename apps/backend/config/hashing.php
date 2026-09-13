<?php

return [
    'driver' => env('HASH_DRIVER', 'argon2id'),

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => filter_var(env('HASH_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => filter_var(env('HASH_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'rehash_on_login' => true,
];
