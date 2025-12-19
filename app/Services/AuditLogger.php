<?php
namespace App\Services;

use App\Models\AuditSysteme;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public static function logSignalement(array $signalementData): void
    {
        // Logging of signalements is disabled. Previously this created an AuditSignalement record.
        return;
    }

    public static function logSystemAction(string $userEmail, string $action, string $entite, string $statut, string $details, ?string $reference = null): void
    {
        try {
            AuditSysteme::create([
                'timestamp' => now(),
                'utilisateur' => $userEmail,
                'action' => $action,
                'entite' => $entite,
                'statut' => $statut,
                'ip' => Request::ip(),
                'details' => $details,
                'reference_dossier' => $reference,
                'metadata' => [
                    'user_agent' => Request::header('User-Agent'),
                    'referer' => Request::header('referer'),
                    'method' => Request::method()
                ]
            ]);
        } catch (\Exception $e) {
            // Log l'erreur sans provoquer d'échec
            \Illuminate\Support\Facades\Log::error('Erreur AuditLogger::logSystemAction', [
                'error' => $e->getMessage(),
                'userEmail' => $userEmail,
                'action' => $action
            ]);
        }
    }

    public static function logConsultation(string $userEmail, string $entite, string $details, ?string $reference = null): void
    {
        self::logSystemAction($userEmail, 'Consultation', $entite, 'Succès', $details, $reference);
    }

    public static function logModification(string $userEmail, string $entite, string $details, ?string $reference = null): void
    {
        self::logSystemAction($userEmail, 'Modification', $entite, 'Succès', $details, $reference);
    }

    public static function logExport(string $userEmail, string $entite, string $details, ?string $reference = null): void
    {
        // CORRECTION: Appel correct à logSystemAction avec tous les paramètres
        self::logSystemAction(
            $userEmail,
            'Export', // Action
            $entite,  // Entité
            'Succès', // Statut
            $details, // Détails
            $reference // Référence
        );
    }

    public static function logConnexion(string $userEmail, string $statut, string $details): void
    {
        self::logSystemAction($userEmail, 'Connexion', 'Authentification', $statut, $details);
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

    // Méthode supplémentaire pour les suppressions
    public static function logSuppression(string $userEmail, string $entite, string $details, ?string $reference = null): void
    {
        self::logSystemAction($userEmail, 'Suppression', $entite, 'Succès', $details, $reference);
    }

    // Méthode supplémentaire pour les créations
    public static function logCreation(string $userEmail, string $entite, string $details, ?string $reference = null): void
    {
        self::logSystemAction($userEmail, 'Création', $entite, 'Succès', $details, $reference);
    }
}
