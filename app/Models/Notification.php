<?php
// app/Models/Notification.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'titre',
        'message',
        'priority',
        'status',
        'reference_dossier',
        'metadata',
        'read_at',
        'user_id',
        'user_role',
        'target_roles',
        'expires_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'read_at' => 'datetime',
        'expires_at' => 'datetime',
        'target_roles' => 'array'
    ];

    // Constantes pour les types de notifications
    const TYPE_NOUVEAU_SIGNALEMENT = 'nouveau_signalement';
    const TYPE_SIGNALEMENT_URGENT = 'signalement_urgent';
    const TYPE_DOUBLON_DETECTE = 'doublon_detecte';
    const TYPE_STATUT_MODIFIE = 'statut_modifie';
    const TYPE_ENQUETE_ASSIGNEE = 'enquete_assignee';
    const TYPE_ENQUETE_TERMINEE = 'enquete_terminee';
    const TYPE_SYSTEM = 'system';
    const TYPE_USER_ACTIVITY = 'user_activity';

    // Constantes pour les priorités
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    // Constantes pour les statuts
    const STATUS_ACTIVE = 'active';
    const STATUS_READ = 'read';
    const STATUS_ARCHIVED = 'archived';
    const STATUS_EXPIRED = 'expired';

    /**
     * Scope pour les notifications actives
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Scope pour les notifications non lues
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at')
                    ->where('status', self::STATUS_ACTIVE)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Scope pour les notifications lues
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_READ);
    }

    /**
     * Scope pour les notifications archivées
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    /**
     * Scope pour les notifications expirées
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now())
                    ->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope pour les notifications par type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope pour les notifications par priorité
     */
    public function scopeOfPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope pour les notifications urgentes
     */
    public function scopeUrgent(Builder $query): Builder
    {
        return $query->where('priority', self::PRIORITY_URGENT)
                    ->orWhere('priority', self::PRIORITY_HIGH);
    }

    /**
     * Scope pour les notifications par rôle cible
     */
    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->where(function ($q) use ($role) {
            $q->whereNull('target_roles')
              ->orWhereJsonContains('target_roles', $role);
        });
    }

    /**
     * Scope pour les notifications par utilisateur
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereNull('user_id')
              ->orWhere('user_id', $userId);
        });
    }

    /**
     * Scope pour les notifications récentes
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope pour les notifications non expirées
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Marquer la notification comme lue
     */
    public function markAsRead(): bool
    {
        return $this->update([
            'read_at' => now(),
            'status' => self::STATUS_READ
        ]);
    }

    /**
     * Marquer la notification comme non lue
     */
    public function markAsUnread(): bool
    {
        return $this->update([
            'read_at' => null,
            'status' => self::STATUS_ACTIVE
        ]);
    }

    /**
     * Archiver la notification
     */
    public function archive(): bool
    {
        return $this->update(['status' => self::STATUS_ARCHIVED]);
    }

    /**
     * Vérifier si la notification est lue
     */
    public function isRead(): bool
    {
        return !is_null($this->read_at) && $this->status === self::STATUS_READ;
    }

    /**
     * Vérifier si la notification est non lue
     */
    public function isUnread(): bool
    {
        return is_null($this->read_at) && $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Vérifier si la notification est expirée
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Vérifier si la notification est urgente
     */
    public function isUrgent(): bool
    {
        return in_array($this->priority, [self::PRIORITY_URGENT, self::PRIORITY_HIGH]);
    }

    /**
     * Obtenir l'icône selon le type de notification
     */
    public function getIconAttribute(): string
    {
        return match($this->type) {
            self::TYPE_NOUVEAU_SIGNALEMENT => '📄',
            self::TYPE_SIGNALEMENT_URGENT => '🚨',
            self::TYPE_DOUBLON_DETECTE => '⚠️',
            self::TYPE_STATUT_MODIFIE => '🔄',
            self::TYPE_ENQUETE_ASSIGNEE => '🎯',
            self::TYPE_ENQUETE_TERMINEE => '✅',
            self::TYPE_SYSTEM => '⚙️',
            self::TYPE_USER_ACTIVITY => '👤',
            default => '🔔'
        };
    }

    /**
     * Obtenir la couleur selon la priorité
     */
    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            self::PRIORITY_URGENT => 'red',
            self::PRIORITY_HIGH => 'orange',
            self::PRIORITY_MEDIUM => 'blue',
            self::PRIORITY_LOW => 'gray',
            default => 'gray'
        };
    }

    /**
     * Obtenir le libellé du type
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            self::TYPE_NOUVEAU_SIGNALEMENT => 'Nouveau signalement',
            self::TYPE_SIGNALEMENT_URGENT => 'Signalement urgent',
            self::TYPE_DOUBLON_DETECTE => 'Doublon détecté',
            self::TYPE_STATUT_MODIFIE => 'Statut modifié',
            self::TYPE_ENQUETE_ASSIGNEE => 'Enquête assignée',
            self::TYPE_ENQUETE_TERMINEE => 'Enquête terminée',
            self::TYPE_SYSTEM => 'Système',
            self::TYPE_USER_ACTIVITY => 'Activité utilisateur',
            default => 'Notification'
        };
    }

    /**
     * Obtenir le temps écoulé depuis la création
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Vérifier si la notification est destinée à un rôle spécifique
     */
    public function isForRole(string $role): bool
    {
        if (empty($this->target_roles)) {
            return true; // Notification pour tous les rôles
        }

        return in_array($role, $this->target_roles);
    }

    /**
     * Définir les rôles cibles
     */
    public function setTargetRolesAttribute($value): void
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $this->attributes['target_roles'] = json_encode($value);
    }

    /**
     * Obtenir les rôles cibles
     */
    public function getTargetRolesAttribute($value): array
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }

        return $value ?? [];
    }

    /**
     * Relation avec l'utilisateur (si la notification est liée à un utilisateur spécifique)
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Relation avec le dossier (si la notification est liée à un dossier)
     */
    public function dossier()
    {
        return $this->belongsTo(\App\Models\Report::class, 'reference_dossier', 'reference');
    }

    /**
     * Méthode pour créer une notification rapidement
     */
    public static function createNotification(array $data): self
    {
        return self::create(array_merge([
            'priority' => self::PRIORITY_MEDIUM,
            'status' => self::STATUS_ACTIVE,
            'metadata' => [],
            'target_roles' => null,
        ], $data));
    }

    /**
     * Méthode pour nettoyer les notifications expirées
     */
    public static function cleanupExpired(): int
    {
        return self::expired()->update(['status' => self::STATUS_EXPIRED]);
    }

    /**
     * Méthode pour obtenir les statistiques des notifications
     */
    public static function getStats(?string $role = null): array
    {
        $query = self::query();

        if ($role) {
            $query->forRole($role);
        }

        return [
            'total' => $query->count(),
            'unread' => $query->clone()->unread()->count(),
            'read' => $query->clone()->read()->count(),
            'urgent' => $query->clone()->urgent()->count(),
            'active' => $query->clone()->active()->count(),
        ];
    }

    /**
     * Boot du modèle
     */
    protected static function boot()
    {
        parent::boot();

        // Nettoyer automatiquement les notifications expirées
        static::saving(function ($notification) {
            if ($notification->isExpired()) {
                $notification->status = self::STATUS_EXPIRED;
            }
        });
    }
}