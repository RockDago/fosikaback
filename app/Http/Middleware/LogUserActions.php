<?php
namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;

class LogUserActions
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($this->shouldSkip($request)) {
            return $response;
        }

        $user = $request->user();

        if ($user) {

            AuditLogger::logSystemAction(
                $user->email,
                $this->getActionFromRequest($request),
                $this->getEntityFromRoute($request) ?? 'Système',
                $this->getStatusFromResponse($response),
                $this->getActionDetails($request, $response),
                $this->getReferenceFromRequest($request)
            );
        } elseif ($request->routeIs('auth.login') || str_contains($request->path(), 'login')) {
            AuditLogger::logConnexion(
                $request->input('email', 'unknown@example.com'),
                $response->getStatusCode() === 200 ? 'Succès' : 'Échec',
                $this->getActionDetails($request, $response)
            );
        }

        return $response;
    }

    private function getActionFromRequest(Request $request): string
    {
        if ($request->routeIs('auth.login')) {
            return 'Connexion';
        }

        if ($request->routeIs('auth.logout')) {
            return 'Déconnexion';
        }

        if (str_contains($request->path(), 'toggle-status') || preg_match('#/(status)$#', $request->path())) {
            return 'Modification de statut';
        }

        if (str_contains($request->path(), 'export')) {
            return 'Export';
        }

        return match ($request->method()) {
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
        if (!$route) {
            return null;
        }

        $uri = $route->uri();
        $path = $request->path();

        if (str_contains($uri, 'reports') || str_contains($path, 'reports')) {
            return 'Signalements';
        }

        if (str_contains($uri, 'notifications') || str_contains($path, 'notifications')) {
            return 'Notifications';
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

        if (str_contains($uri, 'enseignants') || str_contains($path, 'enseignants')) {
            return 'Enseignants';
        }

        if (str_contains($uri, 'universites') || str_contains($path, 'universites')) {
            return 'Universités';
        }

        if (str_contains($uri, 'etablissements') || str_contains($path, 'etablissements')) {
            return 'Établissements';
        }

        if (str_contains($uri, 'admin') || str_contains($path, 'admin')) {
            return 'Administration';
        }

        return 'Système';
    }

    private function getStatusFromResponse($response): string
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return 'Succès';
        }

        if ($statusCode === 401 || $statusCode === 403) {
            return 'Refusé';
        }

        if ($statusCode >= 400 && $statusCode < 500) {
            return 'Échec';
        }

        return 'Erreur système';
    }

    private function getActionDetails(Request $request, $response): string
    {
        $details = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $response->getStatusCode(),
        ];

        $params = $request->except([
            'password',
            'password_confirmation',
            'current_password',
            'token',
            '_token',
            'code',
            'recovery_code',
            'email',
            'phone',
            'telephone',
            'address',
            'description',
            'content',
            'file',
            'files',
        ]);
        if (!empty($params)) {
            $details['params'] = $params;
        }

        if ($request->user()) {
            $details['user_id'] = $request->user()->id;
        }

        return json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function getReferenceFromRequest(Request $request): ?string
    {
        return $request->route('id')
            ?? $request->route('reference')
            ?? $request->route('report')
            ?? $request->input('reference')
            ?? $request->input('report_id')
            ?? null;
    }

    private function shouldSkip(Request $request): bool
    {
        $path = trim($request->path(), '/');

        return str_starts_with($path, 'api/audit')
            || str_starts_with($path, 'api/admin/audit')
            || str_contains($path, '/audit/');
    }
}
