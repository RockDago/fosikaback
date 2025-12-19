<?php

use Laravel\Sanctum\Sanctum;

return [

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,127.0.0.1:3000,::1,' .
        'fosika.mesupres.edu.mg,www.fosika.mesupres.edu.mg'
    )),

    'guard' => [
        // En mode API token, on laisse vide pour que Sanctum lise directement le bearer token
        // 'web',
    ],

    'expiration' => 1440, // 24h

    'middleware' => [
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
    ],

];

