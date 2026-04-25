<?php

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'auth/*',
        'login',
        'logout',
        'email/*',
        'user/*',
        'files/*',
        'reports/tracking/*',
        'chat/*', 
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'https://fosika.mesupres.mg',
        'https://www.fosika.mesupres.mg',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-Session-Token',
        'X-XSRF-TOKEN',
        'Access-Control-Allow-Origin',
    ],

    'supports_credentials' => true,

    'max_age' => 86400, 
];
