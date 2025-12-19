<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // 1. Vérifier si l'utilisateur est authentifié
        if (!$user) {
            Log::warning('CheckUserStatus: User not authenticated', [
                'url' => $request->fullUrl(),
                'session_exists' => Session::getId(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
                'requires_logout' => true,
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        // 2. Vérifier l'expiration de la session
        $lastActivity = Session::get('last_activity');
        $sessionLifetime = config('session.lifetime', 120) * 60; // en secondes

        if ($lastActivity && (time() - $lastActivity > $sessionLifetime)) {
            Log::info('CheckUserStatus: Session expired', [
                'user_id' => $user->id,
                'last_activity' => $lastActivity,
                'session_id' => Session::getId(),
            ]);

            // Déconnecter l'utilisateur
            Auth::logout();
            Session::invalidate();

            return response()->json([
                'success' => false,
                'message' => 'Session expirée',
                'requires_logout' => true,
                'error_code' => 'SESSION_EXPIRED'
            ], 419);
        }

        // 3. Vérifier le statut de l'utilisateur
        if (!$this->isUserActive($user)) {
            Log::warning('CheckUserStatus: User account disabled', [
                'user_id' => $user->id,
                'statut' => $user->statut,
            ]);

            // Déconnecter l'utilisateur
            Auth::logout();
            Session::invalidate();

            return response()->json([
                'success' => false,
                'message' => 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.',
                'requires_logout' => true,
                'error_code' => 'ACCOUNT_DISABLED'
            ], 403);
        }

        // 4. Mettre à jour le timestamp d'activité
        Session::put('last_activity', time());
        Session::save();

        // 5. Poursuivre la requête
        return $next($request);
    }

    /**
     * Vérifier si l'utilisateur est actif
     */
    private function isUserActive($user): bool
    {
        if (!isset($user->statut)) {
            return true; // Si pas de champ statut, considérer comme actif
        }

        $statut = strtolower(trim($user->statut));
        $allowedStatuses = ['actif', 'active', 'activé', 'enabled', '1', 'true'];

        return in_array($statut, $allowedStatuses);
    }
}
