<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Notifications\TwoFactorCodeNotification;

class TwoFactorAuthenticationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Envoyer un code de vérification 2FA
     */
    public function sendVerificationCode(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            if (!$user instanceof User) {
                Log::error('Type d\'utilisateur incorrect', [
                    'user_class' => get_class($user),
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Erreur système'
                ], 500);
            }

            // Vérifier si l'email est vérifié
            if (!$user->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veuillez vérifier votre email avant d\'activer la 2FA'
                ], 400);
            }

            // Si la 2FA est déjà activée, utiliser la méthode normale
            if ($user->two_factor_enabled) {
                $code = $user->generateTwoFactorCode();

                // Envoyer la notification
                $user->notify(new TwoFactorCodeNotification($code, $user->two_factor_code_expires_at));

                Log::info('Code 2FA renvoyé pour l\'utilisateur:', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Un nouveau code a été envoyé à votre adresse email.',
                    'data' => [
                        'email' => $user->email,
                        'expires_in' => 900 // 15 minutes en secondes
                    ]
                ]);
            }

            // Pour l'activation initiale de la 2FA
            $code = $user->generateTwoFactorCode();

            // Envoyer la notification
            $user->notify(new TwoFactorCodeNotification($code, $user->two_factor_code_expires_at));

            Log::info('Code 2FA d\'activation envoyé à:', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Un code de vérification a été envoyé à votre adresse email.',
                'data' => [
                    'email' => $user->email,
                    'expires_in' => 900
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur sendVerificationCode:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du code: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifier un code 2FA
     */
    public function verifyCode(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required|string|size:6'
            ]);

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Vérifier si la 2FA est déjà activée (pour login normal)
            if ($user->two_factor_enabled) {
                if ($user->verifyTwoFactorCode($request->code)) {
                    // Marquer comme vérifié dans la session
                    session(['2fa_verified' => true]);
                    session(['2fa_verified_at' => now()]);

                    // Effacer le code utilisé
                    $user->two_factor_code = null;
                    $user->two_factor_code_expires_at = null;
                    $user->save();

                    Log::info('2FA vérifiée pour la connexion:', [
                        'user_id' => $user->id,
                        'email' => $user->email
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Vérification 2FA réussie!',
                        'data' => [
                            'two_factor_verified' => true,
                            'user' => $user->getProfileData()
                        ]
                    ]);
                }

                // Vérifier si le code a expiré
                if ($user->isTwoFactorCodeExpired()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Code expiré. Veuillez en demander un nouveau.'
                    ], 400);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Code invalide'
                ], 400);
            }

            // Pour l'activation initiale de la 2FA
            $result = $user->verifyAndEnableTwoFactor($request->code);

            if ($result) {
                Log::info('2FA activée pour l\'utilisateur:', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Double authentification activée avec succès!',
                    'data' => [
                        'two_factor_enabled' => true,
                        'recovery_codes' => $user->two_factor_recovery_codes,
                        'user' => $user->getProfileData()
                    ]
                ]);
            } else {
                if ($user->isTwoFactorCodeExpired()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Code expiré. Veuillez en demander un nouveau.'
                    ], 400);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Code invalide'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Erreur verifyCode:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activer/désactiver la 2FA
     */
    public function toggleTwoFactor(Request $request)
    {
        try {
            $request->validate([
                'enabled' => 'boolean'
            ]);

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $enabled = $request->input('enabled', !$user->two_factor_enabled);

            if ($enabled) {
                // Pour activer, on doit d'abord envoyer un code
                $code = $user->generateTwoFactorCode();

                // Envoyer la notification
                $user->notify(new TwoFactorCodeNotification($code, $user->two_factor_code_expires_at));

                return response()->json([
                    'success' => true,
                    'message' => 'Code de vérification envoyé pour activer la 2FA',
                    'data' => [
                        'requires_code' => true,
                        'email' => $user->email
                    ]
                ]);
            } else {
                // Désactiver la 2FA
                $user->disableTwoFactor();

                // Effacer la vérification de session
                session()->forget(['2fa_verified', '2fa_verified_at']);

                Log::info('2FA désactivée pour l\'utilisateur:', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Double authentification désactivée avec succès',
                    'data' => [
                        'two_factor_enabled' => false,
                        'user' => $user->getProfileData()
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Erreur toggleTwoFactor:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Générer de nouveaux codes de récupération
     */
    public function generateRecoveryCodes(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || !$user->two_factor_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => '2FA non activée'
                ], 400);
            }

            $recoveryCodes = $user->generateNewRecoveryCodes();

            Log::info('Nouveaux codes de récupération générés:', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nouveaux codes de récupération générés',
                'data' => [
                    'recovery_codes' => $recoveryCodes,
                    'count' => count($recoveryCodes)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur generateRecoveryCodes:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifier un code de récupération
     */
    public function verifyRecoveryCode(Request $request)
    {
        try {
            $request->validate([
                'recovery_code' => 'required|string'
            ]);

            $user = $request->user();

            if (!$user || !$user->two_factor_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => '2FA non activée'
                ], 400);
            }

            if ($user->verifyRecoveryCode($request->recovery_code)) {
                // Marquer comme vérifié dans la session
                session(['2fa_verified' => true]);
                session(['2fa_verified_at' => now()]);

                Log::warning('Code de récupération utilisé:', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Code de récupération accepté',
                    'data' => [
                        'two_factor_verified' => true,
                        'remaining_codes' => count($user->two_factor_recovery_codes)
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Code de récupération invalide'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Erreur verifyRecoveryCode:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification: ' . $e->getMessage()
            ], 500);
        }
    }
}
