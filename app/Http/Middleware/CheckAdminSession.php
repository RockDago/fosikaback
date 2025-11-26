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
        $admin = $request->user();
        $token = $request->bearerToken();

        // Vérifier si l'admin et le token existent
        if (!$admin || !$token) {
            Log::warning('Authentication failed: No admin or token', [
                'has_admin' => !is_null($admin),
                'has_token' => !is_null($token),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Session non authentifiée'
            ], 401);
        }

        // CORRECTION: Rendre la vérification session_id optionnelle
        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            Log::warning('Token not found in database', [
                'admin_id' => $admin->id,
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Token invalide'
            ], 401);
        }

        // Vérifier la session uniquement si session_id existe
        if ($accessToken->session_id) {
            if (!$admin->isValidSession($accessToken->session_id)) {
                // Supprimer le token invalide
                $accessToken->delete();
                
                Log::info('Invalid session detected', [
                    'admin_id' => $admin->id,
                    'session_id' => $accessToken->session_id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Session expirée ou utilisateur connecté ailleurs'
                ], 401);
            }
        }

        // Tout est valide, laisser passer
        return $next($request);
    }
}
