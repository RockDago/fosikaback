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
    /**
     * Connexion d'un administrateur
     */
    public function login(Request $request)
    {
        try {
            // Validation
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Vérifier les identifiants
            $admin = Admin::where('email', $request->email)->first();

            if (!$admin || !Hash::check($request->password, $admin->password)) {
                Log::warning('Failed login attempt', [
                    'email' => $request->email,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Identifiants incorrects'
                ], 401);
            }

            // CORRECTION: Invalider toutes les sessions existantes
            $this->invalidateAllSessions($admin);

            // CORRECTION: Générer un nouvel ID de session
            $sessionId = $admin->generateSessionId();

            // CORRECTION: Créer le token AVANT de chercher l'accessToken
            $tokenResult = $admin->createToken('admin-token', ['*'], now()->addHours(24));
            $token = $tokenResult->plainTextToken;

            // CORRECTION: Récupérer l'accessToken depuis l'objet NewAccessToken
            $accessToken = $tokenResult->accessToken;

            // CORRECTION: Stocker l'ID de session dans le token
            if ($accessToken) {
                $accessToken->forceFill([
                    'session_id' => $sessionId,
                ])->save();

                Log::info('Session ID saved to token', [
                    'admin_id' => $admin->id,
                    'session_id' => $sessionId,
                    'token_id' => $accessToken->id
                ]);
            }

            Log::info('Admin logged in successfully', [
                'admin_id' => $admin->id,
                'session_id' => $sessionId,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'token' => $token,
                'session_id' => $sessionId,
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'first_name' => $admin->first_name,
                    'last_name' => $admin->last_name,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Login error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $request->email ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Déconnexion d'un administrateur
     */
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
                    'admin_id' => $admin->id
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ]);

        } catch (\Exception $e) {
            Log::error('Logout error', [
                'error' => $e->getMessage(),
                'admin_id' => $request->user()->id ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion'
            ], 500);
        }
    }

    /**
     * Récupérer les informations de l'administrateur connecté
     */
    public function user(Request $request)
    {
        try {
            $admin = $request->user();
            $token = $request->bearerToken();

            if (!$this->isValidSession($admin, $token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session invalide'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'first_name' => $admin->first_name,
                    'last_name' => $admin->last_name,
                    'phone' => $admin->phone,
                    'avatar' => $admin->avatar_url,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('User fetch error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données utilisateur'
            ], 500);
        }
    }

    /**
     * Vérifier l'authentification
     */
    public function checkAuth(Request $request)
    {
        try {
            $admin = $request->user();
            $token = $request->bearerToken();

            if (!$admin || !$this->isValidSession($admin, $token)) {
                return response()->json([
                    'success' => false,
                    'authenticated' => false
                ], 401);
            }

            return response()->json([
                'success' => true,
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
                'success' => false,
                'authenticated' => false
            ], 401);
        }
    }

    /**
     * Vérifier si la session est valide
     */
    private function isValidSession($admin, $token)
    {
        if (!$admin || !$token) {
            return false;
        }

        $accessToken = PersonalAccessToken::findToken($token);
        
        // CORRECTION: Rendre la vérification session_id optionnelle
        if (!$accessToken) {
            return false;
        }

        // Si session_id existe, vérifier sa validité
        if ($accessToken->session_id) {
            return $admin->isValidSession($accessToken->session_id);
        }

        // Si pas de session_id, considérer comme valide (pour rétrocompatibilité)
        return true;
    }

    /**
     * Invalider toutes les sessions existantes
     */
    private function invalidateAllSessions(Admin $admin)
    {
        // Invalider l'ancienne session
        $admin->invalidateSession();

        // Supprimer tous les tokens existants
        $admin->tokens()->delete();

        Log::info('All sessions invalidated for admin', [
            'admin_id' => $admin->id
        ]);
    }
}
