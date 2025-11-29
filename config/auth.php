<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */

    'guards' => [

        // Utilisateurs normaux (TeamUser)
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // API Utilisateur normal avec Sanctum
        'api' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],

        // Admin avec SESSION (si tu fais login admin via formulaire)
        'admin_web' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],

        // Admin avec API Sanctum (dashboard React)
        'admin' => [
            'driver' => 'sanctum',
            'provider' => 'admins',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [

        // TeamUser (utilisateurs normaux)
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\TeamUser::class,
        ],

        // Admin (administrateurs)
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Reset Settings
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_resets',
            'expire' => 60,
            'throttle' => 60,
        ],

        'admins' => [
            'provider' => 'admins',
            'table' => 'password_resets',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => 10800,

];
