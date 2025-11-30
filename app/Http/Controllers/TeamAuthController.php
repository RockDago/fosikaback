<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TeamUser;
use App\Models\Role;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TeamAuthController extends Controller
{
    /**
     * Authentifier un utilisateur team - VERSION CORRIGÉE
     */
    public function login(Request $request)
    {
        try {
            Log::info('🔐 TEAM LOGIN ATTEMPT', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Validation des données
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string|min:1',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ TEAM LOGIN VALIDATION FAILED', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Données de connexion invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            Log::info('🔍 SEARCHING TEAM USER', ['email' => $request->email]);

            // Rechercher l'utilisateur
            $user = TeamUser::where('email', $request->email)->first();

            if (!$user) {
                Log::warning('❌ TEAM USER NOT FOUND', ['email' => $request->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'Email ou mot de passe incorrect'
                ], 401);
            }

            Log::info('👤 TEAM USER FOUND', [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'statut' => $user->statut
            ]);

            // Vérifier le statut du compte - CORRECTION: utilisation de la bonne colonne
            if ($user->statut != 1 && $user->statut != true) {
                Log::warning('🚫 TEAM ACCOUNT DISABLED', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'statut' => $user->statut
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte a été désactivé'
                ], 403);
            }

            // Vérifier le mot de passe
            Log::info('🔑 CHECKING PASSWORD', [
                'provided_password_length' => strlen($request->password),
                'hashed_password_in_db' => $user->password ? 'yes' : 'no'
            ]);

            if (!Hash::check($request->password, $user->password)) {
                Log::warning('❌ TEAM PASSWORD MISMATCH', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Email ou mot de passe incorrect'
                ], 401);
            }

            // Créer le token Sanctum
            Log::info('🎫 CREATING SANCTUM TOKEN');
            $token = $user->createToken('team-auth-token')->plainTextToken;

            Log::info('✅ TEAM LOGIN SUCCESS', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'token_preview' => substr($token, 0, 20) . '...'
            ]);

            // Retourner la réponse
            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'user' => $this->formatUserData($user)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('💥 TEAM LOGIN EXCEPTION', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur interne du serveur lors de la connexion',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            
            Log::info('🚪 TEAM LOGOUT', [
                'user_id' => $user->id ?? 'unknown',
                'email' => $user->email ?? 'unknown'
            ]);

            // Supprimer le token courant
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ]);

        } catch (\Exception $e) {
            Log::error('💥 TEAM LOGOUT ERROR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Récupérer l'utilisateur connecté
     */
    public function user(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || !$user instanceof TeamUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $this->formatUserData($user)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('💥 TEAM USER FETCH ERROR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données utilisateur',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Vérifier l'authentification
     */
    public function checkAuth(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || !$user instanceof TeamUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'authenticated' => true,
                    'user' => [
                        'id' => $user->id,
                        'nom_complet' => $user->nom_complet,
                        'email' => $user->email,
                        'role' => $user->role
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('💥 CHECK AUTH ERROR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur de vérification d\'authentification',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Récupérer le profil de l'utilisateur connecté
     */
    public function me(Request $request)
    {
        return $this->user($request);
    }

    /**
     * Récupérer tous les agents
     */
    public function getAgents()
    {
        try {
            $agents = TeamUser::where('role', 'Agent')->select(
                'id', 'nom_complet', 'first_name', 'last_name', 'email', 'telephone', 'adresse', 
                'departement', 'username', 'role', 'statut', 'avatar',
                'responsabilites', 'specialisations', 'created_at', 'updated_at'
            )->get();
            
            return response()->json([
                'success' => true,
                'data' => $agents
            ]);
        } catch (\Exception $e) {
            Log::error('💥 GET AGENTS ERROR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des agents',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Récupérer tous les investigateurs
     */
    public function getInvestigateurs()
    {
        try {
            $investigateurs = TeamUser::where('role', 'Investigateur')->select(
                'id', 'nom_complet', 'first_name', 'last_name', 'email', 'telephone', 'adresse', 
                'departement', 'username', 'role', 'statut', 'avatar',
                'responsabilites', 'specialisations', 'created_at', 'updated_at'
            )->get();
            
            return response()->json([
                'success' => true,
                'data' => $investigateurs
            ]);
        } catch (\Exception $e) {
            Log::error('💥 GET INVESTIGATEURS ERROR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des investigateurs',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Récupérer tous les administrateurs
     */
    public function getAdministrateurs()
    {
        try {
            $administrateurs = TeamUser::where('role', 'Admin')->select(
                'id', 'nom_complet', 'first_name', 'last_name', 'email', 'telephone', 'adresse', 
                'departement', 'username', 'role', 'statut', 'avatar',
                'responsabilites', 'specialisations', 'created_at', 'updated_at'
            )->get();
            
            Log::info('👑 GET ADMINISTRATEURS - Administrateurs trouvés:', [
                'count' => $administrateurs->count()
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $administrateurs
            ]);
        } catch (\Exception $e) {
            Log::error('💥 GET ADMINISTRATEURS ERROR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des administrateurs',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Récupérer tous les utilisateurs
     */
    public function getAllUsers()
    {
        try {
            $users = TeamUser::select(
                'id', 'nom_complet', 'first_name', 'last_name', 'email', 'telephone', 'adresse', 
                'departement', 'username', 'role', 'statut', 'avatar',
                'responsabilites', 'specialisations', 'created_at', 'updated_at'
            )->get();
            
            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            Log::error('💥 GET ALL USERS ERROR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Récupérer les rôles disponibles
     */
    public function getRoles()
    {
        try {
            $roles = [
                ['id' => 1, 'name' => 'Admin', 'code' => 'Admin', 'description' => 'Administrateur système'],
                ['id' => 2, 'name' => 'Agent', 'code' => 'Agent', 'description' => 'Agent de suivi'],
                ['id' => 3, 'name' => 'Investigateur', 'code' => 'Investigateur', 'description' => 'Investigateur terrain'],
            ];
            
            return response()->json([
                'success' => true,
                'data' => $roles
            ]);
        } catch (\Exception $e) {
            Log::error('💥 GET ROLES ERROR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des rôles',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Créer un nouvel utilisateur
     */
    public function createUser(Request $request)
    {
        try {
            // Préparation des données
            $requestData = $request->all();
            Log::info('📝 CREATE USER - Données reçues:', $requestData);
            
            // Déterminer le rôle
            $role = null;
            
            if (isset($requestData['role']) && !empty($requestData['role'])) {
                $role = $requestData['role'];
            } elseif (isset($requestData['role_id']) && !empty($requestData['role_id'])) {
                $roleMap = [
                    1 => 'Admin',
                    2 => 'Agent',
                    3 => 'Investigateur',
                ];
                $roleId = intval($requestData['role_id']);
                $role = $roleMap[$roleId] ?? null;
            }
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le rôle est obligatoire'
                ], 422);
            }
            
            $requestData['role'] = $role;
            unset($requestData['role_id']);

            // Validation
            $validator = Validator::make($requestData, [
                'nom_complet' => 'required|string|max:255',
                'email' => 'required|email|unique:team_users,email',
                'telephone' => 'required|string|max:20',
                'adresse' => 'nullable|string|max:500',
                'departement' => 'required|string|max:255',
                'username' => 'required|string|unique:team_users,username',
                'password' => 'required|string|min:8',
                'password_confirmation' => 'required|same:password',
                'role' => 'required|in:Admin,Agent,Investigateur',
                'responsabilites' => 'nullable|string',
                'specialisations' => 'nullable|string',
                'statut' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                Log::error('❌ CREATE USER VALIDATION FAILED', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Préparer les données utilisateur
            $userData = $requestData;
            $userData['password'] = Hash::make($requestData['password']);
            unset($userData['password_confirmation']);
            
            // Assurer que les champs first_name et last_name sont présents
            if (!isset($userData['first_name'])) {
                $userData['first_name'] = null;
            }
            if (!isset($userData['last_name'])) {
                $userData['last_name'] = null;
            }
            
            // Gérer le statut
            if (!isset($userData['statut'])) {
                $userData['statut'] = true; // Actif par défaut
            }

            // Filtrer les champs autorisés
            $allowed = [
                'nom_complet', 'first_name', 'last_name', 'email', 'telephone', 'adresse', 
                'departement', 'username', 'password', 'role', 'responsabilites', 
                'specialisations', 'statut'
            ];
            $filteredData = array_intersect_key($userData, array_flip($allowed));

            $user = TeamUser::create($filteredData);
            
            Log::info('✅ USER CREATED SUCCESSFULLY', [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès',
                'data' => $this->formatUserData($user)
            ]);

        } catch (\Exception $e) {
            Log::error('💥 CREATE USER EXCEPTION', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'utilisateur',
                'debug' => config('app.debug') ? $e->getMessage() : null
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

            // Préparation des données
            $requestData = $request->all();
            
            // Déterminer le rôle
            if (!isset($requestData['role']) || empty($requestData['role'])) {
                if (isset($requestData['role_id'])) {
                    $roleMap = [
                        1 => 'Admin',
                        2 => 'Agent',
                        3 => 'Investigateur',
                    ];
                    $requestData['role'] = $roleMap[$requestData['role_id']] ?? null;
                }
            }
            
            unset($requestData['role_id']);

            $validator = Validator::make($requestData, [
                'nom_complet' => 'required|string|max:255',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'email' => 'required|email|unique:team_users,email,' . $id,
                'telephone' => 'required|string|max:20',
                'adresse' => 'nullable|string|max:500',
                'departement' => 'required|string|max:255',
                'username' => 'required|string|unique:team_users,username,' . $id,
                'role' => 'required|in:Admin,Agent,Investigateur',
                'responsabilites' => 'nullable|string',
                'specialisations' => 'nullable|string',
                'statut' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->update($requestData);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur modifié avec succès',
                'data' => $this->formatUserData($user)
            ]);

        } catch (\Exception $e) {
            Log::error('💥 UPDATE USER ERROR', [
                'message' => $e->getMessage(),
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification de l\'utilisateur',
                'debug' => config('app.debug') ? $e->getMessage() : null
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
            Log::error('💥 DELETE USER ERROR', [
                'message' => $e->getMessage(),
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'utilisateur',
                'debug' => config('app.debug') ? $e->getMessage() : null
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
                'data' => $this->formatUserData($user)
            ]);

        } catch (\Exception $e) {
            Log::error('💥 TOGGLE STATUS ERROR', [
                'message' => $e->getMessage(),
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut',
                'debug' => config('app.debug') ? $e->getMessage() : null
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
            Log::error('💥 RESET PASSWORD ERROR', [
                'message' => $e->getMessage(),
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation du mot de passe',
                'debug' => config('app.debug') ? $e->getMessage() : null
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
            $totalAgents = TeamUser::where('role', 'Agent')->count();
            $totalInvestigateurs = TeamUser::where('role', 'Investigateur')->count();
            $totalAdministrateurs = TeamUser::where('role', 'Admin')->count();
            $totalActifs = TeamUser::where('statut', true)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_users' => $totalUsers,
                    'total_agents' => $totalAgents,
                    'total_investigateurs' => $totalInvestigateurs,
                    'total_administrateurs' => $totalAdministrateurs,
                    'total_actifs' => $totalActifs,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('💥 GET STATS ERROR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Formater les données utilisateur de manière cohérente
     */
    private function formatUserData(TeamUser $user): array
    {
        return [
            'id' => $user->id,
            'nom_complet' => $user->nom_complet,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'telephone' => $user->telephone,
            'adresse' => $user->adresse,
            'departement' => $user->departement,
            'username' => $user->username,
            'role' => $user->role,
            'statut' => $user->statut,
            'avatar' => $user->avatar,
            'avatar_url' => $user->avatar_url, // Assurez-vous que cette accesseur existe dans le modèle
            'responsabilites' => $user->responsabilites,
            'specialisations' => $user->specialisations,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}