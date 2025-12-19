<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Profile extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'telephone',
        'adresse',
        'departement',
        'username',
        'password',
        'role',
        'responsabilites',
        'specialisations',
        'statut',
        'avatar',
        'session_id',
        'last_login_at',
        'last_login_ip',
        'email_verified_at',

        // Nouveaux champs pour la vérification email
        'email_verification_code',
        'email_verification_code_expires_at',

        // Nouveaux champs pour la 2FA
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_code',
        'two_factor_code_expires_at',
        'two_factor_recovery_codes',

        // Champs de sécurité supplémentaires
        'login_attempts',
        'last_login_attempt_at',
        'account_locked_until',

        // Champ pour les sessions actives
        'active_sessions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'session_id',
        'two_factor_secret',
        'two_factor_code',
        'two_factor_recovery_codes',
        'email_verification_code',
        'active_sessions',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'last_login_attempt_at' => 'datetime',
        'account_locked_until' => 'datetime',
        'email_verification_code_expires_at' => 'datetime',
        'two_factor_code_expires_at' => 'datetime',
        'specialisations' => 'array',
        'statut' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'two_factor_recovery_codes' => 'array',
        'login_attempts' => 'integer',
        'active_sessions' => 'array',
    ];

    protected $appends = [
        'full_name',
        'avatar_url',
        'formatted_role',
        'is_email_verified',
        'is_2fa_enabled',
        'is_account_locked',
        'has_pending_email_verification',
        'has_pending_2fa_verification',
        'profile_completion_percentage',
        'security_level',
    ];

    /**
     * Get the user's full name
     */
    public function getFullNameAttribute()
    {
        if ($this->first_name && $this->last_name) {
            return trim($this->first_name . ' ' . $this->last_name);
        }
        return $this->name ?? '';
    }

    /**
     * Get avatar URL with fallback to default
     */
    public function getAvatarUrlAttribute()
    {
        if (!$this->avatar) {
            return $this->generateDefaultAvatar();
        }

        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
            return $this->avatar;
        }

        if (Storage::disk('public')->exists($this->avatar)) {
            return Storage::disk('public')->url($this->avatar);
        }

        if (strpos($this->avatar, 'avatars/') !== false) {
            return Storage::url($this->avatar);
        }

        return asset('storage/' . $this->avatar);
    }

    /**
     * Get formatted role name
     */
    public function getFormattedRoleAttribute()
    {
        $roles = [
            'admin' => 'Administrateur',
            'agent' => 'Agent',
            'investigateur' => 'Investigateur',
            'superviseur' => 'Superviseur',
            'utilisateur' => 'Utilisateur'
        ];

        return $roles[strtolower($this->role)] ?? ucfirst($this->role);
    }

    /**
     * Check if email is verified
     */
    public function getIsEmailVerifiedAttribute()
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Check if 2FA is enabled
     */
    public function getIs2faEnabledAttribute()
    {
        return (bool) $this->two_factor_enabled;
    }

    /**
     * Check if account is locked
     */
    public function getIsAccountLockedAttribute()
    {
        if (!$this->account_locked_until) {
            return false;
        }
        return now()->lt($this->account_locked_until);
    }

    /**
     * Check if there's a pending email verification
     */
    public function getHasPendingEmailVerificationAttribute()
    {
        return !empty($this->email_verification_code) &&
            !$this->isEmailVerificationCodeExpired();
    }

    /**
     * Check if there's a pending 2FA verification
     */
    public function getHasPending2faVerificationAttribute()
    {
        return !empty($this->two_factor_code) &&
            !$this->isTwoFactorCodeExpired();
    }

    /**
     * Calculate profile completion percentage
     */
    public function getProfileCompletionPercentageAttribute()
    {
        $fields = [
            'first_name' => 10,
            'last_name' => 10,
            'email' => 15,
            'email_verified_at' => 20,
            'phone' => 10,
            'avatar' => 10,
            'departement' => 10,
            'specialisations' => 5,
            'responsabilites' => 5,
            'two_factor_enabled' => 5,
        ];

        $completed = 0;
        $total = array_sum($fields);

        foreach ($fields as $field => $weight) {
            if ($field === 'email_verified_at') {
                if ($this->email_verified_at) $completed += $weight;
            } elseif ($field === 'two_factor_enabled') {
                if ($this->two_factor_enabled) $completed += $weight;
            } elseif (!empty($this->{$field})) {
                $completed += $weight;
            }
        }

        return min(100, round(($completed / $total) * 100));
    }

    /**
     * Get security level (Low, Medium, High)
     */
    public function getSecurityLevelAttribute()
    {
        $score = 0;

        if ($this->email_verified_at) $score += 1;
        if ($this->two_factor_enabled) $score += 2;
        if (strlen($this->password) >= 12) $score += 1;
        if ($this->last_login_at && now()->diffInDays($this->last_login_at) < 30) $score += 1;

        if ($score >= 4) return 'high';
        if ($score >= 2) return 'medium';
        return 'low';
    }

    /**
     * Generate default avatar URL
     */
    private function generateDefaultAvatar()
    {
        $initials = strtoupper(
            substr($this->first_name ?? '', 0, 1) .
            substr($this->last_name ?? '', 0, 1)
        );

        if (empty($initials) || strlen($initials) < 2) {
            $initials = substr($this->name ?? 'U', 0, 2);
        }

        // Nettoyer les caractères spéciaux
        $initials = preg_replace('/[^A-Z]+/', '', $initials);

        if (empty($initials) || strlen($initials) < 2) {
            $initials = 'U';
        }

        $background = '6b7280';

        return "https://ui-avatars.com/api/?name=" .
            urlencode($initials) .
            "&background={$background}&color=fff&size=128&bold=true&format=svg";
    }

    /**
     * Hash password automatically
     */
    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['password'] = Hash::make($value);
        }
    }

    /**
     * Generate email verification code
     */
    public function generateEmailVerificationCode()
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->email_verification_code = Hash::make($code);
        $this->email_verification_code_expires_at = now()->addHour();
        $this->save();

        return $code;
    }

    /**
     * Verify email verification code
     */
    public function verifyEmailCode($code)
    {
        if ($this->isEmailVerificationCodeExpired()) {
            return false;
        }

        if (!Hash::check($code, $this->email_verification_code)) {
            return false;
        }

        // Mark email as verified
        $this->email_verified_at = now();
        $this->email_verification_code = null;
        $this->email_verification_code_expires_at = null;
        $this->save();

        return true;
    }

    /**
     * Check if email verification code is expired
     */
    public function isEmailVerificationCodeExpired()
    {
        if (!$this->email_verification_code_expires_at) {
            return true;
        }
        return now()->gt($this->email_verification_code_expires_at);
    }

    /**
     * Generate 2FA verification code
     */
    public function generateTwoFactorCode()
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->two_factor_code = Hash::make($code);
        $this->two_factor_code_expires_at = now()->addMinutes(10);
        $this->save();

        return $code;
    }

    /**
     * Verify 2FA code and enable 2FA
     */
    public function verifyAndEnableTwoFactor($code)
    {
        if ($this->isTwoFactorCodeExpired()) {
            return false;
        }

        if (!Hash::check($code, $this->two_factor_code)) {
            return false;
        }

        // Enable 2FA
        $this->two_factor_enabled = true;
        $this->two_factor_secret = Str::random(32);
        $this->two_factor_code = null;
        $this->two_factor_code_expires_at = null;

        // Generate recovery codes
        $this->generateRecoveryCodes();

        $this->save();

        return true;
    }

    /**
     * Check if 2FA code is expired
     */
    public function isTwoFactorCodeExpired()
    {
        if (!$this->two_factor_code_expires_at) {
            return true;
        }
        return now()->gt($this->two_factor_code_expires_at);
    }

    /**
     * Disable 2FA
     */
    public function disableTwoFactor()
    {
        $this->two_factor_enabled = false;
        $this->two_factor_secret = null;
        $this->two_factor_code = null;
        $this->two_factor_code_expires_at = null;
        $this->two_factor_recovery_codes = null;
        $this->save();
    }

    /**
     * Generate recovery codes for 2FA
     */
    public function generateRecoveryCodes($count = 8)
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(10));
        }

        $this->two_factor_recovery_codes = $codes;
        $this->save();

        return $codes;
    }

    /**
     * Verify recovery code
     */
    public function verifyRecoveryCode($code)
    {
        if (empty($this->two_factor_recovery_codes)) {
            return false;
        }

        $index = array_search($code, $this->two_factor_recovery_codes);

        if ($index !== false) {
            // Remove used code
            unset($this->two_factor_recovery_codes[$index]);
            $this->two_factor_recovery_codes = array_values($this->two_factor_recovery_codes);
            $this->save();
            return true;
        }

        return false;
    }

    /**
     * Increment login attempts and lock account if necessary
     */
    public function incrementLoginAttempts()
    {
        $this->login_attempts++;
        $this->last_login_attempt_at = now();

        // Lock account after 5 failed attempts
        if ($this->login_attempts >= 5) {
            $this->account_locked_until = now()->addMinutes(30);
        }

        $this->save();
    }

    /**
     * Reset login attempts
     */
    public function resetLoginAttempts()
    {
        $this->login_attempts = 0;
        $this->last_login_attempt_at = null;
        $this->account_locked_until = null;
        $this->save();
    }

    /**
     * Record successful login
     */
    public function recordSuccessfulLogin($ipAddress)
    {
        $this->last_login_at = now();
        $this->last_login_ip = $ipAddress;
        $this->resetLoginAttempts();
        $this->save();
    }

    /**
     * Add active session
     */
    public function addActiveSession($sessionId, $data)
    {
        $sessions = $this->active_sessions ?? [];
        $sessions[$sessionId] = [
            'id' => $sessionId,
            'ip' => $data['ip'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'last_activity' => now(),
            'created_at' => now(),
        ];

        $this->active_sessions = $sessions;
        $this->save();
    }

    /**
     * Remove active session
     */
    public function removeActiveSession($sessionId)
    {
        $sessions = $this->active_sessions ?? [];

        if (isset($sessions[$sessionId])) {
            unset($sessions[$sessionId]);
            $this->active_sessions = $sessions;
            $this->save();
        }
    }

    /**
     * Cleanup expired sessions
     */
    public function cleanupExpiredSessions($expiryHours = 24)
    {
        $sessions = $this->active_sessions ?? [];
        $now = now();

        foreach ($sessions as $sessionId => $session) {
            if ($now->diffInHours($session['last_activity']) > $expiryHours) {
                unset($sessions[$sessionId]);
            }
        }

        $this->active_sessions = $sessions;
        $this->save();
    }

    /**
     * Get profile data for API response
     */
    public function getProfileData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone ?? $this->telephone,
            'adresse' => $this->adresse,
            'departement' => $this->departement,
            'username' => $this->username,
            'role' => $this->role,
            'responsabilites' => $this->responsabilites,
            'specialisations' => $this->specialisations,
            'statut' => $this->statut,
            'avatar' => $this->avatar,
            'avatar_url' => $this->avatar_url,
            'formatted_role' => $this->formatted_role,
            'last_login_at' => $this->last_login_at,
            'last_login_ip' => $this->last_login_ip,
            'email_verified_at' => $this->email_verified_at,

            // Security information
            'is_email_verified' => $this->is_email_verified,
            'is_2fa_enabled' => $this->is_2fa_enabled,
            'is_account_locked' => $this->is_account_locked,
            'has_pending_email_verification' => $this->has_pending_email_verification,
            'has_pending_2fa_verification' => $this->has_pending_2fa_verification,
            'profile_completion_percentage' => $this->profile_completion_percentage,
            'security_level' => $this->security_level,

            // Additional info
            'login_attempts' => $this->login_attempts,
            'account_locked_until' => $this->account_locked_until,
            'active_sessions_count' => count($this->active_sessions ?? []),

            // For 2FA setup
            'has_recovery_codes' => !empty($this->two_factor_recovery_codes),
            'recovery_codes_count' => count($this->two_factor_recovery_codes ?? []),
        ];
    }

    /**
     * Get minimal profile data (for public display)
     */
    public function getPublicProfileData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'avatar_url' => $this->avatar_url,
            'formatted_role' => $this->formatted_role,
            'departement' => $this->departement,
            'specialisations' => $this->specialisations,
        ];
    }

    /**
     * Get security audit data
     */
    public function getSecurityAuditData(): array
    {
        return [
            'email_verified' => !is_null($this->email_verified_at),
            'email_verified_at' => $this->email_verified_at,
            'two_factor_enabled' => $this->two_factor_enabled,
            'two_factor_enabled_at' => $this->two_factor_enabled ? now() : null,
            'last_password_change' => $this->updated_at, // Vous pourriez vouloir un champ séparé pour cela
            'last_login' => $this->last_login_at,
            'last_login_ip' => $this->last_login_ip,
            'failed_login_attempts' => $this->login_attempts,
            'account_lock_status' => $this->is_account_locked,
            'account_locked_until' => $this->account_locked_until,
            'active_sessions' => $this->active_sessions ?? [],
            'password_strength' => $this->estimatePasswordStrength(),
            'security_recommendations' => $this->getSecurityRecommendations(),
        ];
    }

    /**
     * Estimate password strength
     */
    private function estimatePasswordStrength(): string
    {
        // Note: Ceci est une estimation basique
        // En production, vous voudriez analyser réellement le mot de passe
        if ($this->created_at->diffInDays(now()) > 180) {
            return 'weak'; // Mot de passe ancien
        }

        return 'medium'; // Par défaut
    }

    /**
     * Get security recommendations
     */
    private function getSecurityRecommendations(): array
    {
        $recommendations = [];

        if (!$this->email_verified_at) {
            $recommendations[] = 'Vérifiez votre adresse email';
        }

        if (!$this->two_factor_enabled) {
            $recommendations[] = 'Activez la double authentification (2FA)';
        }

        if ($this->created_at->diffInDays(now()) > 180) {
            $recommendations[] = 'Changez votre mot de passe (trop ancien)';
        }

        if (empty($this->two_factor_recovery_codes) && $this->two_factor_enabled) {
            $recommendations[] = 'Générez de nouveaux codes de récupération 2FA';
        }

        if (count($this->active_sessions ?? []) > 5) {
            $recommendations[] = 'Trop de sessions actives, revoyez vos connexions';
        }

        return $recommendations;
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('statut', true)
            ->whereNull('account_locked_until')
            ->orWhere('account_locked_until', '<', now());
    }

    /**
     * Scope for users with verified email
     */
    public function scopeEmailVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Scope for users with 2FA enabled
     */
    public function scopeTwoFactorEnabled($query)
    {
        return $query->where('two_factor_enabled', true);
    }

    /**
     * Scope for locked accounts
     */
    public function scopeLocked($query)
    {
        return $query->whereNotNull('account_locked_until')
            ->where('account_locked_until', '>', now());
    }

    /**
     * Check if user can perform action (not locked, etc.)
     */
    public function canPerformAction($action = null): bool
    {
        if ($this->is_account_locked) {
            return false;
        }

        if (!$this->statut) {
            return false;
        }

        // Actions spécifiques nécessitant une vérification email
        $requireEmailVerification = ['update_profile', 'change_password', 'enable_2fa'];

        if (in_array($action, $requireEmailVerification) && !$this->email_verified_at) {
            return false;
        }

        return true;
    }
}
