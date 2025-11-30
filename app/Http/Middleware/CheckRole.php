<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        
        if (!$user) {
            Log::warning('Role check failed: no user', [
                'ip' => $request->ip(),
                'required_roles' => $roles
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
                'requires_logout' => true,
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        // Déterminer le rôle de l'utilisateur selon son type
        $userRole = $this->getUserRole($user);

        // Vérifier si l'utilisateur a un des rôles requis
        if (!in_array($userRole, $roles)) {
            Log::warning('Role check failed: insufficient permissions', [
                'user_id' => $user->id,
                'user_type' => get_class($user),
                'user_role' => $userRole,
                'required_roles' => $roles,
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé pour votre rôle',
                'error_code' => 'INSUFFICIENT_PERMISSIONS'
            ], 403);
        }

        // Vérification supplémentaire pour TeamUser (statut actif)
        if ($user instanceof \App\Models\TeamUser && !$user->statut) {
            Log::warning('Role check failed: disabled account', [
                'user_id' => $user->id,
                'email' => $user->email,
                'user_role' => $userRole,
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

        return $next($request);
    }

    /**
     * Get the user's role based on their type.
     */
    private function getUserRole($user): string
    {
        if ($user instanceof \App\Models\Admin) {
            return 'admin';
        } elseif ($user instanceof \App\Models\TeamUser) {
            // Vérifier si la relation role existe
            if ($user->relationLoaded('role')) {
                return $user->role->name; // 'agent' ou 'investigator'
            } else {
                // Charger la relation si elle n'est pas déjà chargée
                $user->load('role');
                return $user->role->name;
            }
        }

        return 'unknown';
    }
}