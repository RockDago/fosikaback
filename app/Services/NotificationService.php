<?php
// app/Services/NotificationService.php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    public static function createNotification(array $data): Notification
    {
        return Notification::create([
            'type' => $data['type'],
            'titre' => $data['titre'],
            'message' => $data['message'],
            'priority' => $data['priority'] ?? 'medium',
            'reference_dossier' => $data['reference_dossier'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    public static function notifyNewSignalement(string $reference, array $signalementData): void
    {
        self::createNotification([
            'type' => 'nouveau_signalement',
            'titre' => 'Nouveau signalement reçu',
            'message' => "Un nouveau signalement a été enregistré avec la référence: {$reference}",
            'priority' => 'high',
            'reference_dossier' => $reference,
            'metadata' => $signalementData,
        ]);
    }

    public static function notifySignalementUrgent(string $reference): void
    {
        self::createNotification([
            'type' => 'signalement_urgent',
            'titre' => 'Signalement urgent',
            'message' => "Le signalement {$reference} nécessite une attention immédiate",
            'priority' => 'high',
            'reference_dossier' => $reference,
        ]);
    }
}
