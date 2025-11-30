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
        try {
            $this->authenticate($request, $guards);
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            if ($request->expectsJson()) {
                Log::warning('Authentication failed', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'url' => $request->fullUrl(),
                    'guards' => $guards
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié',
                    'requires_logout' => true,
                    'error_code' => 'UNAUTHENTICATED'
                ], 401);
            }
            
            return $this->unauthenticated($request, $guards);
        }

        // Vérifier si l'utilisateur est un TeamUser et s'il est désactivé
        $user = $request->user();
        if ($user && method_exists($user, 'getTable') && $user->getTable() === 'team_users') {
            if (!$user->statut) {
                Log::warning('Disabled team user attempted access', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip()
                ]);

                // Déconnecter l'utilisateur
                $request->user()->currentAccessToken()->delete();
                $request->session()->invalidate();

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.',
                        'requires_logout' => true,
                        'error_code' => 'ACCOUNT_DISABLED'
                    ], 403);
                }

                return redirect()->route('login')->with('error', 'Votre compte a été désactivé.');
            }
        }

        // Vérifier la session pour tous les utilisateurs
        if ($request->session()) {
            $lastActivity = $request->session()->get('last_activity');
            $sessionLifetime = config('session.lifetime', 120) * 60;

            if ($lastActivity && (time() - $lastActivity > $sessionLifetime)) {
                Log::info('Session expired', [
                    'user_id' => $user?->id,
                    'user_type' => get_class($user),
                    'ip' => $request->ip()
                ]);

                if ($user) {
                    $user->currentAccessToken()->delete();
                }
                $request->session()->invalidate();

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Session expirée',
                        'requires_logout' => true,
                        'error_code' => 'SESSION_EXPIRED'
                    ], 419);
                }

                return redirect()->route('login')->with('error', 'Votre session a expiré.');
            }

            // Mettre à jour le timestamp d'activité
            $request->session()->put('last_activity', time());
        }

        return $next($request);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }

    /**
     * Handle unauthenticated users.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $guards
     * @return void
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function unauthenticated($request, array $guards)
    {
        Log::warning('Unauthenticated access attempt', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'guards' => $guards
        ]);

        throw new \Illuminate\Auth\AuthenticationException(
            'Unauthenticated.', $guards, $this->redirectTo($request)
        );
    }
}