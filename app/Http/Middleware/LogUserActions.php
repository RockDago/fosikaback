<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;

class LogUserActions
{
    public function handle(Request $request, Closure $next)
    {
        // On laisse la requête passer d'abord
        $response = $next($request);

        // Logger les actions pour les utilisateurs authentifiés
        if (Auth::check()) {
            $user = Auth::user();
            $action = $this->getActionFromRequest($request);
            $entite = $this->getEntityFromRoute($request);
            $statut = $this->getStatusFromResponse($response);

            // Toujours logger, même si l'entité est null
            AuditLogger::logSystemAction(
                $user->email,
                $action,
                $entite ?? 'Système',
                $statut,
                $this->getActionDetails($request, $response),
                $this->getReferenceFromRequest($request)
            );
        } else {
            // Log des actions non authentifiées (échecs de login, etc.)
            if ($request->routeIs('auth.login') || str_contains($request->path(), 'login')) {
                $email = $request->input('email', 'unknown@example.com');
                $statut = $response->getStatusCode() === 200 ? 'Succès' : 'Échec';

                AuditLogger::logConnexion(
                    $email,
                    $statut,
                    $this->getActionDetails($request, $response)
                );
            }
        }

        return $response;
    }

    private function getActionFromRequest(Request $request): string
    {
        // Déterminer l'action basée sur la méthode HTTP et la route
        if ($request->routeIs('auth.login')) return 'Connexion';
        if ($request->routeIs('auth.logout')) return 'Déconnexion';
        if (str_contains($request->path(), 'export')) return 'Export';

        return match($request->method()) {
            'GET' => 'Consultation',
            'POST' => 'Création',
            'PUT', 'PATCH' => 'Modification',
            'DELETE' => 'Suppression',
            default => 'Action système',
        };
    }

    private function getEntityFromRoute(Request $request): ?string
    {
        $route = $request->route();
        if (!$route) return null;

        $uri = $route->uri();
        $path = $request->path();

        // Priorité aux routes spécifiques
        if (str_contains($uri, 'reports') || str_contains($path, 'reports')) {
            return 'Signalements';
        }
        if (str_contains($uri, 'notifications') || str_contains($path, 'notifications')) {
            return 'Notifications';
        }
        if (str_contains($uri, 'audit/journal') || str_contains($path, 'audit/journal')) {
            return 'Journal d\'audit';
        }
        if (str_contains($uri, 'admin') || str_contains($path, 'admin')) {
            return 'Administration';
        }
        if (str_contains($uri, 'users') || str_contains($path, 'users')) {
            return 'Utilisateurs';
        }
        if (str_contains($uri, 'profile') || str_contains($path, 'profile')) {
            return 'Profil';
        }
        if (str_contains($uri, 'auth') || str_contains($path, 'auth')) {
            return 'Authentification';
        }
        if (str_contains($uri, 'files') || str_contains($path, 'files')) {
            return 'Fichiers';
        }

        return 'Système';
    }

    private function getStatusFromResponse($response): string
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return 'Succès';
        } elseif ($statusCode === 401 || $statusCode === 403) {
            return 'Refusé';
        } elseif ($statusCode >= 400 && $statusCode < 500) {
            return 'Échec';
        } else {
            return 'Erreur système';
        }
    }

    private function getActionDetails(Request $request, $response): string
    {
        $details = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $response->getStatusCode(),
        ];

        // Ajouter des paramètres sensibles (sans mot de passe)
        $params = $request->except(['password', 'password_confirmation', 'token', '_token']);
        if (!empty($params)) {
            $details['params'] = $params;
        }

        // Ajouter l'ID utilisateur si authentifié
        if (Auth::check()) {
            $details['user_id'] = Auth::id();
        }

        return json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function getReferenceFromRequest(Request $request): ?string
    {
        // Extrait une référence si présente
        return $request->route('id')
            ?? $request->route('reference')
            ?? $request->route('report')
            ?? $request->input('reference')
            ?? $request->input('report_id')
            ?? null;
    }
}
