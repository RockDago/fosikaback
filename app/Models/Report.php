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
        'accept_truth',
          'assigned_to'// ✅ AJOUTER cette ligne
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
     /**
     * ✅ AJOUTER cette relation
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
                'agent' => 'DAAQ / CAC / DAJ',
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
    // Format date: jjmmyyyy (ex: 17122025)
    $dateStr = now()->format('dmY');

    // Prefix fixe demandé
    $fixedPrefix = "REF-{$dateStr}-FSK";

    // Chercher la dernière référence (peu importe la date) avec le motif REF-XXXXXXXX-FSK####
    // => compteur global qui ne reset jamais
    $lastReport = self::where('reference', 'LIKE', 'REF-%-FSK%')
        ->orderBy('reference', 'desc')
        ->lockForUpdate()
        ->first();

    $nextNumber = 1;

    if ($lastReport && $lastReport->reference) {
        // Extraire les 3 derniers chiffres (ou plus si tu changes la longueur)
        if (preg_match('/FSK(\d+)$/', $lastReport->reference, $matches)) {
            $lastNumber = (int) $matches[1];
            $nextNumber = $lastNumber + 1;
        }
    }

    // 3 chiffres: 001, 002, ...
    $formattedNumber = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

    return "{$fixedPrefix}{$formattedNumber}";
}


    /**
     * Générer un préfixe aléatoire (1 lettre + 1 chiffre)
     */
    private static function generateRandomPrefix()
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';

        return $letters[rand(0, 25)] . $numbers[rand(0, 9)];
    }


    /**
     * Obtenir le label du statut dans une langue donnée
     * ✅ SUPPORT de 'classifier'
     */
public function getStatusLabel($language = 'fr')
{
    $statuses = [
        'fr' => [
            'traitement_classification' => 'Traitement et Classification',
            'investigation' => 'Investigation',
            'transmis_autorite' => 'Transmis aux autorités compétentes',
            'refuse' => 'Refusé',
            'classifier' => 'Classifié'
        ],
        'mg' => [
            'traitement_classification' => 'Fanodinana sy Fanasokajiana',
            'investigation' => 'Fanadihadiana',
            'transmis_autorite' => 'Nalefa tany amin\'ny manam-pahefana',
            'refuse' => 'Nolavina',
            'classifier' => 'Voasokajy'
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

/**
 * Mettre à jour le workflow en fonction du statut simplifié
 */
/**
 * Mettre à jour le workflow en fonction du statut simplifié
 */
public function updateWorkflowFromStatus($newStatus)
{
    try {
        $workflow = $this->workflow ?? [];

        switch ($newStatus) {
            case 'traitement_classification':
                $workflow['drse'] = [
                    'date' => now()->toDateTimeString(),
                    'status' => 'in_progress',
                    'progress' => 33,
                    'agent' => 'DAAQ / DRSE'
                ];
                $workflow['cac'] = [
                    'date' => null,
                    'status' => 'pending',
                    'progress' => 0,
                    'agent' => 'DAAQ / CAC / DAJ'
                ];
                $workflow['bianco'] = [
                    'date' => null,
                    'status' => 'pending',
                    'progress' => 0,
                    'agent' => 'DAAQ / BIANCO'
                ];
                break;

            case 'investigation':
                $workflow['drse'] = [
                    'date' => $workflow['drse']['date'] ?? now()->toDateTimeString(),
                    'status' => 'completed',
                    'progress' => 100,
                    'agent' => 'DAAQ / DRSE'
                ];
                $workflow['cac'] = [
                    'date' => now()->toDateTimeString(),
                    'status' => 'in_progress',
                    'progress' => 66,
                    'agent' => 'DAAQ / CAC / DAJ'
                ];
                $workflow['bianco'] = [
                    'date' => null,
                    'status' => 'pending',
                    'progress' => 0,
                    'agent' => 'DAAQ / BIANCO'
                ];
                break;

            case 'transmis_autorite':
                $workflow['drse'] = [
                    'date' => $workflow['drse']['date'] ?? now()->toDateTimeString(),
                    'status' => 'completed',
                    'progress' => 100,
                    'agent' => 'DAAQ / DRSE'
                ];
                $workflow['cac'] = [
                    'date' => $workflow['cac']['date'] ?? now()->toDateTimeString(),
                    'status' => 'completed',
                    'progress' => 100,
                    'agent' => 'DAAQ / CAC / DAJ'
                ];
                $workflow['bianco'] = [
                    'date' => now()->toDateTimeString(),
                    'status' => 'in_progress',
                    'progress' => 66,
                    'agent' => 'DAAQ / BIANCO'
                ];
                break;

            case 'classifier':
                $workflow['drse'] = [
                    'date' => $workflow['drse']['date'] ?? now()->toDateTimeString(),
                    'status' => 'completed',
                    'progress' => 100,
                    'agent' => 'DAAQ / DRSE'
                ];
                $workflow['cac'] = [
                    'date' => $workflow['cac']['date'] ?? now()->toDateTimeString(),
                    'status' => 'completed',
                    'progress' => 100,
                    'agent' => 'DAAQ / CAC / DAJ'
                ];
                $workflow['bianco'] = [
                    'date' => now()->toDateTimeString(),
                    'status' => 'completed',
                    'progress' => 100,
                    'agent' => 'DAAQ / BIANCO'
                ];
                break;

            case 'refuse':
                $workflow['drse'] = [
                    'date' => now()->toDateTimeString(),
                    'status' => 'rejected',
                    'progress' => 0,
                    'agent' => 'DAAQ / DRSE'
                ];
                $workflow['cac'] = [
                    'date' => null,
                    'status' => 'not_required',
                    'progress' => 0,
                    'agent' => 'DAAQ / CAC / DAJ'
                ];
                $workflow['bianco'] = [
                    'date' => null,
                    'status' => 'not_required',
                    'progress' => 0,
                    'agent' => 'DAAQ / BIANCO'
                ];
                break;
        }

        // Mettre à jour uniquement le champ workflow sans toucher updated_at
        $this->workflow = $workflow;
        $this->save();

        // Mettre à jour aussi les WorkflowLogs
        $this->updateWorkflowLogs($newStatus);

        return true;

    } catch (\Exception $e) {
        Log::error("Erreur updateWorkflowFromStatus: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Mettre à jour les logs de workflow
 */
protected function updateWorkflowLogs($status)
{
    $workflow = $this->workflow;

    foreach ($workflow as $step => $data) {
        $log = $this->workflowLogs()->where('step', strtoupper($step))->first();

        if ($log) {
            $log->update([
                'status' => $data['status'],
                'processed_at' => $data['date'] ? now()->parse($data['date']) : null,
                'agent' => $data['agent']
            ]);
        } else {
            WorkflowLog::create([
                'report_id' => $this->id,
                'step' => strtoupper($step),
                'status' => $data['status'],
                'agent' => $data['agent'],
                'processed_at' => $data['date'] ? now()->parse($data['date']) : null
            ]);
        }
    }
}
}
