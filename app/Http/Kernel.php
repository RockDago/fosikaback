<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * Les middlewares globaux de l'application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // ✅ AJOUTEZ CorsFix ICI EN PREMIER (avant tout autre middleware)
        \App\Http\Middleware\CorsFix::class,
        
        // \Illuminate\Http\Middleware\TrustProxies::class,
        // \Illuminate\Http\Middleware\HandleCors::class, // ← Retirez celui-ci si présent ici
        // \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
        // \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        // \App\Http\Middleware\TrimStrings::class,
        // \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * Les groupes de middlewares de l'application.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            // ✅ RETIREZ HandleCors car vous utilisez CorsFix à la place
            // \Illuminate\Http\Middleware\HandleCors::class,
            
            // ✅ CORRECTION: Augmentation de la limite pour le chat
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * Les middlewares de route de l'application.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        
        // ✅ CORRECTION: Throttle reste ici
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,

        'check.status' => \App\Http\Middleware\CheckUserStatus::class,
        'check.role' => \App\Http\Middleware\CheckRole::class,
        'check.permission' => \App\Http\Middleware\CheckPermission::class,
        'log.actions' => \App\Http\Middleware\LogUserActions::class,

        // Middlewares 2FA
        'check.twofactor' => \App\Http\Middleware\CheckTwoFactorVerification::class,
        'twofactor.api' => \App\Http\Middleware\CheckTwoFactorApi::class,
        
        // ✅ OPTIONNEL: Enregistrez CorsFix comme middleware nommé aussi
        'cors' => \App\Http\Middleware\CorsFix::class,
    ];

    /**
     * L'ordre des middlewares pour les requêtes.
     *
     * @var array<int, class-string|string>
     */
    protected $middlewarePriority = [
        \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequests::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,

        \App\Http\Middleware\CheckUserStatus::class,
        \App\Http\Middleware\CheckRole::class,
        \App\Http\Middleware\CheckPermission::class,
    ];
}
