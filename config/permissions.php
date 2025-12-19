<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rôles et permissions
    |--------------------------------------------------------------------------
    |
    | Définition des rôles et de leurs permissions associées.
    |
    */

    'roles' => [
        'admin' => [
            'name' => 'Administrateur',
            'permissions' => [
                'view_dashboard',
                'manage_users',
                'manage_reports',
                'view_analytics',
                'manage_settings',
                'export_data',
                'manage_audit_logs',
            ],
        ],

        'agent' => [
            'name' => 'Agent',
            'permissions' => [
                'view_dashboard',
                'create_reports',
                'view_reports',
                'update_assigned_reports',
                'upload_files',
            ],
        ],

        'investigateur' => [
            'name' => 'Investigateur',
            'permissions' => [
                'view_dashboard',
                'view_assigned_investigations',
                'update_investigation_status',
                'add_investigation_notes',
                'generate_investigation_reports',
                'upload_evidence',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes par rôle
    |--------------------------------------------------------------------------
    |
    | Définition des routes accessibles par rôle.
    |
    */
    'routes_by_role' => [
        'admin' => [
            '/admin/*',
            '/profile',
            '/reports/*',
            '/users/*',
            '/analytics/*',
            '/settings/*',
        ],
        'agent' => [
            '/profile',
            '/reports/create',
            '/reports/view/*',
            '/reports/assigned',
        ],
        'investigateur' => [
            '/profile',
            '/investigations/*',
            '/reports/investigate/*',
        ],
    ],
];
