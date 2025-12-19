<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    // =========================================
    // MÉTHODES D'AUTH SUPPRIMÉES
    // (login, logout, checkAuth sont dans UserAuthController)
    // =========================================

    public function getProfile(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous devez être connecté'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatUserData($user)
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération profil:', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du profil'
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous devez être connecté'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'telephone' => 'nullable|string|max:20',
                'adresse' => 'nullable|string|max:500',
                'departement' => 'nullable|string|max:255',
                'responsabilites' => 'nullable|array',
                'specialisations' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = array_filter([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'telephone' => $request->telephone,
                'adresse' => $request->adresse,
                'departement' => $request->departement,
                'responsabilites' => $request->responsabilites,
                'specialisations' => $request->specialisations,
            ], fn($value) => $value !== null);

            $user->update($updateData);
            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'data' => $this->formatUserData($user)
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour profil:', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil'
            ], 500);
        }
    }

    public function updateAvatar(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous devez être connecté'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp,svg|max:5120'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!$request->hasFile('avatar')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun fichier fourni'
                ], 422);
            }

            $file = $request->file('avatar');

            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $fileName = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();

            $folder = match(strtolower($user->role)) {
                'admin' => 'avatars/admins',
                'agent' => 'avatars/agents',
                'investigateur' => 'avatars/investigateurs',
                default => 'avatars/users'
            };

            $path = $file->storeAs($folder, $fileName, 'public');
            $user->avatar = $path;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Avatar mis à jour avec succès',
                'data' => [
                    'avatar_url' => $user->avatar_url,
                    'avatar_path' => $path
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour avatar:', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'avatar'
            ], 500);
        }
    }

    public function updatePassword(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous devez être connecté'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le mot de passe actuel est incorrect'
                ], 422);
            }

            if (Hash::check($request->new_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le nouveau mot de passe doit être différent de l\'actuel'
                ], 422);
            }

            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour mot de passe:', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du mot de passe'
            ], 500);
        }
    }

    public function deleteAvatar(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous devez être connecté'
                ], 401);
            }

            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $user->avatar = null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Avatar supprimé avec succès',
                'data' => ['avatar_url' => null]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur suppression avatar:', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'avatar'
            ], 500);
        }
    }

    public function getAllUsers(Request $request)
    {
        try {
            $users = User::orderBy('created_at', 'desc')->get();

            // Formater les données pour être compatible avec ProfileController
            $formattedUsers = $users->map(function ($user) {
                return $this->formatUserData($user);
            });

            return response()->json([
                'success' => true,
                'data' => $formattedUsers,
                'users' => $formattedUsers,
                'count' => $users->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur getAllUsers:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs: ' . $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        return $this->getAllUsers($request);
    }

    public function createUser(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'username' => 'required|string|unique:users,username',
                'password' => 'required|string|min:8',
                'role' => 'required|in:Admin,Agent,Investigateur,admin,agent,investigateur',
                'departement' => 'required|string',
                'telephone' => 'nullable|string|max:20',
                'adresse' => 'nullable|string|max:500',
                'responsabilites' => 'nullable|array',
                'specialisations' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'telephone' => $request->telephone,
                'departement' => $request->departement,
                'adresse' => $request->adresse,
                'responsabilites' => $request->responsabilites ?? [],
                'specialisations' => $request->specialisations ?? [],
                'statut' => $request->statut ?? true
            ]);

            Log::info('Utilisateur créé avec succès', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès',
                'data' => $this->formatUserData($user)
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur création utilisateur:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        return $this->createUser($request);
    }

    public function updateUser(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'username' => 'sometimes|string|unique:users,username,' . $id,
                'password' => 'nullable|string|min:8',
                'role' => 'sometimes|in:Admin,Agent,Investigateur,admin,agent,investigateur',
                'departement' => 'sometimes|string',
                'telephone' => 'nullable|string|max:20',
                'adresse' => 'nullable|string|max:500',
                'responsabilites' => 'nullable|array',
                'specialisations' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = $request->except(['password', 'password_confirmation']);

            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);
            $user->refresh();

            Log::info('Utilisateur mis à jour', ['user_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur mis à jour avec succès',
                'data' => $this->formatUserData($user)
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur update utilisateur:', [
                'error' => $e->getMessage(),
                'id' => $id,
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        return $this->updateUser($request, $id);
    }

    public function show($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatUserData($user)
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur show user:', ['error' => $e->getMessage(), 'id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération'
            ], 500);
        }
    }

    public function deleteUser(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable'
                ], 404);
            }

            // ✅ Utiliser $request->user() au lieu de Auth::id()
            if ($user->id === $request->user()?->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas supprimer votre propre compte'
                ], 400);
            }

            $user->delete();

            Log::info('Utilisateur supprimé', ['user_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur suppression utilisateur:', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        return $this->deleteUser($request, $id);
    }

    public function toggleStatus($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable'
                ], 404);
            }

            $user->statut = !$user->statut;
            $user->save();

            Log::info('Statut utilisateur modifié', [
                'user_id' => $id,
                'new_status' => $user->statut
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Statut modifié avec succès',
                'data' => [
                    'statut' => $user->statut,
                    'user' => $this->formatUserData($user)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur toggleStatus:', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut'
            ], 500);
        }
    }

    public function changeStatus($id)
    {
        return $this->toggleStatus($id);
    }

    public function getAgents()
    {
        try {
            $agents = User::where('role', 'ILIKE', 'agent')->get();

            return response()->json([
                'success' => true,
                'data' => $agents,
                'count' => $agents->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur getAgents:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des agents'
            ], 500);
        }
    }

    public function getInvestigateurs()
    {
        try {
            $investigateurs = User::where('role', 'ILIKE', 'investigateur')->get();

            return response()->json([
                'success' => true,
                'data' => $investigateurs,
                'count' => $investigateurs->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur getInvestigateurs:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des investigateurs'
            ], 500);
        }
    }

    public function getAdministrateurs()
    {
        try {
            $admins = User::where('role', 'ILIKE', 'admin')->get();

            return response()->json([
                'success' => true,
                'data' => $admins,
                'count' => $admins->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur getAdministrateurs:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des administrateurs'
            ], 500);
        }
    }

    public function getStats()
    {
        try {
            $totalUsers = User::count();
            $activeUsers = User::where('statut', true)->count();
            $agents = User::where('role', 'ILIKE', 'agent')->count();
            $investigateurs = User::where('role', 'ILIKE', 'investigateur')->count();
            $admins = User::where('role', 'ILIKE', 'admin')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $totalUsers,
                    'active' => $activeUsers,
                    'inactive' => $totalUsers - $activeUsers,
                    'agents' => $agents,
                    'investigateurs' => $investigateurs,
                    'admins' => $admins,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur getStats:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques'
            ], 500);
        }
    }

    public function getRoles()
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['id' => 1, 'name' => 'Admin', 'code' => 'admin'],
                ['id' => 2, 'name' => 'Agent', 'code' => 'agent'],
                ['id' => 3, 'name' => 'Investigateur', 'code' => 'investigateur'],
            ]
        ]);
    }

    public function resetPassword(Request $request, $id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'new_password' => 'required|string|min:8'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur resetPassword:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation'
            ], 500);
        }
    }

    public function restoreUser($id)
    {
        try {
            $user = User::withTrashed()->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable'
                ], 404);
            }

            $user->restore();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur restauré avec succès',
                'data' => $this->formatUserData($user)
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur restoreUser:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la restauration'
            ], 500);
        }
    }

    // Nouvelles méthodes pour la gestion des utilisateurs supprimés

    public function trashed()
    {
        try {
            $trashedUsers = User::onlyTrashed()->orderBy('deleted_at', 'desc')->get();

            $formattedUsers = $trashedUsers->map(function ($user) {
                return $this->formatUserData($user);
            });

            return response()->json([
                'success' => true,
                'data' => $formattedUsers,
                'count' => $trashedUsers->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur trashed users:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs supprimés'
            ], 500);
        }
    }

    public function restore($id)
    {
        return $this->restoreUser($id);
    }

    public function getUsersByRole(Request $request)
    {
        try {
            $role = $request->query('role');

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paramètre role manquant'
                ], 400);
            }

            $users = User::where('role', 'ILIKE', $role)
                ->orderBy('created_at', 'desc')
                ->get();

            $formattedUsers = $users->map(function ($user) {
                return $this->formatUserData($user);
            });

            return response()->json([
                'success' => true,
                'data' => $formattedUsers,
                'count' => $users->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur getUsersByRole:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs par rôle'
            ], 500);
        }
    }

    private function formatUserData(User $user): array
    {
        // Convertir les tableaux en chaînes pour être compatible avec ProfileController
        $specialisationsStr = '';
        $responsabilitesStr = '';

        // Gérer spécialisations
        if (is_array($user->specialisations) && count($user->specialisations) > 0) {
            $specialisationsStr = implode(', ', $user->specialisations);
        } else if (is_string($user->specialisations) && !empty($user->specialisations)) {
            $specialisationsStr = $user->specialisations;
        }

        // Gérer responsabilités
        if (is_array($user->responsabilites) && count($user->responsabilites) > 0) {
            $responsabilitesStr = implode(', ', $user->responsabilites);
        } else if (is_string($user->responsabilites) && !empty($user->responsabilites)) {
            $responsabilitesStr = $user->responsabilites;
        }

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'firstname' => $user->first_name, // Ajouté pour compatibilité
            'lastname' => $user->last_name,   // Ajouté pour compatibilité
            'name' => trim($user->first_name . ' ' . $user->last_name),
            'email' => $user->email,
            'telephone' => $user->telephone,
            'phone' => $user->telephone,
            'avatar' => $user->avatar_url ?? null,
            'username' => $user->username,
            'role' => $user->role,
            'departement' => $user->departement,
            'adresse' => $user->adresse,
            'responsabilites' => $responsabilitesStr, // Chaîne au lieu de tableau
            'specialisations' => $specialisationsStr,  // Chaîne au lieu de tableau
            'statut' => $user->statut,
            'full_name' => trim($user->first_name . ' ' . $user->last_name),
            'formatted_role' => ucfirst(strtolower($user->role ?? 'user')),
            'initials' => strtoupper(substr($user->first_name ?? 'U', 0, 1) . substr($user->last_name ?? '', 0, 1)),
            'created_at' => $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $user->updated_at ? $user->updated_at->format('Y-m-d H:i:s') : null,
            'last_login_at' => $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : null,
            'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->format('Y-m-d H:i:s') : null,
            'two_factor_enabled' => $user->two_factor_enabled ?? false,
            'deleted_at' => $user->deleted_at ? $user->deleted_at->format('Y-m-d H:i:s') : null,
        ];
    }

    private function generateUsername($email)
    {
        $username = strstr($email, '@', true);
        $username = preg_replace('/[^a-zA-Z0-9_]/', '', $username);

        $count = User::where('username', $username)->count();

        if ($count > 0) {
            $username .= '_' . ($count + 1);
        }

        return $username;
    }
}
