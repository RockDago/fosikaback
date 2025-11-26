<?php
// app/Services/AuditLogger.php

namespace App\Services;

use App\Models\AuditSignalement;
use App\Models\AuditSysteme;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public static function logSignalement(array $signalementData): void
    {
        AuditSignalement::create([
            'reference_dossier' => $signalementData['reference'],
            'timestamp' => now(),
            'type_anonymat' => $signalementData['type_anonymat'],
            'adresse_ip' => $signalementData['adresse_ip'] ?? Request::ip(),
            'geolocalisation' => $signalementData['geolocalisation'] ?? self::getGeolocation(Request::ip()),
            'identite' => $signalementData['identite'],
            'telephone' => $signalementData['telephone'],
            'email' => $signalementData['email'],
            'region_province' => $signalementData['region_province'],
            'type_fraude' => $signalementData['type_fraude'],
            'statut' => 'Reçu',
            'details_supplementaires' => $signalementData['details'] ?? null,
        ]);
    }

    public static function logSystemAction(string $userEmail, string $action, string $entite, string $statut, string $details, ?string $reference = null): void
    {
        AuditSysteme::create([
            'timestamp' => now(),
            'utilisateur' => $userEmail,
            'action' => $action,
            'entite' => $entite,
            'statut' => $statut,
            'ip' => Request::ip(),
            'details' => $details,
            'reference_dossier' => $reference,
        ]);
    }

    public static function logConsultation(string $userEmail, string $entite, string $details, ?string $reference = null): void
    {
        self::logSystemAction($userEmail, 'Consultation', $entite, 'Succès', $details, $reference);
    }

    public static function logModification(string $userEmail, string $entite, string $details, ?string $reference = null): void
    {
        self::logSystemAction($userEmail, 'Modification', $entite, 'Succès', $details, $reference);
    }

    public static function logExport(string $userEmail, string $entite, string $details): void
    {
        self::logSystemAction($userEmail, 'Export', $entite, 'Succès', $details);
    }

    public static function logConnexion(string $userEmail, string $statut, string $details): void
    {
        self::logSystemAction($userEmail, 'Connexion', 'Système', $statut, $details);
    }

    private static function getGeolocation(string $ip): string
    {
        // Implémentation basique - À améliorer avec un service de géolocalisation
        if ($ip === '127.0.0.1') {
            return 'Localhost';
        }
        
        // Pour l'exemple, retourner une valeur par défaut
        return 'Madagascar';
    }
}