<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'step',
        'status',
        'agent',
        'notes',
        'processed_at'
    ];

    protected $casts = [
        'processed_at' => 'datetime'
    ];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }

    public function getStatusLabel($language = 'fr')
    {
        $statuses = [
            'fr' => [
                'pending' => 'En attente',
                'in_progress' => 'En cours',
                'completed' => 'Terminé',
                'rejected' => 'Rejeté',
                'duplicate' => 'Doublon',
                'not_required' => 'Non requis'
            ],
            'mg' => [
                'pending' => 'Miandry',
                'in_progress' => 'Eo am-panorana',
                'completed' => 'Vita',
                'rejected' => 'Nolavina',
                'duplicate' => 'Mitovy',
                'not_required' => 'Tsy ilaina'
            ]
        ];

        return $statuses[$language][$this->status] ?? $this->status;
    }

    public function getStepLabel($language = 'fr')
    {
        $steps = [
            'fr' => [
                'drse' => 'DAAQ / DRSE',
                'cac' => 'CAC',
                'bianco' => 'Traitement BIANCO'
            ],
            'mg' => [
                'drse' => 'DAAQ / DRSE',
                'cac' => 'CAC',
                'bianco' => 'Fanodinana BIANCO'
            ]
        ];

        return $steps[$language][$this->step] ?? $this->step;
    }
}