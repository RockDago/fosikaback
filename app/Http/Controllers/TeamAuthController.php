<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TeamUser;
use App\Models\Role;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TeamAuthController extends Controller
{
    /**
     * Authentifier un utilisateur team
     */
    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string'
            ]);

            // Vérifier d'abord si l'utilisateur existe
            $user = TeamUser::where('email', $credentials['email'])->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email ou mot de passe incorrect'
                ], 401);
            }

            // Vérifier si le compte est désactivé AVANT la vérification du mot de passe
            if (!$user->statut) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.'
                ], 403);
            }

            // Tenter l'authentification
            if (Auth::guard('web')->attempt($credentials)) {
                $user = Auth::guard('web')->user();
                
                // Vérifier à nouveau le statut après authentification (double sécurité)
                if (!$user->statut) {
                    Auth::guard('web')->logout();
                    return response()->json([
                        'success' => false,
                        'message' => 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.'
                    ], 403);
                }
                
                // Créer un token Sanctum
                $token = $user->createToken('team-token')->plainTextToken;
                
                return response()->json([
                    'success' => true,
                    'message' => 'Connexion réussie',
                    'data' => [
                        'token' => $token,
                        'user' => $user
                    ]
                ]);
            }

            // Si l'authentification échoue, vérifier si c'est à cause du mot de passe
            // Mais d'abord re-vérifier que l'utilisateur existe toujours
            $user = TeamUser::where('email', $credentials['email'])->first();
            if ($user && !$user->statut) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.'
                ], 403);
            }

            return response()->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect'
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ... (le reste des méthodes reste inchangé)
    
    /**
     * Vérifier l'authentification et le statut de l'utilisateur
     */
    public function checkAuth(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }

            // Vérifier si l'utilisateur est désactivé
            if (!$user->statut) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.'
                ], 403);
            }

            $user->load('role');
            
            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification de l\'authentification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer le profil de l'utilisateur connecté (sans vérification de statut)
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }

            $user->load('role');
            
            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Déconnexion de l'utilisateur
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            if ($user) {
                $user->currentAccessToken()->delete();
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer l'utilisateur connecté (alias de me)
     */
    public function user(Request $request)
    {
        return $this->me($request);
    }

    /**
     * Récupérer tous les agents
     */
    public function getAgents()
    {
        try {
            $agents = TeamUser::with('role')->agents()->get();
            
            return response()->json([
                'success' => true,
                'data' => $agents
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des agents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer tous les investigateurs
     */
    public function getInvestigateurs()
    {
        try {
            $investigateurs = TeamUser::with('role')->investigateurs()->get();
            
            return response()->json([
                'success' => true,
                'data' => $investigateurs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des investigateurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer tous les rôles
     */
    public function getRoles()
    {
        try {
            $roles = Role::all();
            
            return response()->json([
                'success' => true,
                'data' => $roles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des rôles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un nouvel utilisateur
     */
    public function createUser(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nom_complet' => 'required|string|max:255',
                'email' => 'required|email|unique:team_users,email',
                'telephone' => 'required|string',
                'adresse' => 'nullable|string',
                'departement' => 'required|string',
                'username' => 'required|string|unique:team_users,username',
                'password' => 'required|string|min:8',
                'password_confirmation' => 'required|same:password',
                'role_id' => 'required|exists:roles,id',
                'statut' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userData = $request->all();
            $userData['password'] = Hash::make($request->password);
            
            // Nettoyer les données
            unset($userData['password_confirmation']);
            
            $user = TeamUser::create($userData);
            $user->load('role');
            
            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un utilisateur
     */
    public function updateUser(Request $request, $id)
    {
        try {
            $user = TeamUser::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'nom_complet' => 'required|string|max:255',
                'email' => 'required|email|unique:team_users,email,' . $id,
                'telephone' => 'required|string',
                'adresse' => 'nullable|string',
                'departement' => 'required|string',
                'username' => 'required|string|unique:team_users,username,' . $id,
                'role_id' => 'required|exists:roles,id',
                'statut' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->update($request->all());
            $user->load('role');
            
            return response()->json([
                'success' => true,
                'message' => 'Utilisateur modifié avec succès',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un utilisateur
     */
    public function deleteUser($id)
    {
        try {
            $user = TeamUser::findOrFail($id);
            $user->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Utilisateur supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activer/Désactiver un utilisateur
     */
    public function toggleStatus($id)
    {
        try {
            $user = TeamUser::findOrFail($id);
            $user->statut = !$user->statut;
            $user->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Statut modifié avec succès',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Réinitialiser le mot de passe
     */
    public function resetPassword(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = TeamUser::findOrFail($id);
            $user->password = Hash::make($request->password);
            $user->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation du mot de passe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les statistiques
     */
    public function getStats()
    {
        try {
            $totalUsers = TeamUser::count();
            $activeUsers = TeamUser::where('statut', true)->count();
            $agents = TeamUser::agents()->count();
            $investigateurs = TeamUser::investigateurs()->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers,
                    'agents' => $agents,
                    'investigateurs' => $investigateurs
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}