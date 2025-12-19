<?php
// app/Http/Middleware/AdminMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || !in_array(strtolower($user->role), ['admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Droits administrateur requis.'
            ], 403);
        }

        return $next($request);
    }
}
