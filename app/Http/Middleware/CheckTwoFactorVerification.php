<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;

class CheckTwoFactorVerification
{
    /**
     * Routes exemptées de la vérification 2FA
     */
    protected $exemptRoutes = [
        'login',
        'logout',
        'two-factor.*',
        'password.*',
        'register',
        'email.*',
        'api.login',
        'api.auth.*',
        'api.2fa.*',
        'api.email.*',
        'api.debug.*',
        'api.health',
        'api.check-auth',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Log pour debug
        Log::info('CheckTwoFactorVerification middleware déclenché', [
            'route' => $request->route()->getName(),
            'path' => $request->path(),
            'user_authenticated' => Auth::check(),
            'user_id' => Auth::check() ? Auth::id() : null,
            'ip' => $request->ip(),
            'user_agent' => substr($request->userAgent(), 0, 100)
        ]);

        // Vérifier si la route est exemptée
        if ($this->isExemptRoute($request)) {
            Log::info('Route exemptée de 2FA', ['route' => $request->route()->getName()]);
            return $next($request);
        }

        // Si l'utilisateur n'est pas authentifié, continuer
        if (!Auth::check()) {
            Log::info('Utilisateur non authentifié, passer');
            return $next($request);
        }

        $user = Auth::user();

        // Si l'utilisateur n'a pas activé la 2FA, passer
        if (!$user->two_factor_enabled) {
            Log::info('2FA non activée pour l\'utilisateur', ['user_id' => $user->id]);
            return $next($request);
        }

        Log::info('Vérification 2FA pour l\'utilisateur', [
            'user_id' => $user->id,
            '2fa_enabled' => $user->two_factor_enabled
        ]);

        // Vérifier si la 2FA a été vérifiée
        if (!$this->isTwoFactorVerified($request, $user)) {
            Log::warning('2FA non vérifiée, redirection vers vérification', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return $this->redirectToTwoFactor($request);
        }

        Log::info('2FA vérifiée, accès autorisé', ['user_id' => $user->id]);
        return $next($request);
    }

    /**
     * Vérifier si la route est exemptée
     */
    private function isExemptRoute(Request $request): bool
    {
        $routeName = $request->route()->getName();

        if (!$routeName) {
            return in_array($request->path(), [
                'two-factor/verify',
                'two-factor/resend',
                'login',
                'logout',
                'api/auth/login',
                'api/auth/verify-2fa',
                'api/auth/resend-2fa-code',
                'api/auth/2fa-status',
                'api/auth/device-status',
                'api/auth/check-2fa-required',
                'api/2fa/send-code',
                'api/2fa/verify-code',
                'api/2fa/status',
                'api/email/verify',
                'api/email/verification-notification',
                'api/debug/token-info',
                'api/debug/auth-status',
                'api/debug/2fa-info',
            ]);
        }

        foreach ($this->exemptRoutes as $exemptRoute) {
            if (str_ends_with($exemptRoute, '.*')) {
                $pattern = str_replace('.*', '', $exemptRoute);
                if (str_starts_with($routeName, $pattern)) {
                    return true;
                }
            } elseif ($routeName === $exemptRoute) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifier si la 2FA est vérifiée
     * ✅ CORRIGÉ : Logique de flux correcte
     */
    private function isTwoFactorVerified(Request $request, $user): bool
    {
        // DEBUG
        Log::info('Vérification état 2FA', [
            'session_2fa_verified' => Session::get('2fa_verified'),
            'cookie_remember' => $request->hasCookie('two_factor_remember'),
            'has_stored_device' => Session::has('two_factor_verified_device')
        ]);

        // 1. Vérifier "Remember me" cookie EN PREMIER
        $rememberToken = $request->cookie('two_factor_remember');
        if ($rememberToken && method_exists($user, 'verifyTwoFactorRememberToken')) {
            Log::info('Vérification token remember me', ['has_token' => !empty($rememberToken)]);

            if ($user->verifyTwoFactorRememberToken($rememberToken)) {
                Log::info('Token remember me validé - accès autorisé');
                // Stocker les informations de l'appareil pour cette session
                $this->storeDeviceInfo($request);
                // Marquer comme vérifié dans la session
                Session::put('2fa_verified', true);
                Session::put('2fa_verified_at', now());
                return true;
            } else {
                Log::warning('Token remember me invalide ou expiré');
            }
        }

        // 2. Vérifier si la 2FA est vérifiée dans la session
        if (!Session::get('2fa_verified', false)) {
            Log::info('2FA non vérifiée dans la session - vérification requise');
            return false; // ❌ Pas encore vérifié
        }

        // 3. Vérifier si la vérification a expiré (24h)
        $verifiedAt = Session::get('2fa_verified_at');
        if ($verifiedAt && now()->diffInHours($verifiedAt) > 24) {
            Log::warning('Vérification 2FA expirée (>24h)', ['verified_at' => $verifiedAt]);
            Session::forget(['2fa_verified', '2fa_verified_at', 'two_factor_verified_device']);
            return false; // ❌ Expiré
        }

        // 4. Vérifier si l'appareil a changé
        $verifiedDevice = Session::get('two_factor_verified_device');

        // ✅ CORRECTION : Si pas d'appareil enregistré ET session vérifiée,
        // c'est probablement juste après la vérification - stocker et autoriser
        if (!$verifiedDevice) {
            if (Session::get('2fa_verified', false)) {
                Log::info('Session vérifiée mais pas d\'appareil stocké - stockage des infos');
                $this->storeDeviceInfo($request);
                return true; // ✅ Juste vérifié, autoriser
            } else {
                Log::info('Pas d\'appareil vérifié et session non vérifiée - vérification requise');
                return false; // ❌ Première connexion sans vérification
            }
        }

        // 5. Vérifier les changements d'appareil
        if ($this->hasDeviceChanged($request, $verifiedDevice)) {
            Log::warning('Changement d\'appareil détecté - nouvelle vérification requise', [
                'previous_ip' => $verifiedDevice['ip'],
                'current_ip' => $request->ip(),
                'previous_browser' => $verifiedDevice['browser'],
                'current_browser' => (new Agent())->browser()
            ]);

            // Détection de changement - forcer une nouvelle vérification
            $this->storeDeviceChanges($request, $verifiedDevice);

            // ✅ Effacer la vérification actuelle
            Session::forget(['2fa_verified', '2fa_verified_at']);

            return false; // ❌ Changement détecté, re-vérification requise
        }

        Log::info('2FA pleinement vérifiée et valide - accès autorisé');
        return true; // ✅ Tout est OK
    }

    /**
     * Stocker les informations de l'appareil
     */
    private function storeDeviceInfo(Request $request): void
    {
        $agent = new Agent();

        $deviceInfo = [
            'ip' => $request->ip(),
            'browser' => $agent->browser(),
            'browser_version' => $agent->version($agent->browser()),
            'platform' => $agent->platform(),
            'platform_version' => $agent->version($agent->platform()),
            'device' => $agent->device(),
            'is_robot' => $agent->isRobot(),
            'robot_name' => $agent->robot(),
            'user_agent' => $request->userAgent(),
            'verified_at' => now(),
        ];

        Session::put('two_factor_verified_device', $deviceInfo);

        Log::info('Informations appareil stockées', [
            'ip' => $deviceInfo['ip'],
            'browser' => $deviceInfo['browser'],
            'platform' => $deviceInfo['platform']
        ]);
    }

    /**
     * Vérifier si l'appareil a changé
     */
    private function hasDeviceChanged(Request $request, array $storedDevice): bool
    {
        $agent = new Agent();

        $currentDevice = [
            'ip' => $request->ip(),
            'browser' => $agent->browser(),
            'platform' => $agent->platform(),
            'device' => $agent->device(),
            'user_agent' => $request->userAgent(),
        ];

        // Réinitialiser les flags
        Session::forget([
            'two_factor_ip_changed',
            'two_factor_browser_changed',
            'two_factor_platform_changed',
            'two_factor_device_changed'
        ]);

        $hasChanged = false;

        // 1. Changement d'IP
        if ($storedDevice['ip'] !== $currentDevice['ip']) {
            $hasChanged = true;
            Session::put('two_factor_ip_changed', true);
            Session::put('two_factor_previous_ip', $storedDevice['ip']);
            Session::put('two_factor_new_ip', $currentDevice['ip']);

            Log::warning('IP changée', [
                'previous' => $storedDevice['ip'],
                'new' => $currentDevice['ip']
            ]);
        }

        // 2. Changement de navigateur
        if ($storedDevice['browser'] !== $currentDevice['browser']) {
            $hasChanged = true;
            Session::put('two_factor_browser_changed', true);
            Session::put('two_factor_previous_browser', $storedDevice['browser']);
            Session::put('two_factor_new_browser', $currentDevice['browser']);

            Log::warning('Navigateur changé', [
                'previous' => $storedDevice['browser'],
                'new' => $currentDevice['browser']
            ]);
        }

        // 3. Changement de système d'exploitation
        if ($storedDevice['platform'] !== $currentDevice['platform']) {
            $hasChanged = true;
            Session::put('two_factor_platform_changed', true);
            Session::put('two_factor_previous_platform', $storedDevice['platform']);
            Session::put('two_factor_new_platform', $currentDevice['platform']);

            Log::warning('Plateforme changée', [
                'previous' => $storedDevice['platform'],
                'new' => $currentDevice['platform']
            ]);
        }

        return $hasChanged;
    }

    /**
     * Stocker les détails des changements détectés
     */
    private function storeDeviceChanges(Request $request, array $previousDevice): void
    {
        $agent = new Agent();

        $currentDevice = [
            'ip' => $request->ip(),
            'browser' => $agent->browser(),
            'platform' => $agent->platform(),
            'device' => $agent->device(),
        ];

        $changes = [
            'ip_changed' => $previousDevice['ip'] !== $currentDevice['ip'],
            'browser_changed' => $previousDevice['browser'] !== $currentDevice['browser'],
            'platform_changed' => $previousDevice['platform'] !== $currentDevice['platform'],
            'previous_device' => $previousDevice,
            'current_device' => $currentDevice,
            'detected_at' => now(),
        ];

        Session::put('two_factor_detected_changes', $changes);
        Session::put('two_factor_requires_verification', true);
        Session::put('two_factor_intended', $request->fullUrl());

        Log::warning('Changements d\'appareil stockés', [
            'ip_changed' => $changes['ip_changed'],
            'browser_changed' => $changes['browser_changed'],
            'platform_changed' => $changes['platform_changed']
        ]);
    }

    /**
     * Rediriger vers la vérification 2FA
     */
    private function redirectToTwoFactor(Request $request)
    {
        Log::info('Redirection vers page 2FA', [
            'intended_url' => Session::get('two_factor_intended'),
            'has_changes' => Session::get('two_factor_requires_verification')
        ]);

        // Si c'est une requête API/JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Vérification 2FA requise',
                'requires_2fa' => true,
                'device_changed' => Session::get('two_factor_requires_verification', false),
                'changes' => Session::get('two_factor_detected_changes', []),
                'redirect_url' => url('/two-factor/verify')
            ], 403);
        }

        // Stocker l'URL d'origine pour redirection après vérification
        if (!$request->routeIs('two-factor.*')) {
            Session::put('two_factor_intended', $request->fullUrl());
        }

        // Marquer que la 2FA est requise
        Session::put('two_factor_required', true);

        return redirect()->route('two-factor.verify')
            ->with('warning', 'Vérification de sécurité requise. Un code a été envoyé à votre email.');
    }
}
