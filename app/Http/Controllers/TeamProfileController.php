<?php

namespace App\Http\Controllers;

use App\Models\TeamUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TeamProfileController extends Controller
{
    /**
     * Récupérer le profil de l'utilisateur connecté
     */
    public function getProfile(Request $request)
    {
        try {
            $user = $request->user();
            
            // Vérification plus robuste de l'utilisateur
            if (!$user instanceof TeamUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non valide ou token expiré'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatUserData($user)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur récupération profil TeamUser:', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du profil',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Mettre à jour le profil
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user instanceof TeamUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non valide ou token expiré'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'adresse' => 'nullable|string|max:500',
                'departement' => 'nullable|string|max:255',
                'responsabilites' => 'nullable|string',
                'specialisations' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = array_filter([
                'nom_complet' => $request->name,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'telephone' => $request->phone,
                'adresse' => $request->adresse,
                'departement' => $request->departement,
                'responsabilites' => $request->responsabilites,
                'specialisations' => $request->specialisations,
            ], fn($value) => $value !== null);

            Log::info('Mise à jour profil TeamUser:', [
                'user_id' => $user->id,
                'data' => $updateData
            ]);

            $user->update($updateData);
            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'data' => $this->formatUserData($user)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour profil TeamUser:', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Mettre à jour l'avatar
     */
    public function updateAvatar(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user instanceof TeamUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non valide ou token expiré'
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

            // Supprimer l'ancien avatar s'il existe
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Générer un nom de fichier unique
            $fileName = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('avatars/team', $fileName, 'public');
            
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
            Log::error('Erreur mise à jour avatar TeamUser:', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'avatar',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Changer le mot de passe
     */
    public function updatePassword(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user instanceof TeamUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non valide ou token expiré'
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

            // Vérifier que le nouveau mot de passe est différent de l'ancien
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
            Log::error('Erreur mise à jour mot de passe TeamUser:', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du mot de passe',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Supprimer l'avatar
     */
    public function deleteAvatar(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user instanceof TeamUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non valide ou token expiré'
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
                'data' => [
                    'avatar_url' => null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur suppression avatar TeamUser:', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'avatar',
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
            'name' => $user->nom_complet,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->telephone,
            'avatar' => $user->avatar_url,
            'username' => $user->username,
            'role' => $user->role,
            'departement' => $user->departement,
            'adresse' => $user->adresse,
            'responsabilites' => $user->responsabilites,
            'specialisations' => $user->specialisations,
            'statut' => $user->statut,
            'full_name' => $user->nom_complet,
        ];
    }
}