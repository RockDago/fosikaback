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
        });

        static::created(function ($report) {
            try {
                // Créer les logs de workflow
                $report->initializeWorkflow();
                
                // Ne PAS créer de tracking pour les doublons
                if ($report->status !== 'doublon') {
                    // Vérifier si le tracking existe déjà
                    $existingTracking = \App\Models\Tracking::where('reference', $report->reference)->first();
                    
                    if (!$existingTracking) {
                        \App\Models\Tracking::create([
                            'reference' => $report->reference,
                            'status' => $report->status,
                            'last_update' => now()
                        ]);
                        
                        Log::info("Tracking créé pour {$report->reference}");
                    } else {
                        Log::info("Tracking déjà existant pour {$report->reference}");
                    }
                } else {
                    Log::info("Tracking non créé pour doublon {$report->reference}");
                }

                Log::info("Signalement {$report->reference} créé avec succès");
            } catch (\Exception $e) {
                Log::error("Erreur création workflow/tracking pour {$report->reference}: " . $e->getMessage());
            }
        });

        // Observer les changements de statut
        static::updated(function ($report) {
            // Si le statut devient doublon, supprimer le tracking
            if ($report->isDirty('status') && $report->status === 'doublon') {
                $tracking = \App\Models\Tracking::where('reference', $report->reference)->first();
                if ($tracking) {
                    $tracking->delete();
                    Log::info("Tracking supprimé pour doublon {$report->reference}");
                }
            }
            
            // Si le statut change d'un autre état, mettre à jour le tracking
            if ($report->isDirty('status') && $report->status !== 'doublon') {
                \App\Models\Tracking::updateOrCreate(
                    ['reference' => $report->reference],
                    [
                        'status' => $report->status,
                        'last_update' => now()
                    ]
                );
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
            // Créer un log de workflow dans la table dédiée
            try {
                \App\Models\WorkflowLog::create([
                    'report_id' => $this->id,
                    'step' => $stage['stage'],
                    'status' => $stage['status'],
                    'agent' => $stage['agent'],
                    'processed_at' => $stage['processed_at']
                ]);
            } catch (\Exception $e) {
                Log::warning("Erreur création WorkflowLog: " . $e->getMessage());
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
            'classifier' => []
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
