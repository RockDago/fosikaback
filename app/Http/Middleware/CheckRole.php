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

        // ✅ CORRECTION : Support des admins de la table admins
        // Les utilisateurs de la table admins ont automatiquement le rôle 'Admin'
        if ($user instanceof \App\Models\Admin) {
            // Si la route demande le rôle 'Admin', on autorise
            if (in_array('Admin', $roles)) {
                return $next($request);
            }
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
            if (method_exists($user, 'currentAccessToken')) {
                $user->currentAccessToken()->delete();
            }
            
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
        // ✅ CORRECTION : Les admins de la table admins ont le rôle 'Admin'
        if ($user instanceof \App\Models\Admin) {
            return 'Admin';
        } 
        // Pour les TeamUser, utiliser le champ role directement
        elseif ($user instanceof \App\Models\TeamUser) {
            return $user->role; // Retourne 'Admin', 'Agent', 'Investigateur'
        }

        return 'unknown';
    }
}