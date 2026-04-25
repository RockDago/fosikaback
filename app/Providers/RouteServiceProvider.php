<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * ✅ CORRECTION: Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        // ✅ Limite générale pour l'API (augmentée de 60 à 300 requêtes/minute)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(300)  // ✅ Augmenté de 60 à 300
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Trop de requêtes. Veuillez patienter quelques instants.'
                    ], 429, $headers);
                });
        });

        // ✅ NOUVEAU: Limite spécifique pour les routes de chat (500 requêtes/minute)
        RateLimiter::for('chat', function (Request $request) {
            return Limit::perMinute(500)  // Plus élevé pour le chat temps réel
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Trop de requêtes sur le chat. Veuillez ralentir.'
                    ], 429, $headers);
                });
        });

        // ✅ NOUVEAU: Limite pour les routes publiques (200 requêtes/minute)
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(200)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Trop de tentatives. Réessayez dans quelques instants.'
                    ], 429, $headers);
                });
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by(strtolower((string) $request->input('login')) . '|' . $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Trop de tentatives de connexion. Reessayez plus tard.'
                    ], 429, $headers);
                });
        });

        RateLimiter::for('twofactor', function (Request $request) {
            return Limit::perMinute(6)
                ->by(($request->user()?->id ?: $request->ip()) . '|2fa')
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Trop de tentatives 2FA. Reessayez plus tard.'
                    ], 429, $headers);
                });
        });
    }
}
