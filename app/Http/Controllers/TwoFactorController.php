<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cookie;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\Notification;

class TwoFactorController extends Controller
{
    /**
     * Routes exemptées de la vérification
     */
    protected $exemptRoutes = [
        'two-factor.*',
        'logout',
        'login',
        'check-auth',
    ];

    /**
     * Afficher le formulaire de vérification 2FA
     */
    public function showVerifyForm(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if (!$user->two_factor_enabled) {
            return redirect()->intended('/dashboard');
        }

        // Vérifier si la 2FA est déjà vérifiée
        if (Session::get('2fa_verified')) {
            $intendedUrl = Session::get('two_factor_intended', route('dashboard'));
            Session::forget('two_factor_intended');
            return redirect()->to($intendedUrl);
        }

        // Générer un code si nécessaire
        if (!$user->two_factor_code || $user->isTwoFactorCodeExpired()) {
            $code = $user->generateTwoFactorCode();

            // Envoyer la notification
            try {
                $user->notify(new \App\Notifications\TwoFactorCodeNotification($code, $user->two_factor_code_expires_at));
            } catch (\Exception $e) {
                // Continuer même si l'envoi échoue
                \Log::error('Erreur envoi notification 2FA:', ['error' => $e->getMessage()]);
            }
        }

        // Récupérer les changements détectés
        $agent = new Agent();
        $changes = [
            'ip_changed' => Session::get('two_factor_ip_changed', false),
            'browser_changed' => Session::get('two_factor_browser_changed', false),
            'platform_changed' => Session::get('two_factor_platform_changed', false),
            'previous_ip' => Session::get('two_factor_previous_ip'),
            'new_ip' => Session::get('two_factor_new_ip', $request->ip()),
            'previous_browser' => Session::get('two_factor_previous_browser'),
            'new_browser' => Session::get('two_factor_new_browser', $agent->browser()),
            'previous_platform' => Session::get('two_factor_previous_platform'),
            'new_platform' => Session::get('two_factor_new_platform', $agent->platform()),
            'first_access' => !Session::has('two_factor_verified_device'),
        ];

        // Calculer le temps restant
        $expiresAt = $user->two_factor_code_expires_at;
        $remainingSeconds = $expiresAt ? now()->diffInSeconds($expiresAt, false) : 900;

        return view('auth.two-factor-verify', [
            'changes' => $changes,
            'user' => $user,
            'expires_at' => $expiresAt,
            'remaining_seconds' => max(0, $remainingSeconds),
            'email' => $user->email,
        ]);
    }

    /**
     * Traiter la vérification 2FA
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
            'remember' => 'nullable|boolean',
        ]);

        $user = Auth::user();

        if (!$user || !$user->two_factor_enabled) {
            return redirect()->route('login');
        }

        // Vérifier le blocage
        $blockedUntil = Session::get('two_factor_blocked_until');
        if ($blockedUntil && now()->lt($blockedUntil)) {
            $remaining = now()->diffInMinutes($blockedUntil, false);
            return back()->withErrors([
                'code' => 'Trop de tentatives. Veuillez réessayer dans ' . $remaining . ' minutes.',
            ]);
        }

        // Vérifier si le code est expiré
        if ($user->isTwoFactorCodeExpired()) {
            return back()->withErrors([
                'code' => 'Le code a expiré. Veuillez en demander un nouveau.',
            ])->withInput();
        }

        // Vérifier le code
        if (!$user->verifyTwoFactorCode($request->code)) {
            // Incrémenter les tentatives échouées
            $failedAttempts = Session::get('two_factor_failed_attempts', 0) + 1;
            Session::put('two_factor_failed_attempts', $failedAttempts);

            if ($failedAttempts >= 5) {
                Session::put('two_factor_blocked_until', now()->addMinutes(15));
                return back()->withErrors([
                    'code' => 'Trop de tentatives. Veuillez réessayer dans 15 minutes.',
                ]);
            }

            return back()->withErrors([
                'code' => 'Code incorrect. Tentative ' . $failedAttempts . '/5',
            ])->withInput();
        }

        // Réinitialiser les tentatives
        Session::forget(['two_factor_failed_attempts', 'two_factor_blocked_until']);

        // Marquer comme vérifié
        Session::put('2fa_verified', true);
        Session::put('2fa_verified_at', now());

        // Stocker les infos de l'appareil actuel
        $agent = new Agent();
        Session::put('two_factor_verified_device', [
            'ip' => $request->ip(),
            'browser' => $agent->browser(),
            'platform' => $agent->platform(),
            'device' => $agent->device(),
            'user_agent' => $request->userAgent(),
            'verified_at' => now(),
        ]);

        // Effacer les flags de changement
        Session::forget([
            'two_factor_ip_changed', 'two_factor_browser_changed', 'two_factor_platform_changed',
            'two_factor_previous_ip', 'two_factor_new_ip',
            'two_factor_previous_browser', 'two_factor_new_browser',
            'two_factor_previous_platform', 'two_factor_new_platform',
            'two_factor_requires_verification', 'two_factor_detected_changes'
        ]);

        // Gérer "Remember me" pour 30 jours
        if ($request->remember) {
            $token = $user->generateTwoFactorRememberToken();
            Cookie::queue('two_factor_remember', $token, 30 * 24 * 60);
        } else {
            // Effacer le cookie si existant
            Cookie::queue(Cookie::forget('two_factor_remember'));
            $user->clearTwoFactorRememberToken();
        }

        // Effacer le code utilisé
        $user->two_factor_code = null;
        $user->two_factor_code_expires_at = null;
        $user->save();

        // Rediriger vers la page d'origine
        $intendedUrl = Session::pull('two_factor_intended', route('dashboard'));

        return redirect()->to($intendedUrl)
            ->with('success', 'Vérification 2FA réussie !');
    }

    /**
     * Renvoyer un nouveau code
     */
    public function resend()
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Vérifier le blocage
        $blockedUntil = Session::get('two_factor_blocked_until');
        if ($blockedUntil && now()->lt($blockedUntil)) {
            $remaining = now()->diffInMinutes($blockedUntil, false);
            return back()->withErrors([
                'code' => 'Trop de demandes. Réessayez dans ' . $remaining . ' minutes.',
            ]);
        }

        // Limiter le renvoi
        $lastResend = Session::get('two_factor_last_resend');
        if ($lastResend && now()->diffInSeconds($lastResend) < 60) {
            return back()->withErrors([
                'code' => 'Veuillez patienter avant de redemander un code.',
            ]);
        }

        // Générer un nouveau code
        $code = $user->generateTwoFactorCode();

        // Envoyer la notification
        try {
            $user->notify(new \App\Notifications\TwoFactorCodeNotification($code, $user->two_factor_code_expires_at));
        } catch (\Exception $e) {
            \Log::error('Erreur renvoi notification 2FA:', ['error' => $e->getMessage()]);
        }

        Session::put('two_factor_last_resend', now());

        return back()->with('success', 'Un nouveau code a été envoyé.');
    }

    /**
     * Utiliser un code de récupération
     */
    public function useRecoveryCode(Request $request)
    {
        $request->validate([
            'recovery_code' => 'required|string',
        ]);

        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->verifyRecoveryCode($request->recovery_code)) {
            // Marquer comme vérifié
            Session::put('2fa_verified', true);
            Session::put('2fa_verified_at', now());

            // Stocker les infos de l'appareil
            $agent = new Agent();
            Session::put('two_factor_verified_device', [
                'ip' => $request->ip(),
                'browser' => $agent->browser(),
                'platform' => $agent->platform(),
                'device' => $agent->device(),
                'user_agent' => $request->userAgent(),
                'verified_at' => now(),
            ]);

            // Effacer les flags
            Session::forget([
                'two_factor_ip_changed', 'two_factor_browser_changed', 'two_factor_platform_changed',
                'two_factor_requires_verification', 'two_factor_detected_changes'
            ]);

            // Effacer le cookie pour plus de sécurité
            Cookie::queue(Cookie::forget('two_factor_remember'));
            $user->clearTwoFactorRememberToken();

            $intendedUrl = Session::pull('two_factor_intended', route('dashboard'));

            return redirect()->to($intendedUrl)
                ->with('warning', 'Code de récupération utilisé. Veuillez générer de nouveaux codes.');
        }

        return back()->withErrors([
            'recovery_code' => 'Code de récupération invalide.',
        ]);
    }

    /**
     * Afficher les codes de récupération
     */
    public function showRecoveryCodes()
    {
        $user = Auth::user();

        if (!$user || !$user->two_factor_enabled) {
            return redirect()->route('dashboard');
        }

        $recoveryCodes = $user->two_factor_recovery_codes ?? [];

        return view('auth.two-factor-recovery-codes', [
            'recoveryCodes' => $recoveryCodes,
            'user' => $user,
        ]);
    }

    /**
     * Générer de nouveaux codes de récupération
     */
    public function generateNewRecoveryCodes()
    {
        $user = Auth::user();

        if (!$user || !$user->two_factor_enabled) {
            return redirect()->route('dashboard');
        }

        $recoveryCodes = $user->generateNewRecoveryCodes();

        return view('auth.two-factor-recovery-codes', [
            'recoveryCodes' => $recoveryCodes,
            'user' => $user,
            'regenerated' => true,
        ])->with('success', 'Nouveaux codes de récupération générés.');
    }

    /**
     * Middleware pour vérifier la 2FA (à ajouter dans app/Http/Kernel.php si nécessaire)
     */
    public function checkTwoFactor(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return true; // Pas d'utilisateur connecté
        }

        // Si la 2FA n'est pas activée, laisser passer
        if (!$user->two_factor_enabled) {
            return true;
        }

        // Vérifier le cookie "remember me"
        $rememberToken = $request->cookie('two_factor_remember');
        if ($rememberToken && $user->verifyTwoFactorRememberToken($rememberToken)) {
            Session::put('2fa_verified', true);
            return true;
        }

        // Vérifier si déjà vérifié dans cette session
        if (Session::get('2fa_verified')) {
            // Vérifier les changements d'appareil
            return $this->checkDeviceChanges($request);
        }

        // Stocker l'URL demandée
        if (!$request->is('two-factor/*')) {
            Session::put('two_factor_intended', $request->fullUrl());
        }

        // Détecter les changements d'appareil
        $this->detectDeviceChanges($request);

        return false;
    }

    /**
     * Détecter les changements d'appareil
     */
    private function detectDeviceChanges(Request $request): void
    {
        $agent = new Agent();
        $currentDevice = [
            'ip' => $request->ip(),
            'browser' => $agent->browser(),
            'platform' => $agent->platform(),
        ];

        $storedDevice = Session::get('two_factor_verified_device');

        if ($storedDevice) {
            if ($storedDevice['ip'] !== $currentDevice['ip']) {
                Session::put('two_factor_ip_changed', true);
                Session::put('two_factor_previous_ip', $storedDevice['ip']);
                Session::put('two_factor_new_ip', $currentDevice['ip']);
            }
            if ($storedDevice['browser'] !== $currentDevice['browser']) {
                Session::put('two_factor_browser_changed', true);
                Session::put('two_factor_previous_browser', $storedDevice['browser']);
                Session::put('two_factor_new_browser', $currentDevice['browser']);
            }
            if ($storedDevice['platform'] !== $currentDevice['platform']) {
                Session::put('two_factor_platform_changed', true);
                Session::put('two_factor_previous_platform', $storedDevice['platform']);
                Session::put('two_factor_new_platform', $currentDevice['platform']);
            }
        }

        // Marquer qu'une vérification est nécessaire
        Session::put('two_factor_requires_verification', true);
    }

    /**
     * Vérifier les changements d'appareil
     */
    private function checkDeviceChanges(Request $request): bool
    {
        $agent = new Agent();
        $currentDevice = [
            'ip' => $request->ip(),
            'browser' => $agent->browser(),
            'platform' => $agent->platform(),
        ];

        $storedDevice = Session::get('two_factor_verified_device');

        if (!$storedDevice) {
            return true; // Pas d'appareil stocké
        }

        // Vérifier les changements
        $hasChanges = false;

        if ($storedDevice['ip'] !== $currentDevice['ip']) {
            Session::put('two_factor_ip_changed', true);
            Session::put('two_factor_previous_ip', $storedDevice['ip']);
            Session::put('two_factor_new_ip', $currentDevice['ip']);
            $hasChanges = true;
        }
        if ($storedDevice['browser'] !== $currentDevice['browser']) {
            Session::put('two_factor_browser_changed', true);
            Session::put('two_factor_previous_browser', $storedDevice['browser']);
            Session::put('two_factor_new_browser', $currentDevice['browser']);
            $hasChanges = true;
        }
        if ($storedDevice['platform'] !== $currentDevice['platform']) {
            Session::put('two_factor_platform_changed', true);
            Session::put('two_factor_previous_platform', $storedDevice['platform']);
            Session::put('two_factor_new_platform', $currentDevice['platform']);
            $hasChanges = true;
        }

        if ($hasChanges) {
            Session::put('two_factor_detected_changes', true);
            Session::put('two_factor_requires_verification', true);

            // Stocker l'URL demandée
            if (!$request->is('two-factor/*')) {
                Session::put('two_factor_intended', $request->fullUrl());
            }

            return false; // Nécessite une nouvelle vérification
        }

        return true; // Pas de changements, continuer
    }
}
