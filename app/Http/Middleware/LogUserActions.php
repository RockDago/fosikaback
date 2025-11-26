<?php
// app/Http/Middleware/LogUserActions.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AuditLogger;

class LogUserActions
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Logger les actions pour les utilisateurs authentifiés
        if (auth()->check()) {
            $user = auth()->user();
            $action = $this->getActionFromRequest($request);
            $entite = $this->getEntityFromRoute($request);
            
            if ($action && $entite) {
                AuditLogger::logSystemAction(
                    $user->email,
                    $action,
                    $entite,
                    $response->getStatusCode() === 200 ? 'Succès' : 'Échec',
                    $this->getActionDetails($request)
                );
            }
        }

        return $response;
    }

    private function getActionFromRequest(Request $request): ?string
    {
        return match($request->method()) {
            'GET' => 'Consultation',
            'POST' => 'Création',
            'PUT', 'PATCH' => 'Modification',
            'DELETE' => 'Suppression',
            default => null,
        };
    }

    private function getEntityFromRoute(Request $request): ?string
    {
        $route = $request->route();
        if (!$route) return null;

        $uri = $route->uri();
        
        if (str_contains($uri, 'reports')) return 'Signalements';
        if (str_contains($uri, 'notifications')) return 'Notifications';
        if (str_contains($uri, 'journal-audit')) return 'Journal d\'audit';
        if (str_contains($uri, 'admin')) return 'Administration';
        
        return 'Système';
    }

    private function getActionDetails(Request $request): string
    {
        return "Action sur: " . $request->getPathInfo();
    }
}