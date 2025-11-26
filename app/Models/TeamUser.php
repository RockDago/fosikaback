<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class TeamUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'team_users';

    protected $fillable = [
        'nom_complet',
        'first_name', // AJOUTÉ: champ manquant
        'last_name',  // AJOUTÉ: champ manquant
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
        'avatar', // AJOUTÉ: champ manquant pour les avatars
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

    // SUPPRIMÉ: La relation role qui causait l'erreur
    // public function roleRelation()
    // {
    //     return $this->belongsTo(Role::class, 'role', 'code');
    // }

    // Scopes mis à jour pour utiliser directement la colonne 'role'
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

    // AJOUTÉ: Accesseur pour l'avatar
    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        return null;
    }

    // AJOUTÉ: Accesseur pour le nom complet
    public function getFullNameAttribute()
    {
        if ($this->first_name && $this->last_name) {
            return $this->first_name . ' ' . $this->last_name;
        }
        return $this->nom_complet;
    }

    // AJOUTÉ: Accesseur pour le nom d'affichage (compatibilité)
    public function getNameAttribute()
    {
        return $this->nom_complet;
    }

    // AJOUTÉ: Accesseur pour le téléphone (compatibilité)
    public function getPhoneAttribute()
    {
        return $this->telephone;
    }

    // Méthodes de vérification des permissions
    public function hasPermission($permission)
    {
        // Pour l'instant, pas de permissions système
        // Cette méthode sera implémentée plus tard si nécessaire
        return true;
    }

    public function isAgentSuivi()
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

    // Getters pour la compatibilité
    public function getTelephoneFormattedAttribute()
    {
        $phone = preg_replace('/\D/', '', $this->telephone);
        
        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            return preg_replace('/(\d{2})(\d{2})(\d{3})(\d{2})/', '$1 $2 $3 $4', $phone);
        } elseif (strlen($phone) === 12 && str_starts_with($phone, '261')) {
            return preg_replace('/(\d{3})(\d{2})(\d{2})(\d{3})(\d{2})/', '$1 $2 $3 $4 $5', $phone);
        }
        
        return $this->telephone;
    }

    public function getSpecialisationsListAttribute()
    {
        return is_array($this->specialisations) 
            ? implode(', ', $this->specialisations)
            : '';
    }

    // AJOUTÉ: Méthode pour mettre à jour la dernière connexion
    public function updateLastLogin()
    {
        $this->update([
            'last_login_at' => now()
        ]);
    }

    // AJOUTÉ: Vérifier si l'utilisateur est actif
    public function isActive()
    {
        return $this->statut === true;
    }

    // AJOUTÉ: Obtenir le rôle formaté
    public function getFormattedRoleAttribute()
    {
        $roles = [
            'Admin' => 'Administrateur',
            'Agent' => 'Agent',
            'Investigateur' => 'Investigateur'
        ];
        
        return $roles[$this->role] ?? $this->role;
    }
}