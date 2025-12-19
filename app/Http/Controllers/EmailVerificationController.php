<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\Profile; // Remplacez par votre modèle User si nécessaire
use App\Mail\EmailVerificationMail;

class EmailVerificationController extends Controller
{
    public function __construct()
    {
        // Pas besoin de middleware ici car il est déjà dans les routes
        // $this->middleware('auth:sanctum');
    }

    public function sendVerificationEmail(Request $request)
    {
        Log::info('=== EMAIL VERIFICATION START ===');

        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Vérifier si l'email est déjà vérifié
            if ($user->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email déjà vérifié'
                ], 400);
            }

            // Générer un vrai code de vérification
            $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Stocker le code dans la base de données
            $user->email_verification_code = bcrypt($verificationCode);
            $user->email_verification_code_expires_at = now()->addHour();
            $user->save();

            // Envoyer l'email avec le code
            Mail::to($user->email)->send(new EmailVerificationMail($user, $verificationCode));

            Log::info('Verification email sent to: ' . $user->email);

            return response()->json([
                'success' => true,
                'message' => 'Un code de vérification a été envoyé à votre adresse email.',
                'data' => [
                    'email' => $user->email,
                    'expires_in' => 3600,
                    'timestamp' => now()->toDateTimeString(),
                    'user_id' => $user->id
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ EMAIL VERIFICATION ERROR ❌', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email de vérification: ' . $e->getMessage()
            ], 500);
        } finally {
            Log::info('=== EMAIL VERIFICATION END ===');
        }
    }

    public function verifyEmail(Request $request)
    {
        Log::info('=== VERIFY EMAIL CODE START ===');

        try {
            $request->validate([
                'code' => 'required|string|size:6',
                'email' => 'required|email'
            ]);

            $user = Profile::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ], 404);
            }

            // Vérifier si le code est expiré
            if (!$user->email_verification_code_expires_at ||
                now()->gt($user->email_verification_code_expires_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le code a expiré. Veuillez en demander un nouveau.'
                ], 400);
            }

            // Vérifier le code
            if (!Hash::check($request->code, $user->email_verification_code)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code incorrect'
                ], 400);
            }

            // Marquer l'email comme vérifié
            $user->email_verified_at = now();
            $user->email_verification_code = null;
            $user->email_verification_code_expires_at = null;
            $user->save();

            Log::info('Email verified for: ' . $user->email);

            return response()->json([
                'success' => true,
                'message' => 'Email vérifié avec succès!',
                'data' => [
                    'email_verified_at' => $user->email_verified_at,
                    'email' => $user->email,
                    'user_id' => $user->id
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Verify email error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification: ' . $e->getMessage()
            ], 500);
        } finally {
            Log::info('=== VERIFY EMAIL CODE END ===');
        }
    }

    public function resendVerificationCode(Request $request)
    {
        Log::info('=== RESEND VERIFICATION CODE START ===');

        try {
            // Vérifier l'authentification
            if (!Auth::check()) {
                Log::warning('Resend: User not authenticated');
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }

            $user = $request->user();

            Log::info('Resend code for user:', [
                'id' => $user->id,
                'email' => $user->email
            ]);

            // Vérifier si l'email est déjà vérifié
            if ($user->email_verified_at) {
                Log::info('Resend: Email already verified for:', ['email' => $user->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'Email déjà vérifié'
                ], 400);
            }

            // Générer un nouveau code de vérification
            $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Mettre à jour le code dans la base de données
            $user->email_verification_code = bcrypt($verificationCode);
            $user->email_verification_code_expires_at = now()->addHour();
            $user->save();

            // Envoyer le nouvel email avec le code
            Mail::to($user->email)->send(new EmailVerificationMail($user, $verificationCode));

            Log::info('New verification code sent to:', ['email' => $user->email]);

            // Réponse réussie
            return response()->json([
                'success' => true,
                'message' => 'Un nouveau code de vérification a été envoyé.',
                'data' => [
                    'email' => $user->email,
                    'expires_in' => 3600,
                    'timestamp' => now()->toDateTimeString(),
                    'user_id' => $user->id
                ]
            ]);

        } catch (\Exception $e) {
            // Log détaillé de l'erreur
            Log::error('❌ RESEND VERIFICATION CODE ERROR ❌', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString()
            ]);

            // Réponse d'erreur
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du renvoi du code',
                'error' => $e->getMessage()
            ], 500);
        } finally {
            Log::info('=== RESEND VERIFICATION CODE END ===');
        }
    }

    public function checkVerificationStatus(Request $request)
    {
        Log::info('=== CHECK VERIFICATION STATUS START ===');

        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $status = [
                'email_verified' => !is_null($user->email_verified_at),
                'email_verified_at' => $user->email_verified_at,
                'email' => $user->email,
                'has_pending_verification' => !is_null($user->email_verification_code),
                'user_id' => $user->id,
                'checked_at' => now()->toDateTimeString()
            ];

            Log::info('Verification status:', $status);

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Check verification status error:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du statut'
            ], 500);
        } finally {
            Log::info('=== CHECK VERIFICATION STATUS END ===');
        }
    }
}
