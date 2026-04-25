<?php
namespace App\Services;

use App\Models\AuditSysteme;
use Illuminate\Support\Facades\Log;
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
                'action' => self::normalizeLabel($action),
                'entite' => self::normalizeLabel($entite),
                'statut' => self::normalizeLabel($statut),
                'ip' => Request::ip(),
                'details' => $details,
                'reference_dossier' => $reference,
                'metadata' => [
                    'user_agent' => Request::header('User-Agent'),
                    'referer' => Request::header('referer'),
                    'method' => Request::method(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur AuditLogger::logSystemAction', [
                'error' => $e->getMessage(),
                'userEmail' => $userEmail,
                'action' => $action,
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
        self::logSystemAction($userEmail, 'Export', $entite, 'Succès', $details, $reference);
    }

    public static function logConnexion(string $userEmail, string $statut, string $details): void
    {
        self::logSystemAction($userEmail, 'Connexion', 'Authentification', $statut, $details);
    }

    public static function logSuppression(string $userEmail, string $entite, string $details, ?string $reference = null): void
    {
        self::logSystemAction($userEmail, 'Suppression', $entite, 'Succès', $details, $reference);
    }

    public static function logCreation(string $userEmail, string $entite, string $details, ?string $reference = null): void
    {
        self::logSystemAction($userEmail, 'Création', $entite, 'Succès', $details, $reference);
    }

    private static function normalizeLabel(string $value): string
    {
        $map = [
            'SuccÃ¨s' => 'Succès',
            'Ã‰chec' => 'Échec',
            'Ã©chec' => 'Échec',
            'RefusÃ©' => 'Refusé',
            'CrÃ©ation' => 'Création',
            'DÃ©connexion' => 'Déconnexion',
            'TÃ©lÃ©chargement' => 'Téléchargement',
            'SystÃ¨me' => 'Système',
            'EntitÃ©' => 'Entité',
        ];

        return str_replace(array_keys($map), array_values($map), $value);
    }
}
