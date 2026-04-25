<?php

namespace App\Observers;

use App\Models\Report;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class ReportObserver
{
    /**
     * Declenche automatiquement lors de la creation d'un signalement.
     */
    public function created(Report $report): void
    {
        try {
            NotificationService::notifyNewSignalement(
                $report->reference,
                [
                    'category' => $report->category,
                    'region' => $report->region,
                    'city' => $report->city ?? null,
                    'priority' => $report->priority ?? 'medium',
                    'description' => $report->description,
                    'type' => $report->type,
                    'name' => $report->name,
                    'created_at' => $report->created_at,
                ]
            );

            if (isset($report->priority) && $report->priority === 'high') {
                NotificationService::notifySignalementUrgent($report->reference);
            }
        } catch (\Throwable $e) {
            Log::error('Erreur notification nouveau signalement: ' . $e->getMessage(), [
                'reference' => $report->reference,
            ]);
        }
    }
}
