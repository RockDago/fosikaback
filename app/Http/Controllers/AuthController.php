<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Models\Admin;
use App\Models\TeamUser;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
                'remember' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $email = $request->email;
            $password = $request->password;

            \Log::info("Tentative de connexion", ['email' => $email]);

            // 1. Essayer de trouver dans team_users d'abord
            $teamUser = TeamUser::where('email', $email)->first();
            
            if ($teamUser) {
                \Log::info("TeamUser trouvé", [
                    'email' => $teamUser->email, 
                    'statut' => $teamUser->statut,
                    'role' => $teamUser->role
                ]);

                // Vérifier si le compte est actif
                if (!$teamUser->statut) {
                    \Log::warning("Compte team_user désactivé", ['email' => $email]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Votre compte a été désactivé. Contactez l\'administrateur.'
                    ], 403);
                }

                // Vérifier le mot de passe
                if (Hash::check($password, $teamUser->password)) {
                    // Créer le token
                    $token = $teamUser->createToken('team-token')->plainTextToken;

                    $userType = $teamUser->role; // 'agent' ou 'investigator'

                    \Log::info("Connexion team_user réussie", [
                        'email' => $email,
                        'user_type' => $userType
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Connexion réussie',
                        'token' => $token,
                        'user_type' => $userType,
                        'user' => [
                            'id' => $teamUser->id,
                            'name' => $teamUser->nom_complet,
                            'email' => $teamUser->email,
                            'role' => $teamUser->role,
                            'first_name' => $teamUser->prenom,
                            'last_name' => $teamUser->nom,
                            'phone' => $teamUser->telephone,
                            'avatar' => $teamUser->avatar_url,
                            'departement' => $teamUser->departement,
                            'statut' => $teamUser->statut,
                        ]
                    ]);
                } else {
                    \Log::warning("Mot de passe team_user incorrect", ['email' => $email]);
                }
            }

            // 2. Essayer de trouver dans admins
            $admin = Admin::where('email', $email)->first();
            
            if ($admin) {
                \Log::info("Admin trouvé", ['email' => $admin->email]);

                if (Hash::check($password, $admin->password)) {
                    $token = $admin->createToken('admin-token')->plainTextToken;

                    \Log::info("Connexion admin réussie", ['email' => $email]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Connexion admin réussie',
                        'token' => $token,
                        'user_type' => 'admin',
                        'user' => [
                            'id' => $admin->id,
                            'name' => $admin->name,
                            'email' => $admin->email,
                            'role' => 'admin',
                            'first_name' => $admin->first_name,
                            'last_name' => $admin->last_name,
                            'phone' => $admin->phone,
                            'avatar' => $admin->avatar_url,
                        ]
                    ]);
                } else {
                    \Log::warning("Mot de passe admin incorrect", ['email' => $email]);
                }
            }

            // Si aucun utilisateur trouvé ou mauvais mot de passe
            \Log::warning("Échec connexion - identifiants incorrects", ['email' => $email]);
            return response()->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect'
            ], 401);

        } catch (\Exception $e) {
            \Log::error('Erreur login: ' . $e->getMessage(), [
                'email' => $request->email,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user) {
                $request->user()->currentAccessToken()->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur logout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion'
            ], 500);
        }
    }

    public function user(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'authenticated' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }

            // Formater la réponse selon le type d'utilisateur
            if ($user instanceof Admin) {
                return response()->json([
                    'success' => true,
                    'authenticated' => true,
                    'user_type' => 'admin',
                    'data' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'phone' => $user->phone,
                        'avatar' => $user->avatar_url,
                        'full_name' => $user->full_name,
                    ]
                ]);
            } elseif ($user instanceof TeamUser) {
                // Vérifier le statut pour les TeamUser
                if (!$user->statut) {
                    $request->user()->currentAccessToken()->delete();
                    return response()->json([
                        'success' => false,
                        'authenticated' => false,
                        'message' => 'Compte désactivé'
                    ], 403);
                }

                return response()->json([
                    'success' => true,
                    'authenticated' => true,
                    'user_type' => $user->role,
                    'data' => [
                        'id' => $user->id,
                        'name' => $user->nom_complet,
                        'email' => $user->email,
                        'role' => $user->role,
                        'first_name' => $user->prenom,
                        'last_name' => $user->nom,
                        'phone' => $user->telephone,
                        'avatar' => $user->avatar_url,
                        'departement' => $user->departement,
                        'statut' => $user->statut,
                        'full_name' => $user->nom_complet,
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'authenticated' => false,
                'message' => 'Type d\'utilisateur non reconnu'
            ], 401);

        } catch (\Exception $e) {
            \Log::error('Erreur user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données utilisateur'
            ], 500);
        }
    }

    public function checkAuth(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user) {
                // Vérifier le statut pour TeamUser
                if ($user instanceof TeamUser && !$user->statut) {
                    $request->user()->currentAccessToken()->delete();
                    return response()->json([
                        'success' => false,
                        'message' => 'Compte désactivé'
                    ], 403);
                }

                $userType = $user instanceof Admin ? 'admin' : $user->role;

                return response()->json([
                    'success' => true,
                    'message' => 'Authentifié',
                    'user_type' => $userType
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }
        } catch (\Exception $e) {
            \Log::error('Erreur checkAuth: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur de vérification d\'authentification'
            ], 500);
        }
    }

    public function checkAdminAuth(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user instanceof Admin) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'phone' => $user->phone,
                        'avatar' => $user->avatar_url,
                        'full_name' => $user->full_name,
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux administrateurs'
                ], 403);
            }
        } catch (\Exception $e) {
            \Log::error('Erreur checkAdminAuth: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur de vérification admin'
            ], 500);
        }
    }

    public function refreshCsrfToken()
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'CSRF token rafraîchi',
                'csrf_token' => csrf_token()
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur refreshCsrfToken: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du rafraîchissement du token CSRF'
            ], 500);
        }
    }
}