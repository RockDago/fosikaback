<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Notifications\TwoFactorCodeNotification;
use App\Mail\UserAccountCreatedMail; // Assurez-vous d'importer la classe
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail; // Importer Mail facade
use Jenssegers\Agent\Agent;

class UserAuthController extends Controller
{
    // =========================================
    // 1. LOGIN (GÉNÉRATION TOKEN + DÉTECTION 2FA INTELLIGENTE)
    // =========================================
    public function login(Request $request)
    {
        try {
            // 1. Validation
            $validator = Validator::make($request->all(), [
                'login'    => 'required|string',
                'password' => 'required|string',
                'role'     => 'sometimes|string|in:admin,agent,investigateur',
            ]);

            if ($validator->fails()) {
                Log::warning('Login validation failed', ['login' => $request->login]);
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            // 2. Récupérer l'utilisateur
            $user = User::where('email', $request->login)
                ->orWhere('username', $request->login)
                ->first();

            // 3. Vérification mot de passe
            if (!$user || !Hash::check($request->password, $user->password)) {
                Log::warning('Login failed - incorrect credentials', ['login' => $request->login]);

                AuditLogger::logConnexion(
                    $request->login,
                    'Échec',
                    'Identifiants invalides pour la tentative de connexion'
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Identifiant ou mot de passe incorrect',
                ], 401);
            }

            // 4. Vérifier le statut du compte
            $statut = strtolower(trim($user->statut ?? ''));
            $allowedStatuses = ['actif', 'active', 'activé', 'enabled', '1', 'true'];

            if (!in_array($statut, $allowedStatuses)) {
                AuditLogger::logConnexion(
                    $user->email,
                    'Refusé',
                    "Connexion refusée : compte inactif (statut={$user->statut})"
                );

                return response()->json([
                    'success'     => false,
                    'message'     => 'Votre compte n\'est pas actif. Contactez l\'administrateur.',
                    'user_status' => $user->statut,
                ], 403);
            }

            // 5. Vérifier le rôle si spécifié
            if ($request->has('role') && $user->role !== $request->role) {
                AuditLogger::logConnexion(
                    $user->email,
                    'Refusé',
                    "Connexion refusée : rôle demandé={$request->role}, rôle réel={$user->role}"
                );

                return response()->json([
                    'success' => false,
                    'message' => "Vous n'êtes pas autorisé à vous connecter en tant que {$request->role}",
                ], 403);
            }

            // 6. GÉNÉRATION DU TOKEN SANCTUM
            // On supprime les tokens existants pour repartir sur une base propre
            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;

            // 7. Mettre à jour les informations de connexion de base
            $agent = new Agent();
            $user->update([
                'last_login_at'       => now(),
                'last_login_ip'       => $request->ip(),
                'last_login_browser'  => $agent->browser(),
                'last_login_platform' => $agent->platform(),
            ]);

            // Audit succès connexion initiale (Username/Password OK)
            AuditLogger::logConnexion(
                $user->email,
                'Succès',
                'Authentification primaire réussie'
            );

            // 8. PRÉPARER LA RÉPONSE DE BASE
            $responseData = [
                'success'    => true,
                'message'    => 'Connexion réussie',
                'user'       => $this->formatUserData($user),
                'token'      => $token,
                'token_type' => 'Bearer',
                'is_admin'   => in_array(strtolower($user->role), ['admin']),
            ];

            // 9. LOGIQUE 2FA ADAPTATIVE (INTELLIGENTE)
            if ($user->two_factor_enabled) {

                $currentDeviceAgent = $agent->browser() . ' on ' . $agent->platform();
                $currentIp = $request->ip();

                // Est-ce que c'est le même appareil que la dernière fois ?
                $isTrusted = (
                    !is_null($user->trusted_device_ip) &&
                    $user->trusted_device_ip === $currentIp &&
                    $user->trusted_device_agent === $currentDeviceAgent
                );

                if ($isTrusted) {
                    // ✅ C'EST LE MÊME APPAREIL DE CONFIANCE -> PAS DE 2FA
                    $user->two_factor_verified_at = now(); // On marque comme vérifié immédiatement
                    $user->save();

                    $responseData['requires_2fa'] = false;
                    $responseData['two_factor_verified'] = true;
                    $responseData['two_factor_enabled'] = true;
                    $responseData['message'] = 'Connexion réussie (Appareil reconnu)';

                    AuditLogger::logSystemAction(
                        $user->email,
                        'Login complet',
                        'Authentification',
                        'Succès',
                        'Login sans 2FA (Appareil de confiance reconnu)'
                    );

                } else {
                    // ⚠️ NOUVEL APPAREIL OU IP -> 2FA REQUISE
                    // On force la vérification
                    $user->two_factor_verified_at = null;
                    $user->save();

                    $responseData['requires_2fa'] = true;
                    $responseData['two_factor_verified'] = false;
                    $responseData['two_factor_enabled'] = true;
                    $responseData['message'] = 'Nouvel appareil détecté. Vérification 2FA requise.';

                    // Générer et envoyer le code
                    $code = $user->generateTwoFactorCode();

                    try {
                        $user->notify(new TwoFactorCodeNotification($code, $user->two_factor_code_expires_at));
                        Log::info('2FA notification sent - New Device Detected', ['user_id' => $user->id]);
                    } catch (\Exception $e) {
                        Log::error('Failed to send 2FA notification', ['error' => $e->getMessage()]);
                    }

                    AuditLogger::logSystemAction(
                        $user->email,
                        '2FA - Requis (Nouvel appareil)',
                        'Authentification',
                        'Alerte',
                        'Login partiel - Nouvel appareil détecté : ' . $currentDeviceAgent
                    );
                }

            } else {
                // --- CAS : PAS DE 2FA ACTIVÉE DU TOUT ---
                $responseData['requires_2fa'] = false;
                $responseData['two_factor_enabled'] = false;
                $responseData['two_factor_verified'] = true;

                // On marque comme vérifié pour ne pas être bloqué par le middleware
                $user->two_factor_verified_at = now();
                $user->save();
            }

            return response()->json($responseData);

        } catch (\Throwable $e) {
            Log::error('LOGIN ERROR', [
                'msg'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur lors de la connexion',
                'debug'   => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    // =========================================
    // 2. VÉRIFICATION 2FA APRÈS LOGIN (ET ENREGISTREMENT CONFIANCE)
    // =========================================
    public function verifyTwoFactor(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|size:6',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Code invalide'], 422);
            }

            $user = $request->user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Session expirée.'], 401);
            }

            if (!$user->two_factor_enabled) {
                return response()->json(['success' => false, 'message' => '2FA non activée.'], 400);
            }

            if ($user->isTwoFactorCodeExpired()) {
                return response()->json(['success' => false, 'message' => 'Code expiré.'], 400);
            }

            // VÉRIFIER LE CODE
            if ($user->verifyTwoFactorCode($request->code)) {

                // ✅ VALIDATION RÉUSSIE
                $agent = new Agent();

                // On met à jour le statut ET on enregistre l'appareil comme "de confiance"
                $user->forceFill([
                    'two_factor_verified_at' => now(),
                    'two_factor_code' => null,
                    'two_factor_code_expires_at' => null,
                    // 👇 ON ENREGISTRE L'APPAREIL DE CONFIANCE POUR LA PROCHAINE FOIS
                    'trusted_device_ip' => $request->ip(),
                    'trusted_device_agent' => $agent->browser() . ' on ' . $agent->platform(),
                ])->save();

                AuditLogger::logSystemAction(
                    $user->email,
                    '2FA - Vérification réussie',
                    'Authentification',
                    'Succès',
                    'Nouvel appareil enregistré comme fiable'
                );

                return response()->json([
                    'success'             => true,
                    'message'             => 'Appareil vérifié et enregistré !',
                    'two_factor_verified' => true,
                    'user'                => $this->formatUserData($user),
                    'verified_at'         => now()->format('Y-m-d H:i:s'),
                ]);
            }

            AuditLogger::logSystemAction(
                $user->email,
                '2FA - Échec',
                'Authentification',
                'Échec',
                'Code incorrect'
            );

            return response()->json([
                'success' => false,
                'message' => 'Code incorrect.',
            ], 400);

        } catch (\Throwable $e) {
            Log::error('2FA VERIFICATION ERROR', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    // =========================================
    // 3. RENVOYER UN CODE 2FA
    // =========================================
    public function resendTwoFactorCode(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || !$user->two_factor_enabled) {
                return response()->json(['success' => false, 'message' => 'Action non autorisée'], 400);
            }

            $code = $user->generateTwoFactorCode();

            try {
                $user->notify(new TwoFactorCodeNotification($code, $user->two_factor_code_expires_at));
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => 'Erreur envoi email'], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Nouveau code envoyé.',
            ]);

        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    // =========================================
    // 4. CRÉATION D'UTILISATEUR AVEC NOTIFICATION PAR EMAIL
    // =========================================
    public function createUserWithNotification(Request $request)
    {
        try {
            Log::info('=== CRÉATION UTILISATEUR AVEC NOTIFICATION DÉBUT ===', [
                'data' => $request->except(['password', 'password_confirmation']),
                'admin_id' => $request->user()->id ?? 'system'
            ]);

            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'username' => 'required|string|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|string|in:admin,agent,investigateur',
                'telephone' => 'nullable|string|max:20',
                'departement' => 'nullable|string|max:255',
                'specialisations' => 'nullable|array',
                'responsabilites' => 'nullable|array',
                'adresse' => 'nullable|string',
                'statut' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                Log::warning('Validation échouée création utilisateur', ['errors' => $validator->errors()->toArray()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation des données échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Vérifier que l'utilisateur qui crée est un admin
            $admin = $request->user();
            if (!$admin || !in_array(strtolower($admin->role), ['admin'])) {
                Log::warning('Tentative de création utilisateur sans droits admin', ['user_id' => $admin->id ?? 'none']);
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Droits administrateur requis.'
                ], 403);
            }

            // Créer l'utilisateur
            $userData = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'telephone' => $request->telephone,
                'departement' => $request->departement,
                'specialisations' => $request->specialisations ?? [],
                'responsabilites' => $request->responsabilites ?? [],
                'adresse' => $request->adresse,
                'statut' => $request->statut ?? true,
                'email_verified_at' => null, // Pas encore vérifié
                'created_by' => $admin->id, // Enregistrer qui a créé le compte
            ];

            Log::info('Données utilisateur à créer', $userData);

            $user = User::create($userData);

            // Générer un code de vérification d'email
            $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $user->email_verification_code = bcrypt($verificationCode);
            $user->email_verification_code_expires_at = now()->addHours(24);
            $user->save();

            Log::info('Utilisateur créé avec succès', [
                'user_id' => $user->id,
                'email' => $user->email,
                'verification_code_generated' => true
            ]);

            // Envoyer l'email de bienvenue avec les identifiants
            try {
                Mail::to($user->email)->send(new UserAccountCreatedMail($user, $request->password, $verificationCode));
                Log::info('Email de bienvenue envoyé avec succès', ['email' => $user->email]);

                $emailStatus = 'Envoyé';
                $emailError = null;
            } catch (\Exception $e) {
                Log::error('Erreur lors de l\'envoi de l\'email', [
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                $emailStatus = 'Échec';
                $emailError = $e->getMessage();
            }

            // Logger l'action
            AuditLogger::logSystemAction(
                $admin->email,
                'Création de compte utilisateur',
                'Administration',
                'Succès',
                "Compte créé pour {$user->email} (ID: {$user->id})" .
                ($emailError ? " - Échec envoi email: {$emailError}" : " - Email envoyé avec succès")
            );

            $response = [
                'success' => true,
                'message' => 'Compte utilisateur créé avec succès.',
                'data' => $this->formatUserData($user),
                'notification' => [
                    'email_sent' => $emailStatus === 'Envoyé',
                    'email_status' => $emailStatus,
                    'email_address' => $user->email
                ]
            ];

            if ($emailError) {
                $response['warning'] = "Le compte a été créé mais l'email n'a pas pu être envoyé: {$emailError}";
            } else {
                $response['message'] .= ' Un email a été envoyé à l\'utilisateur avec ses identifiants.';
            }

            Log::info('=== CRÉATION UTILISATEUR AVEC NOTIFICATION FIN ===', ['user_id' => $user->id]);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Erreur création utilisateur:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['password', 'password_confirmation'])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du compte: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================
    // 5. VÉRIFIER STATUT AUTH ET 2FA
    // =========================================
    public function checkTwoFactorRequired(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['authenticated' => false], 401);
        }

        $requires2FA = false;

        // Si 2FA activée MAIS pas encore validée pour cette session -> Requis
        if ($user->two_factor_enabled && is_null($user->two_factor_verified_at)) {
            $requires2FA = true;
        }

        return response()->json([
            'success'             => true,
            'requires_2fa'        => $requires2FA,
            'two_factor_enabled'  => $user->two_factor_enabled,
            'two_factor_verified' => !is_null($user->two_factor_verified_at),
            'authenticated'       => true,
            'user'                => $this->formatUserData($user),
        ]);
    }

    // =========================================
    // 6. DÉCONNEXION
    // =========================================
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if ($user) {
                // On révoque la validation 2FA (pour forcer la re-vérification de confiance au prochain login)
                // Note : On ne supprime PAS 'trusted_device_ip' ici, car on veut se souvenir de l'appareil
                $user->two_factor_verified_at = null;
                $user->save();

                $user->currentAccessToken()->delete();

                AuditLogger::logSystemAction(
                    $user->email,
                    'Déconnexion',
                    'Authentification',
                    'Succès',
                    'Déconnexion utilisateur'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie',
            ]);

        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Erreur lors de la déconnexion'], 500);
        }
    }

    // =========================================
    // 7. HELPER : CHECK AUTH GLOBAL
    // =========================================
    public function checkAuth(Request $request)
    {
        return $this->checkTwoFactorRequired($request);
    }

    public function user(Request $request)
    {
        return $this->checkAuth($request);
    }

    // =========================================
    // 8. MÉTHODE POUR CRÉER UN UTILISATEUR SIMPLE (sans email)
    // Gardée pour compatibilité
    // =========================================
    public function createUser(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'username' => 'required|string|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|string|in:admin,agent,investigateur',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation des données échouée',
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
                'statut' => $request->statut ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Compte utilisateur créé avec succès.',
                'data' => $this->formatUserData($user)
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur création utilisateur simple:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du compte: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================
    // 9. FORMATAGE DONNÉES USER
    // =========================================
    private function formatUserData($user)
    {
        return [
            'id'                => $user->id,
            'name'              => $user->name ?? ($user->first_name . ' ' . $user->last_name),
            'first_name'        => $user->first_name,
            'last_name'         => $user->last_name,
            'email'             => $user->email,
            'username'          => $user->username,
            'phone'             => $user->phone ?? $user->telephone ?? null,
            'adresse'           => $user->adresse ?? null,
            'role'              => $user->role,
            'formatted_role'    => $this->getFormattedRole($user->role),
            'departement'       => $user->departement ?? null,
            'specialisations'   => $user->specialisations ?? [],
            'responsabilites'   => $user->responsabilites ?? [],
            'statut'            => $user->statut ?? 'actif',
            'avatar'            => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'initials'          => $this->getInitials($user),
            'created_at'        => $user->created_at->format('Y-m-d H:i:s'),
            'is_admin'          => in_array(strtolower($user->role), ['admin']),
            'email_verified'    => !is_null($user->email_verified_at),
            'two_factor_enabled' => $user->two_factor_enabled ?? false,
            'last_login_at'     => $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : null,
        ];
    }

    private function getFormattedRole($role)
    {
        $roles = [
            'admin'         => 'Administrateur',
            'agent'         => 'Agent',
            'investigateur' => 'Investigateur',
        ];
        return $roles[strtolower($role)] ?? ucfirst($role);
    }

    private function getInitials($user)
    {
        $firstName = $user->first_name ?? '';
        $lastName  = $user->last_name ?? '';

        if ($firstName && $lastName) {
            return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
        } elseif ($user->email) {
            return strtoupper(substr($user->email, 0, 2));
        }
        return 'U';
    }
}
