<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Closure;
use Illuminate\Support\Facades\Log;

class Authenticate extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$guards
     * @return mixed
     */
    public function handle($request, Closure $next, ...$guards)
    {
        // 1. On appelle d'abord la logique d'authentification native de Laravel/Sanctum
        // C'est cette méthode qui va vérifier le token Bearer et charger l'utilisateur.
        $this->authenticate($request, $guards);

        // 2. Si on arrive ici, c'est que l'authentification a réussi (sinon une exception est levée)

        Log::debug('Authenticate Middleware - Success', [
            'user_id' => $request->user()?->id,
            'email' => $request->user()?->email,
        ]);

        return $next($request);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     * (Non utilisé en mode API JSON, mais requis par la classe parent)
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }

    /**
     * Gérer une authentification échouée (token invalide ou manquant)
     * Cette méthode est appelée automatiquement par $this->authenticate() en cas d'échec.
     */
    protected function unauthenticated($request, array $guards)
    {
        Log::warning('Authenticate Middleware - Auth Failed', [
            'url' => $request->fullUrl(),
            'has_header' => $request->hasHeader('Authorization'),
        ]);

        // On force une réponse JSON immédiate au lieu de la redirection
        abort(response()->json([
            'success' => false,
            'message' => 'Token invalide ou non authentifié',
            'requires_logout' => true,
            'error_code' => 'UNAUTHENTICATED',
        ], 401));
    }
}
