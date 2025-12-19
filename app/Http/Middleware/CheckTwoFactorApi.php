<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckTwoFactorApi
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Exempter les routes publiques/auth
        // On permet check-auth et check-2fa-required pour que le front puisse tester l'état sans être bloqué
        if (
            $request->is('api/auth/*') ||
            $request->is('api/2fa/*') ||
            $request->is('api/check-auth') ||
            $request->routeIs('logout')
        ) {
            return $next($request);
        }

        // 2. Vérifier l'utilisateur
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        // 3. Si 2FA désactivée -> on laisse passer
        if (!$user->two_factor_enabled) {
            return $next($request);
        }

        // 4. VÉRIFICATION DE BASE (Date de validation)
        // Si le champ est vide, c'est que la 2FA n'est pas passée ou a été révoquée
        if (is_null($user->two_factor_verified_at)) {
            return response()->json([
                'success'      => false,
                'message'      => 'Vérification 2FA requise',
                'requires_2fa' => true,
                'redirect_url' => '/two-factor-verify',
            ], 403);
        }

        // 5. PROTECTION RENFORCÉE (IP Binding)
        // Si l'IP actuelle est différente de l'IP de confiance enregistrée en BDD
        // On considère la session compromise ou l'environnement changé -> On force la re-vérification
        if ($user->trusted_device_ip !== $request->ip()) {

            // On révoque la validation immédiatement
            $user->two_factor_verified_at = null;
            $user->save();

            return response()->json([
                'success'      => false,
                'message'      => 'Changement d\'adresse IP détecté. Veuillez valider à nouveau votre identité.',
                'requires_2fa' => true,
                'reason'       => 'ip_changed',
                'redirect_url' => '/two-factor-verify',
            ], 403);
        }

        return $next($request);
    }
}
