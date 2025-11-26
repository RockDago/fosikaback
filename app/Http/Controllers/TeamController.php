<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TeamUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TeamController extends Controller
{
    /**
     * Récupérer tous les utilisateurs
     */
    public function getAllUsers()
    {
        try {
            $users = TeamUser::all();
            
            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer tous les agents
     */
    public function getAgents()
    {
        try {
            $agents = TeamUser::agents()->select(
                'id', 'nom_complet', 'first_name', 'last_name', 'email', 'telephone', 'adresse', 
                'departement', 'username', 'role', 'statut', 'avatar',
                'responsabilites', 'specialisations', 'created_at', 'updated_at'
            )->get();
            
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
            $investigateurs = TeamUser::investigateurs()->select(
                'id', 'nom_complet', 'first_name', 'last_name', 'email', 'telephone', 'adresse', 
                'departement', 'username', 'role', 'statut', 'avatar',
                'responsabilites', 'specialisations', 'created_at', 'updated_at'
            )->get();
            
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
            
            Log::info('👑 getAdministrateurs - Administrateurs trouvés:', [
                'count' => $administrateurs->count(),
                'data' => $administrateurs->map(fn($a) => ['id' => $a->id, 'nom' => $a->nom_complet, 'role' => $a->role])->toArray()
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $administrateurs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des administrateurs',
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
            // Préparation des données
            $requestData = $request->all();
            Log::info('📝 createUser - Données reçues (RAW):', $requestData);
            
            // ÉTAPE 1: Déterminer le rôle
            $role = null;
            
            // Essayer d'abord 'role' directement
            if (isset($requestData['role']) && !empty($requestData['role'])) {
                $role = $requestData['role'];
                Log::info('✅ Rôle trouvé directement:', ['role' => $role]);
            }
            // Sinon convertir depuis 'role_id'
            elseif (isset($requestData['role_id']) && !empty($requestData['role_id'])) {
                // NOTE: rustine temporaire — la base de données locale contient
                // encore une contrainte CHECK qui n'autorise pas 'Admin'.
                // Pour éviter l'erreur 500 pendant le développement local,
                // on mappe provisoirement role_id=1 vers 'Agent'.
                $roleMap = [
                    1 => 'Agent', // temporaire -> éviter l'envoi de 'Admin' si la DB le refuse
                    2 => 'Agent',
                    3 => 'Investigateur',
                ];
                $roleId = intval($requestData['role_id']);
                $role = $roleMap[$roleId] ?? null;
                Log::warning('⚠️ Rôle converti depuis role_id (rustine):', ['role_id' => $roleId, 'role' => $role]);
            }
            
            // ÉTAPE 2: Vérifier que nous avons un rôle valide
            if (!$role) {
                Log::error('❌ AUCUN RÔLE TROUVÉ!', ['role_id' => $requestData['role_id'] ?? 'NULL', 'role' => $requestData['role'] ?? 'NULL']);
                return response()->json([
                    'success' => false,
                    'message' => 'Le rôle est obligatoire',
                    'errors' => ['role' => ['Veuillez sélectionner un rôle valide']]
                ], 422);
            }
            
            // ÉTAPE 3: Préparer les données pour validation/création
            $requestData['role'] = $role;
            unset($requestData['role_id']);
            
            Log::info('📤 Données avant validation:', ['role' => $requestData['role'], 'email' => $requestData['email'] ?? 'NULL']);

            // ÉTAPE 4: Valider
            $validator = Validator::make($requestData, [
                'nom_complet' => 'required|string|max:255',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'email' => 'required|email|unique:team_users,email',
                'telephone' => 'required|string',
                'adresse' => 'nullable|string',
                'departement' => 'required|string',
                'username' => 'required|string|unique:team_users,username',
                'password' => 'required|string|min:8',
                'password_confirmation' => 'required|same:password',
                'role' => 'required|in:Admin,Agent,Investigateur',
                'responsabilites' => 'nullable|string',
                'specialisations' => 'nullable|array',
                'statut' => 'boolean'
            ]);

            if ($validator->fails()) {
                Log::error('❌ Validation échouée:', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // ÉTAPE 5: Préparer les données utilisateur
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
            
            Log::info('💾 Données FINALES avant create():', [
                'nom_complet' => $userData['nom_complet'],
                'email' => $userData['email'],
                'role' => $userData['role'],
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'keys' => array_keys($userData)
            ]);
            
            // ÉTAPE 6: Créer l'utilisateur
            // Filtrer les champs envoyés vers create() pour éviter d'insérer
            // des colonnes qui n'existent pas encore en base (cause fréquente
            // d'"Undefined column" SQLSTATE[42703]). Ceci est une rustine
            // temporaire : la vraie correction est d'exécuter les migrations
            // qui synchronisent le schéma de la base.
            $allowed = [
                'nom_complet', 'email', 'telephone', 'adresse', 'departement',
                'username', 'password', 'responsabilites', 'specialisations',
                'statut', 'role'
            ];

            $filtered = array_intersect_key($userData, array_flip($allowed));

            Log::warning('⚠️ Filtrage des champs avant create() (rustine temporaire):', [
                'allowed' => $allowed,
                'provided_keys' => array_keys($userData),
                'filtered_keys' => array_keys($filtered)
            ]);

            $user = TeamUser::create($filtered);
            
            Log::info('✅ Utilisateur créé avec succès:', [
                'id' => $user->id,
                'nom_complet' => $user->nom_complet,
                'role' => $user->role,
                'email' => $user->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            Log::error('💥 ERREUR CRÉATION UTILISATEUR:', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
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

            // Préparation des données
            $requestData = $request->all();
            
            // Priorité: utiliser 'role' si présent, sinon convertir 'role_id'
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
            
            // Nettoyer role_id (non utilisé en BD)
            unset($requestData['role_id']);

            $validator = Validator::make($requestData, [
                'nom_complet' => 'required|string|max:255',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'email' => 'required|email|unique:team_users,email,' . $id,
                'telephone' => 'required|string',
                'adresse' => 'nullable|string',
                'departement' => 'required|string',
                'username' => 'required|string|unique:team_users,username,' . $id,
                'role' => 'required|in:Admin,Agent,Investigateur',
                'responsabilites' => 'nullable|string',
                'specialisations' => 'nullable|array',
                'statut' => 'boolean'
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
     * Récupérer les statistiques de l'équipe
     */
    public function getStats()
    {
        try {
            $totalUsers = TeamUser::count();
            $totalAgents = TeamUser::agents()->count();
            $totalInvestigateurs = TeamUser::investigateurs()->count();
            $totalAdministrateurs = TeamUser::where('role', 'Admin')->count();
            $totalActifs = TeamUser::actifs()->count();

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
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
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
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des rôles',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}