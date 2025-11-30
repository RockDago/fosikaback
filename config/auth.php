<?php

return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'teams',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'teams',
        ],

        // ✅ Guard pour Admin (session)
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],

        // ✅ Guard API Sanctum (tokens) pour tous les modèles
        // Sanctum résout dynamiquement le modèle à partir du token
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => null,
        ],

        // ✅ Guard API dédié aux utilisateurs TeamUser (optionnel mais propre)
        'team-api' => [
            'driver' => 'sanctum',
            'provider' => 'teams',
        ],
    ],

    'providers' => [
        // ✅ Provider principal = TeamUser
        'teams' => [
            'driver'  => 'eloquent',
            'model'   => App\Models\TeamUser::class,
        ],

        // ✅ Provider pour Admin
        'admins' => [
            'driver'  => 'eloquent',
            'model'   => App\Models\Admin::class,
        ],
    ],

    'passwords' => [
        // ✅ Reset password pour TeamUser
        'teams' => [
            'provider' => 'teams',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];
