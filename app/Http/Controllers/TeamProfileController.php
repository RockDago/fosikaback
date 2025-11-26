<?php

namespace App\Http\Controllers;

use App\Models\TeamUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TeamProfileController extends Controller
{
    /**
     * Récupérer le profil de l'utilisateur connecté
     */
    public function getProfile(Request $request)
    {
        try {
            $user = $request->user();
            
            return response()->json([
                'success' => true,
                'data' => [
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
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération profil:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du profil',
                'error' => $e->getMessage()
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

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'adresse' => 'nullable|string|max:500',
                'departement' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Préparer les données de mise à jour
            $updateData = [
                'nom_complet' => $request->name,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'telephone' => $request->phone,
                'adresse' => $request->adresse,
                'departement' => $request->departement,
            ];

            // Supprimer les champs null
            $updateData = array_filter($updateData, function($value) {
                return $value !== null;
            });

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'data' => [
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
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur mise à jour profil:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil'
            ], 500);
        }
    }

    /**
     * Mettre à jour l'avatar - CORRIGÉ
     */
    public function updateAvatar(Request $request)
    {
        try {
            Log::info('Début upload avatar', ['files' => $request->all()]);

            $validator = Validator::make($request->all(), [
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120' // 5MB
            ]);

            if ($validator->fails()) {
                Log::error('Validation avatar échouée:', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            Log::info('Utilisateur trouvé:', ['user_id' => $user->id, 'email' => $user->email]);

            // Vérifier si le fichier est présent
            if (!$request->hasFile('avatar')) {
                Log::error('Aucun fichier avatar dans la requête');
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun fichier fourni'
                ], 422);
            }

            $file = $request->file('avatar');
            Log::info('Fichier reçu:', [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType()
            ]);

            // Supprimer l'ancien avatar s'il existe
            if ($user->avatar) {
                Log::info('Suppression ancien avatar:', ['path' => $user->avatar]);
                try {
                    if (Storage::disk('public')->exists($user->avatar)) {
                        Storage::disk('public')->delete($user->avatar);
                        Log::info('Ancien avatar supprimé');
                    }
                } catch (\Exception $e) {
                    Log::warning('Erreur suppression ancien avatar:', ['error' => $e->getMessage()]);
                    // Continuer même si la suppression échoue
                }
            }

            // Stocker le nouvel avatar
            try {
                $path = $file->store('avatars/team', 'public');
                Log::info('Avatar stocké avec succès:', ['path' => $path]);
                
                // Mettre à jour l'utilisateur
                $user->avatar = $path;
                $user->save();
                
                Log::info('Utilisateur mis à jour avec nouvel avatar');

                return response()->json([
                    'success' => true,
                    'message' => 'Avatar mis à jour avec succès',
                    'data' => [
                        'avatar_url' => $user->avatar_url
                    ]
                ]);

            } catch (\Exception $e) {
                Log::error('Erreur stockage avatar:', ['error' => $e->getMessage()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors du stockage du fichier'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Erreur mise à jour avatar:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'avatar',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
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

            if ($user->avatar) {
                try {
                    if (Storage::disk('public')->exists($user->avatar)) {
                        Storage::disk('public')->delete($user->avatar);
                    }
                } catch (\Exception $e) {
                    Log::warning('Erreur suppression avatar:', ['error' => $e->getMessage()]);
                }
            }

            $user->avatar = null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Avatar supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur suppression avatar:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'avatar'
            ], 500);
        }
    }

    /**
     * Changer le mot de passe
     */
    public function updatePassword(Request $request)
    {
        try {
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

            $user = $request->user();

            // Vérifier le mot de passe actuel
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le mot de passe actuel est incorrect'
                ], 422);
            }

            // Mettre à jour le mot de passe
            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur mise à jour mot de passe:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du mot de passe'
            ], 500);
        }
    }

    /**
     * Récupérer les statistiques personnelles de l'utilisateur
     */
    public function getPersonalStats(Request $request)
    {
        try {
            $user = $request->user();
            $role = $user->role;

            // Statistiques basiques selon le rôle
            $stats = [
                'missions_en_cours' => 0,
                'missions_terminees' => 0,
                'rapports_soumis' => 0,
                'taux_completion' => 0,
            ];

            switch ($role) {
                case 'Agent':
                    $stats = [
                        'missions_en_cours' => 3,
                        'missions_terminees' => 12,
                        'rapports_soumis' => 15,
                        'taux_completion' => 80,
                    ];
                    break;
                case 'Investigateur':
                    $stats = [
                        'missions_en_cours' => 5,
                        'missions_terminees' => 8,
                        'rapports_soumis' => 13,
                        'taux_completion' => 65,
                    ];
                    break;
                case 'Admin':
                    $stats = [
                        'missions_en_cours' => 2,
                        'missions_terminees' => 20,
                        'rapports_soumis' => 22,
                        'taux_completion' => 90,
                    ];
                    break;
            }

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur récupération stats:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques'
            ], 500);
        }
    }
}