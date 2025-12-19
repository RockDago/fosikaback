<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes, Notifiable;

    // ==================== RÔLES & PERMISSIONS ====================
    const ROLES = [
        'admin'         => 'Administrateur',
        'agent'         => 'Agent',
        'investigateur' => 'Investigateur',
    ];

    const PERMISSIONS = [
        'admin' => [
            'dashboard.view', 'users.manage', 'users.view', 'users.create', 'users.edit', 'users.delete',
            'reports.manage', 'reports.view_all', 'reports.create', 'reports.edit', 'reports.delete', 'reports.assign',
            'analytics.view', 'settings.manage', 'audit.view', 'notifications.manage',
        ],
        'agent' => [
            'dashboard.view', 'reports.view_assigned', 'reports.create', 'reports.update_assigned',
            'files.upload', 'profile.view', 'profile.edit', 'notifications.view',
        ],
        'investigateur' => [
            'dashboard.view', 'reports.view_assigned', 'investigations.manage', 'investigations.update_status',
            'investigations.add_notes', 'files.upload', 'profile.view', 'profile.edit', 'notifications.view',
        ],
    ];

    // ==================== FILLABLE ====================
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
        // Nouveaux champs pour la 2FA
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_code',
        'two_factor_code_expires_at',
        'two_factor_recovery_codes',
        'two_factor_remember_token',
        'two_factor_remember_expires_at',
    ];

    // ==================== HIDDEN & CASTS ====================
    protected $hidden = [
        'password',
        'remember_token',
        'session_id',
        'two_factor_secret',
        'two_factor_code',
        'two_factor_recovery_codes',
        'two_factor_remember_token',
    ];

    protected $casts = [
        'email_verified_at'           => 'datetime',
        'last_login_at'               => 'datetime',
        'two_factor_code_expires_at'  => 'datetime',
        'two_factor_remember_expires_at' => 'datetime',
        'statut'                      => 'boolean',
        'two_factor_enabled'          => 'boolean',
        'responsabilites'             => 'array',
        'specialisations'             => 'array',
        'two_factor_recovery_codes'   => 'array',
    ];

    protected $appends = [
        'full_name',
        'avatar_url',
        'formatted_role',
        'initials',
        'permissions_list',
    ];

    // ==================== MUTATOR PASSWORD ====================
    public function setPasswordAttribute($value): void
    {
        if ($value === null) {
            return;
        }

        if (Hash::needsRehash($value) || !str_starts_with($value, '$2y$')) {
            $this->attributes['password'] = Hash::make($value);
        } else {
            $this->attributes['password'] = $value;
        }
    }

    // ==================== ACCESSORS ====================
    public function getFullNameAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            return trim("{$this->first_name} {$this->last_name}");
        }
        return $this->name ?? $this->email;
    }

    public function getAvatarUrlAttribute(): string
    {
        if (!$this->avatar) {
            return $this->generateDefaultAvatar();
        }

        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
            return $this->avatar;
        }

        return Storage::disk('public')->url($this->avatar);
    }

    public function getFormattedRoleAttribute(): string
    {
        return self::ROLES[$this->role] ?? ucfirst($this->role);
    }

    public function getInitialsAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            return strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1));
        }
        return strtoupper(substr($this->name ?? 'U', 0, 2));
    }

    public function getPermissionsListAttribute(): array
    {
        return self::PERMISSIONS[$this->role] ?? [];
    }

    // ==================== RÔLES HELPERS ====================
    public function isAdmin(): bool         { return $this->role === 'admin'; }
    public function isAgent(): bool         { return $this->role === 'agent'; }
    public function isInvestigateur(): bool { return $this->role === 'investigateur'; }
    public function isActive(): bool        { return (bool) $this->statut; }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, self::PERMISSIONS[$this->role] ?? []);
    }

    // ==================== 2FA MÉTHODES ====================
    /**
     * Générer un code 2FA (15 minutes)
     */
    public function generateTwoFactorCode(): string
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->two_factor_code = Hash::make($code);
        $this->two_factor_code_expires_at = now()->addMinutes(15);
        $this->save();

        return $code;
    }

    /**
     * Vérifier et activer la 2FA
     */
    public function verifyAndEnableTwoFactor(string $code): bool
    {
        // Vérifier si le code est expiré
        if (!$this->two_factor_code_expires_at || now()->gt($this->two_factor_code_expires_at)) {
            return false;
        }

        // Vérifier si le code existe
        if (!$this->two_factor_code) {
            return false;
        }

        // Vérifier le code
        if (!Hash::check($code, $this->two_factor_code)) {
            return false;
        }

        // Activer la 2FA
        $this->two_factor_enabled = true;
        $this->two_factor_secret = Str::random(32);

        // Générer des codes de récupération
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = Str::random(10) . '-' . Str::random(10);
        }

        $this->two_factor_recovery_codes = $recoveryCodes;
        $this->two_factor_code = null;
        $this->two_factor_code_expires_at = null;
        $this->save();

        return true;
    }

    /**
     * Vérifier si le code 2FA est expiré
     */
    public function isTwoFactorCodeExpired(): bool
    {
        if (!$this->two_factor_code_expires_at) {
            return true;
        }
        return now()->gt($this->two_factor_code_expires_at);
    }

    /**
     * Vérifier un code 2FA pour la connexion
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        if (!$this->two_factor_enabled) {
            return true; // Pas besoin de 2FA si désactivé
        }

        if (!$this->two_factor_code_expires_at || now()->gt($this->two_factor_code_expires_at)) {
            return false;
        }

        if (!$this->two_factor_code) {
            return false;
        }

        return Hash::check($code, $this->two_factor_code);
    }

    /**
     * Vérifier un code de récupération
     */
    public function verifyRecoveryCode(string $code): bool
    {
        $recoveryCodes = $this->two_factor_recovery_codes ?? [];

        $index = array_search($code, $recoveryCodes);
        if ($index !== false) {
            // Retirer le code utilisé
            unset($recoveryCodes[$index]);
            $this->two_factor_recovery_codes = array_values($recoveryCodes);
            $this->save();
            return true;
        }

        return false;
    }

    /**
     * Désactiver la 2FA
     */
    public function disableTwoFactor(): bool
    {
        $this->two_factor_enabled = false;
        $this->two_factor_secret = null;
        $this->two_factor_code = null;
        $this->two_factor_code_expires_at = null;
        $this->two_factor_recovery_codes = null;
        $this->two_factor_remember_token = null;
        $this->two_factor_remember_expires_at = null;
        $this->save();

        return true;
    }

    /**
     * Générer de nouveaux codes de récupération
     */
    public function generateNewRecoveryCodes(): array
    {
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = Str::random(10) . '-' . Str::random(10);
        }

        $this->two_factor_recovery_codes = $recoveryCodes;
        $this->save();

        return $recoveryCodes;
    }

    /**
     * Vérifier si l'utilisateur a activé la 2FA
     */
    public function hasTwoFactorEnabled(): bool
    {
        return (bool) $this->two_factor_enabled;
    }

    /**
     * Générer un token "remember me" pour la 2FA (30 jours)
     */
    public function generateTwoFactorRememberToken(): string
    {
        $token = Str::random(60);

        $this->two_factor_remember_token = Hash::make($token);
        $this->two_factor_remember_expires_at = now()->addDays(30);
        $this->save();

        return $token;
    }

    /**
     * Vérifier un token "remember me"
     */
    public function verifyTwoFactorRememberToken(string $token): bool
    {
        if (!$this->two_factor_remember_token || !$this->two_factor_remember_expires_at) {
            return false;
        }

        if (now()->gt($this->two_factor_remember_expires_at)) {
            $this->clearTwoFactorRememberToken();
            return false;
        }

        return Hash::check($token, $this->two_factor_remember_token);
    }

    /**
     * Effacer le token "remember me"
     */
    public function clearTwoFactorRememberToken(): void
    {
        $this->two_factor_remember_token = null;
        $this->two_factor_remember_expires_at = null;
        $this->save();
    }

    // ==================== AVATAR PAR DÉFAUT ====================
    private function generateDefaultAvatar(): string
    {
        $initials = $this->initials;
        $bg = match ($this->role) {
            'admin'         => '4f46e5',
            'agent'         => '10b981',
            'investigateur' => '8b5cf6',
            default         => '6b7280',
        };

        return "https://ui-avatars.com/api/?name=" . urlencode($initials)
            . "&background={$bg}&color=fff&size=128&bold=true";
    }

    // ==================== DONNÉES PROFIL POUR FRONTEND ====================
    public function getProfileData(): array
    {
        return [
            'id'                       => $this->id,
            'name'                     => $this->name,
            'first_name'               => $this->first_name,
            'last_name'                => $this->last_name,
            'email'                    => $this->email,
            'phone'                    => $this->phone ?? $this->telephone,
            'avatar'                   => $this->avatar_url,
            'username'                 => $this->username,
            'role'                     => $this->role,
            'formatted_role'           => $this->formatted_role,
            'departement'              => $this->departement,
            'adresse'                  => $this->adresse,
            'responsabilites'          => $this->responsabilites,
            'specialisations'          => $this->specialisations,
            'statut'                   => $this->statut,
            'initials'                 => $this->initials,
            'permissions'              => $this->permissions_list,
            'two_factor_enabled'       => $this->two_factor_enabled,
            'has_recovery_codes'       => !empty($this->two_factor_recovery_codes),
        ];
    }

    // ==================== SCOPES ====================
    public function scopeWithTwoFactorEnabled($query)
    {
        return $query->where('two_factor_enabled', true);
    }

    public function scopeActive($query)
    {
        return $query->where('statut', true);
    }
}
