<?php
// app/Observers/ReportObserver.php

namespace App\Observers;

use App\Models\Report;
use App\Services\NotificationService;

class ReportObserver
{
    /**
     * Déclenché automatiquement lors de la création d'un signalement
     */
    public function created(Report $report): void
    {
        // Créer une notification pour chaque nouveau signalement
        NotificationService::notifyNewSignalement(
            $report->reference,
            [
                'category' => $report->category,
                'region' => $report->region,
                'city' => $report->city ?? null,
                'priority' => $report->priority ?? 'medium',
                'created_at' => $report->created_at,
            ]
        );
        
        // Si le signalement est urgent, créer une notification supplémentaire
        if (isset($report->priority) && $report->priority === 'high') {
            NotificationService::notifySignalementUrgent($report->reference);
        }
    }
}
