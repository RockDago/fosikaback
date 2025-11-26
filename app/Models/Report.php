<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Report extends Model
{
    protected $fillable = [
        'reference', 
        'type', 
        'name', 
        'email', 
        'phone', 
        'address',
        'category', 
        'description', 
        'files', 
        'ip_address',
        'country', 
        'region', 
        'city', 
        'status', 
        'workflow', 
        'accept_terms', 
        'accept_truth'
    ];

    protected $casts = [
        'files' => 'array',
        'workflow' => 'array',
        'accept_terms' => 'boolean',
        'accept_truth' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relations
     */
    public function workflowLogs()
    {
        return $this->hasMany(WorkflowLog::class);
    }

    public function tracking()
    {
        return $this->hasOne(Tracking::class, 'reference', 'reference');
    }

    /**
     * Boot method - événements du cycle de vie
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($report) {
            // Générer une référence unique
            $report->reference = self::generateReference();

            // Valeurs par défaut pour la localisation
            $report->country = 'Madagascar';
            $report->region = 'Analamanga';
            $report->city = 'Antananarivo';
        });

        static::created(function ($report) {
            try {
                // Créer les logs de workflow
                $report->initializeWorkflow();
                
                // Créer le tracking
                \App\Models\Tracking::create([
                    'reference' => $report->reference,
                    'status' => $report->status,
                    'current_stage' => 'DRSE',
                    'last_update' => now()
                ]);

                Log::info("Signalement {$report->reference} créé avec succès");
            } catch (\Exception $e) {
                Log::error("Erreur création workflow/tracking pour {$report->reference}: " . $e->getMessage());
            }
        });
    }

    /**
     * Initialiser le workflow du signalement
     */
    public function initializeWorkflow()
    {
        $stages = [
            [
                'stage' => 'DRSE',
                'status' => 'in_progress',
                'progress' => 33,
                'agent' => 'DAAQ / DRSE',
                'processed_at' => now()
            ],
            [
                'stage' => 'CAC',
                'status' => 'pending',
                'progress' => 0,
                'agent' => 'DAAQ / CAC / DAGI',
                'processed_at' => null
            ],
            [
                'stage' => 'BIANCO',
                'status' => 'pending',
                'progress' => 0,
                'agent' => 'DAAQ / BIANCO',
                'processed_at' => null
            ]
        ];

        $workflowData = [];

        foreach ($stages as $stage) {
            // Créer un log de workflow dans la table dédiée si elle existe
            try {
                \App\Models\WorkflowLog::create([
                    'report_id' => $this->id,
                    'stage' => $stage['stage'],
                    'status' => $stage['status'],
                    'progress' => $stage['progress'],
                    'processed_by' => $stage['agent'],
                    'processed_at' => $stage['processed_at']
                ]);
            } catch (\Exception $e) {
                Log::warning("WorkflowLog table may not exist: " . $e->getMessage());
            }

            // Stocker dans le champ JSON workflow
            $workflowData[strtolower($stage['stage'])] = [
                'date' => $stage['processed_at'] ? $stage['processed_at']->toDateTimeString() : null,
                'status' => $stage['status'],
                'progress' => $stage['progress'],
                'agent' => $stage['agent']
            ];
        }

        $this->update(['workflow' => $workflowData]);
    }

    /**
     * Générer une référence unique pour le signalement
     */
    public static function generateReference()
    {
        do {
            $reference = 'REF-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Obtenir le label du statut dans une langue donnée
     * ✅ SUPPORT de 'classifier'
     */
    public function getStatusLabel($language = 'fr')
    {
        $statuses = [
            'fr' => [
                'en_cours' => 'En cours',
                'finalise' => 'Finalisé',
                'doublon' => 'Doublon',
                'refuse' => 'Refusé',
                'classifier' => 'Classifié'
            ],
            'mg' => [
                'en_cours' => 'Mivoatra',
                'finalise' => 'Vita',
                'doublon' => 'Mitovy',
                'refuse' => 'Nolavina',
                'classifier' => 'Voasafidy'
            ]
        ];

        return $statuses[$language][$this->status] ?? $this->status;
    }

    /**
     * Obtenir les statuts possibles suivants selon le statut actuel
     * ✅ SUPPORT de 'classifier'
     */
    public function getNextPossibleStatus()
    {
        $transitions = [
            'en_cours' => ['finalise', 'doublon', 'refuse', 'classifier'],
            'finalise' => ['classifier'],
            'doublon' => ['en_cours'],
            'refuse' => ['en_cours'],
            'classifier' => [] // Un dossier classifié ne peut plus changer de statut
        ];

        return $transitions[$this->status] ?? [];
    }

    /**
     * Vérifier si le dossier peut être modifié
     * ✅ Les dossiers classifiés ne peuvent plus être modifiés
     */
    public function canBeModified()
    {
        return in_array($this->status, ['en_cours', 'doublon', 'refuse']);
    }

    /**
     * Vérifier si le dossier peut être supprimé (soft delete)
     */
    public function canBeDeleted()
    {
        return in_array($this->status, ['doublon', 'refuse', 'classifier']);
    }

    /**
     * Obtenir le label de la catégorie dans une langue donnée
     */
    public function getCategoryLabel($language = 'fr')
    {
        $categories = [
            'fr' => [
                'faux-diplomes' => 'Faux Diplômes',
                'Offre de formation irrégulière ( non habilité)' => 'Offre de formation irrégulière ( non habilité)',
                'recrutements-irreguliers' => 'Recrutements Irréguliers',
                'harcelement' => 'Harcèlement',
                'corruption' => 'Corruption',
                'divers' => 'Divers'
            ],
            'mg' => [
                'faux-diplomes' => 'Diplaoma Sandoka',
                'Offre de formation irrégulière ( non habilité)' => 'Tolotra fiofanana tsy ara-dalàna (tsy nahazoana alalana)',
                'recrutements-irreguliers' => 'Fandraisana olona tsy ara-dalàna',
                'harcelement' => 'Fanenjehana',
                'corruption' => 'Kolikoly',
                'divers' => 'Hafa'
            ]
        ];

        return $categories[$language][$this->category] ?? $this->category;
    }

    /**
     * Obtenir le label du type de signalement dans une langue donnée
     */
    public function getTypeLabel($language = 'fr')
    {
        $types = [
            'fr' => [
                'anonyme' => 'Anonyme',
                'identifie' => 'Identifié'
            ],
            'mg' => [
                'anonyme' => 'Tsy fantatra anarana',
                'identifie' => 'Fantatra anarana'
            ]
        ];

        return $types[$language][$this->type] ?? $this->type;
    }

    /**
     * Scopes pour faciliter les requêtes
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeAnonymous($query)
    {
        return $query->where('type', 'anonyme');
    }

    public function scopeIdentified($query)
    {
        return $query->where('type', 'identifie');
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByRegion($query, $region)
    {
        return $query->where('region', $region);
    }

    /**
     * Obtenir le nombre total de fichiers joints
     */
    public function getFilesCountAttribute()
    {
        return is_array($this->files) ? count($this->files) : 0;
    }

    /**
     * Vérifier si le signalement a des fichiers joints
     */
    public function hasFiles()
    {
        return !empty($this->files) && is_array($this->files) && count($this->files) > 0;
    }
}