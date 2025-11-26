<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tracking extends Model
{
    use HasFactory;

    protected $table = 'trackings'; 

    protected $fillable = [
        'reference',
        'status',
        'notes',
        'last_update'
    ];

    protected $casts = [
        'last_update' => 'datetime'
    ];

    public function report()
    {
        return $this->belongsTo(Report::class, 'reference', 'reference');
    }

    public function getStatusLabel($language = 'fr')
    {
        $statuses = [
            'fr' => [
                'en_cours' => 'En cours',
                'finalise' => 'Finalisé',
                'doublon' => 'Doublon',
                'refuse' => 'Refusé'
            ],
            'mg' => [
                'en_cours' => 'Voaray',
                'finalise' => 'Vita',
                'doublon' => 'Mitovy',
                'refuse' => 'Nolavina'
            ]
        ];

        return $statuses[$language][$this->status] ?? $this->status;
    }

    public function updateStatus($newStatus, $notes = null)
    {
        $this->update([
            'status' => $newStatus,
            'notes' => $notes,
            'last_update' => now()
        ]);

        // Mettre à jour le report associé
        if ($this->report) {
            $this->report->update(['status' => $newStatus]);
        }
    }
}