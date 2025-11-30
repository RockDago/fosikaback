<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class CheckSession
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Vérifier si l'utilisateur est authentifié
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
                'requires_logout' => true,
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        // Vérifier si la session existe
        if (!$request->session()) {
            Log::warning('No session found', [
                'user_id' => $request->user()->id,
                'user_type' => get_class($request->user()),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Session non disponible',
                'requires_logout' => true,
                'error_code' => 'NO_SESSION'
            ], 419);
        }

        $user = $request->user();
        $lastActivity = $request->session()->get('last_activity');
        $sessionLifetime = config('session.lifetime', 120) * 60; // en secondes

        // Vérifier l'expiration de la session
        if ($lastActivity && (time() - $lastActivity > $sessionLifetime)) {
            Log::info('Session expired', [
                'user_id' => $user->id,
                'user_type' => get_class($user),
                'last_activity' => $lastActivity,
                'current_time' => time(),
                'ip' => $request->ip()
            ]);

            // Déconnecter l'utilisateur
            $request->user()->currentAccessToken()->delete();
            $request->session()->invalidate();

            return response()->json([
                'success' => false,
                'message' => 'Session expirée',
                'requires_logout' => true,
                'error_code' => 'SESSION_EXPIRED'
            ], 419);
        }

        // Vérifier le statut pour TeamUser
        if ($user instanceof \App\Models\TeamUser && !$user->statut) {
            Log::warning('Disabled user attempted access', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip()
            ]);

            // Déconnecter l'utilisateur
            $request->user()->currentAccessToken()->delete();
            $request->session()->invalidate();

            return response()->json([
                'success' => false,
                'message' => 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.',
                'requires_logout' => true,
                'error_code' => 'ACCOUNT_DISABLED'
            ], 403);
        }

        // Mettre à jour le timestamp d'activité
        $request->session()->put('last_activity', time());

        // Ajouter des headers pour le debug
        $response = $next($request);
        
        $response->headers->set('X-Session-Status', 'active');
        $response->headers->set('X-Session-User-Type', get_class($user));
        $response->headers->set('X-Session-User-ID', $user->id);

        return $response;
    }
}