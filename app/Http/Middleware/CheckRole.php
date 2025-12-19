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

        // ✅ CORRECTION : Maintenant nous avons un seul modèle User
        // Vérifier que l'utilisateur est bien une instance du modèle User
        if (!$user instanceof \App\Models\User) {
            Log::error('Role check failed: invalid user model', [
                'user_class' => get_class($user),
                'required_roles' => $roles,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Type d\'utilisateur invalide',
                'requires_logout' => true,
                'error_code' => 'INVALID_USER_TYPE'
            ], 401);
        }

        // Vérifier si le compte est actif (champ 'statut')
        if (!$user->statut) {
            Log::warning('Role check failed: disabled account', [
                'user_id' => $user->id,
                'email' => $user->email,
                'user_role' => $user->role,
                'ip' => $request->ip()
            ]);

            // Déconnecter l'utilisateur
            Auth::logout();
            $request->session()->invalidate();

            return response()->json([
                'success' => false,
                'message' => 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.',
                'requires_logout' => true,
                'error_code' => 'ACCOUNT_DISABLED'
            ], 403);
        }

        // Normaliser les rôles pour la comparaison
        $userRole = strtolower($user->role);
        $requiredRoles = array_map('strtolower', $roles);

        // Vérifier si l'utilisateur a un des rôles requis
        if (!in_array($userRole, $requiredRoles)) {
            Log::warning('Role check failed: insufficient permissions', [
                'user_id' => $user->id,
                'user_role' => $userRole,
                'user_formatted_role' => $user->formatted_role,
                'required_roles' => $requiredRoles,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé pour votre rôle',
                'user_role' => $userRole,
                'required_roles' => $requiredRoles,
                'error_code' => 'INSUFFICIENT_PERMISSIONS'
            ], 403);
        }

        // Ajouter des informations sur l'utilisateur à la requête pour un accès facile
        $request->attributes->set('authenticated_user', $user);
        $request->attributes->set('user_role', $userRole);

        return $next($request);
    }
}
