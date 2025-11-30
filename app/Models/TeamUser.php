<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;

class TeamUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'team_users';

    protected $fillable = [
        'nom_complet',
        'first_name',
        'last_name',
        'email',
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
        'last_login_at',
        'email_verified_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'statut' => 'boolean',
        'specialisations' => 'array',
    ];

    // ✅ AJOUT: Attributs calculés pour une meilleure compatibilité
    protected $appends = [
        'avatar_url',
        'full_name',
        'name',
        'phone',
        'telephone_formatted',
        'specialisations_list',
        'formatted_role'
    ];

    // Scopes pour les différents rôles
    public function scopeAgents($query)
    {
        return $query->where('role', 'Agent');
    }

    public function scopeInvestigateurs($query)
    {
        return $query->where('role', 'Investigateur');
    }

    public function scopeAdministrateurs($query)
    {
        return $query->where('role', 'Admin');
    }

    public function scopeActifs($query)
    {
        return $query->where('statut', true);
    }

    // ✅ CORRIGÉ: Accesseur pour l'avatar - Gestion améliorée
    public function getAvatarUrlAttribute()
    {
        if (!$this->avatar) {
            return null;
        }
        
        // Si c'est déjà une URL complète
        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
            return $this->avatar;
        }
        
        // Si c'est un chemin de stockage
        if (str_starts_with($this->avatar, 'avatars/')) {
            return Storage::disk('public')->url($this->avatar);
        }
        
        // Pour la compatibilité avec asset()
        return asset('storage/' . $this->avatar);
    }

    // ✅ Accesseur pour le nom complet
    public function getFullNameAttribute()
    {
        if ($this->first_name && $this->last_name) {
            return $this->first_name . ' ' . $this->last_name;
        }
        return $this->nom_complet;
    }

    // ✅ Accesseur pour la compatibilité (utilisé dans le frontend)
    public function getNameAttribute()
    {
        return $this->nom_complet;
    }

    // ✅ Accesseur pour la compatibilité (utilisé dans le frontend)
    public function getPhoneAttribute()
    {
        return $this->telephone;
    }

    // ✅ Accesseur pour le téléphone formaté
    public function getTelephoneFormattedAttribute()
    {
        if (!$this->telephone) {
            return null;
        }
        
        $phone = preg_replace('/\D/', '', $this->telephone);
        
        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            return preg_replace('/(\d{2})(\d{2})(\d{3})(\d{2})/', '$1 $2 $3 $4', $phone);
        } elseif (strlen($phone) === 12 && str_starts_with($phone, '261')) {
            return preg_replace('/(\d{3})(\d{2})(\d{2})(\d{3})(\d{2})/', '$1 $2 $3 $4 $5', $phone);
        }
        
        return $this->telephone;
    }

    // ✅ Accesseur pour les spécialisations formatées
    public function getSpecialisationsListAttribute()
    {
        if (empty($this->specialisations)) {
            return 'Aucune spécialisation';
        }
        
        return is_array($this->specialisations) 
            ? implode(', ', $this->specialisations)
            : $this->specialisations;
    }

    // ✅ Accesseur pour le rôle formaté
    public function getFormattedRoleAttribute()
    {
        $roles = [
            'Admin' => 'Administrateur',
            'Agent' => 'Agent',
            'Investigateur' => 'Investigateur'
        ];
        
        return $roles[$this->role] ?? $this->role;
    }

    // ✅ Méthodes de vérification des rôles
    public function isAgent()
    {
        return $this->role === 'Agent';
    }

    public function isInvestigateur()
    {
        return $this->role === 'Investigateur';
    }

    public function isAdmin()
    {
        return $this->role === 'Admin';
    }

    // ✅ Méthode pour mettre à jour la dernière connexion
    public function updateLastLogin()
    {
        $this->update([
            'last_login_at' => now()
        ]);
    }

    // ✅ Vérifier si l'utilisateur est actif
    public function isActive()
    {
        return $this->statut === true;
    }

    // ✅ Méthode pour obtenir les initiales (utilisée dans l'avatar)
    public function getInitialsAttribute()
    {
        if ($this->first_name && $this->last_name) {
            return strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1));
        }
        
        // Fallback sur le nom complet
        $names = explode(' ', $this->nom_complet);
        if (count($names) >= 2) {
            return strtoupper(substr($names[0], 0, 1) . substr($names[1], 0, 1));
        }
        
        return strtoupper(substr($this->nom_complet, 0, 2));
    }

    // ✅ Méthode pour vérifier les permissions basiques
    public function hasPermission($permission)
    {
        // Permissions basées sur le rôle
        $permissions = [
            'Admin' => ['view_dashboard', 'manage_users', 'manage_reports', 'view_analytics'],
            'Investigateur' => ['view_dashboard', 'manage_reports', 'view_analytics'],
            'Agent' => ['view_dashboard', 'manage_reports']
        ];
        
        return in_array($permission, $permissions[$this->role] ?? []);
    }

    // ✅ Méthode pour obtenir les données de profil formatées
    public function getProfileData()
    {
        return [
            'id' => $this->id,
            'name' => $this->nom_complet,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->telephone,
            'avatar' => $this->avatar_url,
            'username' => $this->username,
            'role' => $this->role,
            'departement' => $this->departement,
            'adresse' => $this->adresse,
            'responsabilites' => $this->responsabilites,
            'specialisations' => $this->specialisations,
            'statut' => $this->statut,
            'full_name' => $this->full_name,
            'initials' => $this->initials,
            'formatted_role' => $this->formatted_role,
            'telephone_formatted' => $this->telephone_formatted,
        ];
    }

    // ✅ Override de la méthode pour la route de notification
    public function routeNotificationForMail($notification = null)
    {
        return $this->email;
    }

    // ✅ Scope pour rechercher par nom ou email
    public function scopeSearch($query, $search)
    {
        return $query->where('nom_complet', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
    }
}