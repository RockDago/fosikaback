<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Log;

class CheckAdminSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $token = $request->bearerToken();

        // Vérifier si l'utilisateur et le token existent
        if (!$user || !$token) {
            Log::warning('Session check failed: No user or token', [
                'has_user' => !is_null($user),
                'has_token' => !is_null($token),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Session non authentifiée',
                'requires_logout' => true,
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        // Vérifier que c'est bien un admin
        if (!$user instanceof \App\Models\Admin) {
            Log::warning('Admin session check failed: User is not admin', [
                'user_type' => get_class($user),
                'user_id' => $user->id,
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux administrateurs',
                'requires_logout' => true,
                'error_code' => 'NOT_ADMIN'
            ], 403);
        }

        // Vérifier le token dans la base de données
        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            Log::warning('Session check failed: Token not found', [
                'user_id' => $user->id,
                'user_type' => get_class($user),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Token invalide',
                'requires_logout' => true,
                'error_code' => 'INVALID_TOKEN'
            ], 401);
        }

        // Vérifier que le token appartient à cet utilisateur
        if ($accessToken->tokenable_id !== $user->id || $accessToken->tokenable_type !== get_class($user)) {
            Log::warning('Session check failed: Token mismatch', [
                'user_id' => $user->id,
                'token_user_id' => $accessToken->tokenable_id,
                'token_type' => $accessToken->tokenable_type,
                'ip' => $request->ip()
            ]);
            
            $accessToken->delete();
            
            return response()->json([
                'success' => false,
                'message' => 'Token invalide',
                'requires_logout' => true,
                'error_code' => 'TOKEN_MISMATCH'
            ], 401);
        }

        // Vérification session_id optionnelle (si la méthode existe)
        if ($accessToken->session_id && method_exists($user, 'isValidSession')) {
            if (!$user->isValidSession($accessToken->session_id)) {
                // Supprimer le token invalide
                $accessToken->delete();
                
                Log::info('Session check failed: Invalid session', [
                    'user_id' => $user->id,
                    'session_id' => $accessToken->session_id,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Session expirée ou utilisateur connecté ailleurs',
                    'requires_logout' => true,
                    'error_code' => 'SESSION_EXPIRED'
                ], 401);
            }
        }

        // Mettre à jour la dernière activité
        if ($request->session()) {
            $request->session()->put('last_activity', time());
        }

        Log::info('Admin session check passed', [
            'admin_id' => $user->id,
            'ip' => $request->ip()
        ]);

        return $next($request);
    }
}