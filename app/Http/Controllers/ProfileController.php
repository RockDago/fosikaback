<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class ProfileController extends Controller
{
    public function __construct()
    {
        // Protection par token Sanctum
        $this->middleware('auth:sanctum');
    }

    /**
     * Récupère le profil de l'utilisateur connecté
     */
    public function getProfile(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatUserData($user)
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur getProfile:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur chargement profil'
            ], 500);
        }
    }

    /**
     * Met à jour les informations du profil
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Log pour débogage
            Log::info('=== UPDATE PROFILE POUR USER ===', [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'name' => $user->name
            ]);

            // --- VALIDATION SIMPLIFIÉE ET CORRIGÉE ---
            $rules = [
                'firstname' => 'required|string|max:255',
                'lastname' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'adresse' => 'nullable|string|max:500',
                'departement' => 'nullable|string|max:255',
                'responsabilites' => 'nullable', // Accepte string ou array
                'specialisations' => 'nullable', // Accepte string ou array
                'name' => 'nullable|string|max:255',
            ];

            // Toujours utiliser unique avec l'exception de l'ID courant
            $rules['email'] = 'required|email|max:255|unique:users,email,' . $user->id;

            $validator = Validator::make($request->all(), $rules, [
                'email.unique' => 'Cet email est déjà utilisé par un autre utilisateur',
                'firstname.required' => 'Le prénom est obligatoire',
                'lastname.required' => 'Le nom est obligatoire',
                'email.required' => 'L\'email est obligatoire',
                'email.email' => 'L\'email doit être une adresse valide',
            ]);

            if ($validator->fails()) {
                $errors = $validator->errors()->toArray();

                Log::error('VALIDATION ÉCHOUÉE POUR USER ' . $user->id . ':', [
                    'errors' => $errors,
                    'user_data' => [
                        'id' => $user->id,
                        'role' => $user->role,
                        'email' => $user->email
                    ],
                    'request_data' => $request->all()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $errors,
                    'user_info' => [
                        'id' => $user->id,
                        'role' => $user->role
                    ]
                ], 422);
            }

            $validatedData = $validator->validated();
            Log::info('Données validées:', $validatedData);

            // Construction du nom complet
            $fullName = $validatedData['name'] ?? ($validatedData['firstname'] . ' ' . $validatedData['lastname']);
            if (empty($fullName)) {
                $fullName = $validatedData['firstname'] . ' ' . $validatedData['lastname'];
            }

            // Gestion des champs qui peuvent être des tableaux ou des strings
            $responsabilites = $validatedData['responsabilites'] ?? $user->responsabilites;
            $specialisations = $validatedData['specialisations'] ?? $user->specialisations;

            // Si c'est un tableau, convertir en string
            if (is_array($responsabilites)) {
                $responsabilites = implode(', ', $responsabilites);
            }

            if (is_array($specialisations)) {
                $specialisations = implode(', ', $specialisations);
            }

            // Mise à jour des champs
            $updateData = [
                'name' => $fullName,
                'first_name' => $validatedData['firstname'],
                'last_name' => $validatedData['lastname'],
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'] ?? $user->phone,
                'adresse' => $validatedData['adresse'] ?? $user->adresse,
                'departement' => $validatedData['departement'] ?? $user->departement,
                'responsabilites' => $responsabilites,
                'specialisations' => $specialisations,
            ];

            // DEBUG: Log avant mise à jour
            Log::info('Tentative de mise à jour pour user ' . $user->id . ':', $updateData);

            // Mise à jour
            $user->update($updateData);
            $user->refresh();

            Log::info('Profil mis à jour avec succès pour user ' . $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'data' => $this->formatUserData($user)
            ]);

        } catch (\Exception $e) {
            Log::error('ERREUR EXCEPTION updateProfile pour user ' . ($user->id ?? 'inconnu') . ':', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur mise à jour profil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Met à jour l'avatar (photo de profil)
     */
    public function updateAvatar(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
            }

            if (!$request->hasFile('avatar')) {
                return response()->json(['success' => false, 'message' => 'Aucun fichier'], 422);
            }

            $file = $request->file('avatar');

            $validExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $extension = strtolower($file->getClientOriginalExtension());

            if (!in_array($extension, $validExtensions)) {
                return response()->json(['success' => false, 'message' => 'Format invalide (JPG, PNG, WebP, GIF)'], 422);
            }

            if ($file->getSize() > 5 * 1024 * 1024) { // 5 Mo
                return response()->json(['success' => false, 'message' => 'Fichier trop volumineux (max 5 Mo)'], 422);
            }

            // Supprimer l'ancien avatar s'il existe
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Générer un nom de fichier unique
            $fileName = 'avatar_' . $user->id . '_' . time() . '.' . $extension;

            // Stocker le fichier
            $path = $file->storeAs('avatars', $fileName, 'public');

            // Mettre à jour le chemin dans la base de données
            $user->avatar = $path;
            $user->save();

            // Générer l'URL complète
            $avatarUrl = Storage::url($path);

            return response()->json([
                'success' => true,
                'message' => 'Avatar mis à jour avec succès',
                'data' => [
                    'avatar' => $path,
                    'avatar_url' => $avatarUrl
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur updateAvatar:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur lors du téléchargement de l\'avatar'], 500);
        }
    }

    /**
     * Met à jour le mot de passe
     */
    public function updatePassword(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
            }

            // Support pour 'newpasswordconfirmation' sans underscore si envoyé par React
            if ($request->has('newpasswordconfirmation') && !$request->has('newpassword_confirmation')) {
                $request->merge(['newpassword_confirmation' => $request->newpasswordconfirmation]);
            }

            $validator = Validator::make($request->all(), [
                'currentpassword' => 'required',
                'newpassword' => 'required|min:8|confirmed',
                'newpassword_confirmation' => 'required_with:newpassword'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!Hash::check($request->currentpassword, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe actuel incorrect',
                    'errors' => ['currentpassword' => ['Mot de passe actuel incorrect']]
                ], 422);
            }

            $user->password = Hash::make($request->newpassword);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur updatePassword:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur lors du changement de mot de passe'], 500);
        }
    }

    /**
     * Supprime l'avatar
     */
    public function deleteAvatar(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
            }

            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
                $user->avatar = null;
                $user->save();
            }

            return response()->json(['success' => true, 'message' => 'Avatar supprimé']);

        } catch (\Exception $e) {
            Log::error('Erreur deleteAvatar:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur lors de la suppression'], 500);
        }
    }

    /**
     * Formate les données utilisateur pour l'API
     */
    private function formatUserData($user): array
    {
        // Générer l'URL de l'avatar
        $avatarUrl = null;

        if ($user->avatar) {
            if (filter_var($user->avatar, FILTER_VALIDATE_URL)) {
                $avatarUrl = $user->avatar;
            } else {
                $avatarUrl = Storage::url($user->avatar);
            }
        }

        // Avatar par défaut si aucun
        if (!$avatarUrl) {
            $initials = strtoupper(
                substr($user->first_name ?? '', 0, 1) .
                substr($user->last_name ?? '', 0, 1)
            );

            if (empty($initials) || strlen($initials) < 2) {
                $initials = substr($user->name ?? 'U', 0, 2);
            }

            $initials = preg_replace('/[^A-Z0-9]+/', '', $initials);

            $avatarUrl = "https://ui-avatars.com/api/?name=" .
                urlencode($initials) .
                "&background=6b7280&color=fff&size=128&bold=true";
        }

        // Gérer les spécialisations qui peuvent être stockées comme string ou array
        $specialisations = $user->specialisations ?? '';
        if (is_string($specialisations) && !empty($specialisations)) {
            // Si c'est une string avec des virgules, convertir en array
            if (strpos($specialisations, ',') !== false) {
                $specialisations = array_map('trim', explode(',', $specialisations));
            } else {
                $specialisations = [$specialisations];
            }
        } elseif (empty($specialisations)) {
            $specialisations = [];
        }

        // Gérer les responsabilités
        $responsabilites = $user->responsabilites ?? '';

        return [
            'id' => $user->id,
            'name' => $user->name ?? 'Utilisateur',
            'firstname' => $user->first_name ?? '',
            'lastname' => $user->last_name ?? '',
            'email' => $user->email ?? '',
            'phone' => $user->phone ?? $user->telephone ?? '',
            'adresse' => $user->adresse ?? '',
            'departement' => $user->departement ?? '',
            'username' => $user->username ?? '',
            'role' => $user->role ?? 'admin',
            'formattedrole' => $this->getFormattedRole($user->role ?? 'admin'),
            'responsabilites' => $responsabilites,
            'specialisations' => $specialisations,
            'statut' => $user->statut ?? 'actif',
            'avatar' => $user->avatar,
            'avatar_url' => $avatarUrl,
            'email_verified_at' => $user->email_verified_at,
            'two_factor_enabled' => (bool) ($user->two_factor_enabled ?? false),
        ];
    }

    private function getFormattedRole($role)
    {
        $roles = [
            'admin' => 'Administrateur',
            'agent' => 'Agent',
            'investigateur' => 'Investigateur',
            'superviseur' => 'Superviseur',
            'utilisateur' => 'Utilisateur'
        ];

        return $roles[strtolower($role)] ?? ucfirst($role);
    }
}
