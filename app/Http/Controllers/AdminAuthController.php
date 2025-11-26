<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Log;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $admin = Admin::where('email', $request->email)->first();

            if (!$admin || !Hash::check($request->password, $admin->password)) {
                Log::warning('Failed admin login attempt', [
                    'email' => $request->email,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'message' => 'Identifiants incorrects'
                ], 401);
            }

            // Générer un nouvel ID de session
            $sessionId = $admin->generateSessionId();

            // Créer le token
            $tokenResult = $admin->createToken('admin-token', ['*'], now()->addHours(24));
            $token = $tokenResult->plainTextToken;

            // Stocker l'ID de session dans le token
            $accessToken = $tokenResult->accessToken;
            if ($accessToken) {
                $accessToken->forceFill([
                    'session_id' => $sessionId,
                ])->save();
            }

            Log::info('Admin logged in successfully', [
                'admin_id' => $admin->id,
                'session_id' => $sessionId,
                'ip' => $request->ip()
            ]);

            // Réponse JSON directe sans wrapper "success"
            return response()->json([
                'token' => $token,
                'session_id' => $sessionId,
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'first_name' => $admin->first_name,
                    'last_name' => $admin->last_name,
                    'full_name' => $admin->full_name,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Login error', [
                'error' => $e->getMessage(),
                'email' => $request->email ?? 'unknown'
            ]);

            return response()->json([
                'message' => 'Erreur lors de la connexion'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $admin = $request->user();

            if ($admin) {
                // Invalider la session
                $admin->invalidateSession();
                
                // Supprimer le token actuel
                $request->user()->currentAccessToken()->delete();

                Log::info('Admin logged out successfully', [
                    'admin_id' => $admin->id ?? 'unknown'
                ]);
            }

            return response()->json([
                'message' => 'Déconnexion réussie'
            ]);

        } catch (\Exception $e) {
            Log::error('Logout error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Erreur lors de la déconnexion'
            ], 500);
        }
    }

    public function user(Request $request)
    {
        try {
            $admin = $request->user();
            $token = $request->bearerToken();

            if (!$this->isValidSession($admin, $token)) {
                return response()->json([
                    'message' => 'Session invalide'
                ], 401);
            }

            return response()->json([
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'first_name' => $admin->first_name,
                'last_name' => $admin->last_name,
                'phone' => $admin->phone,
                'avatar' => $admin->avatar_url,
                'full_name' => $admin->full_name,
            ]);

        } catch (\Exception $e) {
            Log::error('User fetch error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Erreur lors de la récupération des données'
            ], 500);
        }
    }

    public function checkAuth(Request $request)
    {
        try {
            $admin = $request->user();
            $token = $request->bearerToken();

            if (!$admin || !$this->isValidSession($admin, $token)) {
                return response()->json([
                    'authenticated' => false
                ], 401);
            }

            return response()->json([
                'authenticated' => true,
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Check auth error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'authenticated' => false
            ], 401);
        }
    }

    private function isValidSession($admin, $token)
    {
        if (!$admin || !$token) {
            return false;
        }

        $accessToken = PersonalAccessToken::findToken($token);
        
        if (!$accessToken) {
            return false;
        }

        // Vérifier si le token appartient à cet admin
        if ($accessToken->tokenable_id !== $admin->id || $accessToken->tokenable_type !== get_class($admin)) {
            return false;
        }

        // Vérifier la session ID si elle existe
        if ($accessToken->session_id) {
            return $admin->isValidSession($accessToken->session_id);
        }

        return true;
    }
}