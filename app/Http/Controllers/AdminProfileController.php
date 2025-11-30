<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AdminProfileController extends Controller
{
    public function getProfile(Request $request)
    {
        try {
            $admin = $request->user();
            
            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Vérifier que c'est bien un Admin
            if (!($admin instanceof \App\Models\Admin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
            }

            // Construction sécurisée des données avec valeurs par défaut
            $profileData = [
                'id' => $admin->id ?? null,
                'name' => $admin->name ?? '',
                'email' => $admin->email ?? '',
                'first_name' => $admin->first_name ?? '',
                'last_name' => $admin->last_name ?? '',
                'phone' => $admin->phone ?? '',
                'avatar' => null,
                'full_name' => '',
            ];

            // Générer full_name de manière sécurisée
            try {
                if (!empty($admin->first_name) && !empty($admin->last_name)) {
                    $profileData['full_name'] = trim($admin->first_name . ' ' . $admin->last_name);
                } else {
                    $profileData['full_name'] = $admin->name ?? '';
                }
            } catch (\Exception $e) {
                Log::warning('Error generating full_name: ' . $e->getMessage());
                $profileData['full_name'] = $admin->name ?? '';
            }

            // Générer avatar_url de manière sécurisée
            try {
                if (!empty($admin->avatar)) {
                    if (filter_var($admin->avatar, FILTER_VALIDATE_URL)) {
                        $profileData['avatar'] = $admin->avatar;
                    } else {
                        $profileData['avatar'] = url('storage/' . $admin->avatar);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error generating avatar URL: ' . $e->getMessage());
                $profileData['avatar'] = null;
            }

            return response()->json([
                'success' => true,
                'data' => $profileData
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Erreur critique getProfile:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du profil',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $admin = $request->user();
            
            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            if (!($admin instanceof \App\Models\Admin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $admin->update([
                'name' => $request->input('name'),
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'phone' => $request->input('phone'),
            ]);

            // Recharger pour avoir les données à jour
            $admin->refresh();

            $fullName = '';
            if ($admin->first_name && $admin->last_name) {
                $fullName = trim($admin->first_name . ' ' . $admin->last_name);
            } else {
                $fullName = $admin->name;
            }

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'data' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'first_name' => $admin->first_name,
                    'last_name' => $admin->last_name,
                    'phone' => $admin->phone,
                    'full_name' => $fullName,
                ]
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Erreur updateProfile:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function updateAvatar(Request $request)
    {
        try {
            $admin = $request->user();
            
            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            if (!($admin instanceof \App\Models\Admin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Supprimer l'ancien avatar
            if ($admin->avatar && Storage::disk('public')->exists($admin->avatar)) {
                Storage::disk('public')->delete($admin->avatar);
            }

            // Sauvegarder le nouvel avatar
            $path = $request->file('avatar')->store('avatars', 'public');
            $admin->avatar = $path;
            $admin->save();

            return response()->json([
                'success' => true,
                'message' => 'Avatar mis à jour avec succès',
                'data' => [
                    'avatar_url' => url('storage/' . $path)
                ]
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Erreur updateAvatar:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'avatar',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function deleteAvatar(Request $request)
    {
        try {
            $admin = $request->user();
            
            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            if (!($admin instanceof \App\Models\Admin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
            }

            if ($admin->avatar && Storage::disk('public')->exists($admin->avatar)) {
                Storage::disk('public')->delete($admin->avatar);
            }

            $admin->avatar = null;
            $admin->save();

            return response()->json([
                'success' => true,
                'message' => 'Avatar supprimé avec succès'
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Erreur deleteAvatar:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'avatar',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function updatePassword(Request $request)
    {
        try {
            $admin = $request->user();
            
            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            if (!($admin instanceof \App\Models\Admin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
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

            if (!Hash::check($request->current_password, $admin->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le mot de passe actuel est incorrect'
                ], 422);
            }

            $admin->password = Hash::make($request->new_password);
            $admin->save();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe mis à jour avec succès'
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Erreur updatePassword:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du mot de passe',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
