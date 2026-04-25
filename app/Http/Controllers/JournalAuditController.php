<?php
namespace App\Http\Controllers;

use App\Models\AuditSysteme;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class JournalAuditController extends Controller
{
    /**
     * Récupère les données du journal d'audit (PUBLIQUE - Pas besoin d'authentification)
     */
    public function getJournalData(Request $request): JsonResponse
    {
        try {
            // ✅ CORRECTION: Utiliser l'email de l'utilisateur authentifié s'il existe
            // Sinon utiliser un identifiant générique
            $userEmail = Auth::check() ? Auth::user()->email : 'Utilisateur non authentifié';
            $userId = Auth::id() ?? null;

            // Log de la consultation
            AuditLogger::logConsultation(
                $userEmail,  // ✅ Ici on utilise le vrai email si authentifié
                'Journal d\'audit',
                'Consultation des logs d\'audit système',
                $userId
            );

            // Récupérer les données d'audit système
            $query = AuditSysteme::orderBy('timestamp', 'desc');

            // Appliquer des filtres si présents
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('utilisateur', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('entite', 'like', "%{$search}%")
                        ->orWhere('statut', 'like', "%{$search}%")
                        ->orWhere('ip', 'like', "%{$search}%")
                        ->orWhere('details', 'like', "%{$search}%");
                });
            }

            if ($request->has('user') && $request->user) {
                $query->where('utilisateur', 'like', "%{$request->user}%");
            }

            if ($request->filled('action')) {
                $this->applyActionFilter($query, $request->action);
            }

            if ($request->filled('status')) {
                $this->applyStatusFilter($query, $request->status);
            }

            if ($request->filled('auditDateStart')) {
                $query->whereDate('timestamp', '>=', $request->auditDateStart);
            }

            if ($request->filled('auditDateEnd')) {
                $query->whereDate('timestamp', '<=', $request->auditDateEnd);
            }

            // Pagination
            $perPage = $request->get('per_page', 50);
            $page = $request->get('page', 1);

            $auditSysteme = $query->paginate($perPage, ['*'], 'page', $page);

            // Transformer les données
            $transformedData = $auditSysteme->map(function ($audit) {
                return [
                    'id' => $audit->id,
                    'timestamp' => $audit->timestamp->format('Y-m-d H:i:s'),
                    'utilisateur' => $audit->utilisateur,
                    'action' => $audit->action,
                    'entite' => $audit->entite,
                    'statut' => $audit->statut,
                    'ip' => $audit->ip,
                    'details' => $audit->details,
                    'reference_dossier' => $audit->reference_dossier,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'total' => $auditSysteme->total(),
                'current_page' => $auditSysteme->currentPage(),
                'per_page' => $auditSysteme->perPage(),
                'last_page' => $auditSysteme->lastPage(),
                'filters_applied' => $request->except(['password', 'token', '_token']),
                'is_public_access' => true,
                'authenticated' => Auth::check(),
                'user_email' => Auth::check() ? Auth::user()->email : null, // ✅ Ajout de l'email utilisateur
                'user_role' => Auth::check() ? Auth::user()->role : null
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur getJournalData:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);

            // Log d'erreur avec l'email réel
            $userEmail = Auth::check() ? Auth::user()->email : 'Utilisateur non authentifié';
            AuditLogger::logSystemAction(
                $userEmail,  // ✅ Ici aussi
                'Consultation',
                'Journal d\'audit',
                'Erreur',
                'Erreur lors de la récupération des logs: ' . $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des logs: ' . $e->getMessage(),
                'is_public_access' => true
            ], 500);
        }
    }

    /**
     * Export des logs (PUBLIQUE - Pas besoin d'authentification)
     */
    public function exportAudit(Request $request): JsonResponse
    {
        try {
            // ✅ CORRECTION: Utiliser l'email de l'utilisateur authentifié
            $userEmail = Auth::check() ? Auth::user()->email : 'Utilisateur non authentifié';
            $userId = Auth::id() ?? null;

            $type = $request->get('type', 'systeme');
            $filters = $request->except(['password', 'token', '_token']);

            // Log de l'export avec l'email réel
            AuditLogger::logExport(
                $userEmail,  // ✅ Ici aussi
                'Journal d\'audit',
                json_encode([
                    'action' => 'Export CSV',
                    'export_type' => $type,
                    'filters_applied' => $filters,
                    'timestamp' => now()->format('Y-m-d H:i:s'),
                    'user_agent' => $request->header('User-Agent'),
                    'user_authenticated' => Auth::check(),
                    'user_email' => Auth::check() ? Auth::user()->email : null,
                    'user_role' => Auth::check() ? Auth::user()->role : 'non authentifié'
                ], JSON_PRETTY_PRINT),
                $userId
            );

            // Récupérer les données avec filtres
            $query = AuditSysteme::orderBy('timestamp', 'desc');

            // Appliquer les filtres si fournis
            if ($request->has('start_date') && $request->start_date) {
                $query->whereDate('timestamp', '>=', $request->start_date);
            }

            if ($request->has('end_date') && $request->end_date) {
                $query->whereDate('timestamp', '<=', $request->end_date);
            }

            if ($request->has('user_filter') && $request->user_filter) {
                $query->where('utilisateur', 'like', '%' . $request->user_filter . '%');
            }

            if ($request->has('action_filter') && $request->action_filter) {
                $query->where('action', $request->action_filter);
            }

            // Limiter à 1000 enregistrements maximum
            $data = $query->limit(1000)->get();
            $dataCount = $data->count();

            // Générer le nom du fichier
            $fileName = 'audit_export_' . date('Y-m-d_H-i-s') . '.csv';

            // Générer le contenu CSV
            $csvContent = $this->generateCSV($data);

            // Enregistrer le fichier temporairement
            $filePath = storage_path('app/exports/' . $fileName);

            // Créer le dossier s'il n'existe pas
            if (!file_exists(storage_path('app/exports'))) {
                mkdir(storage_path('app/exports'), 0755, true);
            }

            file_put_contents($filePath, $csvContent);

            Log::info('Export audit généré', [
                'user' => $userEmail,  // ✅ Ici aussi
                'authenticated' => Auth::check(),
                'file' => $fileName,
                'rows' => $dataCount
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Export généré avec succès',
                'file_name' => $fileName,
                'data_count' => $dataCount,
                'export_type' => $type,
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'download_url' => url("/api/audit/export/download/{$fileName}"),
                'is_public_export' => true,
                'user_authenticated' => Auth::check(),
                'user_email' => Auth::check() ? Auth::user()->email : null,
                'max_records' => 1000
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur exportAudit:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Log de l'erreur d'export avec l'email réel
            $userEmail = Auth::check() ? Auth::user()->email : 'Utilisateur non authentifié';
            AuditLogger::logSystemAction(
                $userEmail,  // ✅ Ici aussi
                'Export',
                'Journal d\'audit',
                'Échec',
                'Erreur lors de l\'export: ' . $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export: ' . $e->getMessage(),
                'is_public_access' => true
            ], 500);
        }
    }

    /**
     * Méthode pour générer le CSV
     */
    private function generateCSV($data)
    {
        $csv = "ID;Date/Heure;Utilisateur;Action;Entité;Statut;IP;Détails\n";

        foreach ($data as $row) {
            $csv .= implode(';', [
                    $row->id,
                    $row->timestamp->format('Y-m-d H:i:s'),
                    '"' . str_replace('"', '""', $row->utilisateur) . '"',
                    '"' . str_replace('"', '""', $row->action) . '"',
                    '"' . str_replace('"', '""', $row->entite) . '"',
                    '"' . str_replace('"', '""', $row->statut) . '"',
                    '"' . str_replace('"', '""', $row->ip) . '"',
                    '"' . str_replace('"', '""', str_replace(["\r\n", "\n", "\r"], ' ', $row->details)) . '"'
                ]) . "\n";
        }

        return $csv;
    }

    /**
     * Téléchargement du fichier exporté (PUBLIQUE)
     */
    public function downloadExport(Request $request, $filename)
    {
        try {
            // ✅ CORRECTION: Utiliser l'email de l'utilisateur authentifié
            $userEmail = Auth::check() ? Auth::user()->email : 'Utilisateur non authentifié';
            $userId = Auth::id() ?? null;

            // Vérifier que le fichier existe
            $filePath = storage_path('app/exports/' . $filename);

            if (!file_exists($filePath)) {
                abort(404, 'Fichier non trouvé');
            }

            // Log du téléchargement avec l'email réel
            AuditLogger::logSystemAction(
                $userEmail,  // ✅ Ici aussi
                'Téléchargement',
                'Journal d\'audit',
                'Succès',
                'Téléchargement du fichier d\'export: ' . $filename
            );

            // Télécharger le fichier
            return Response::download($filePath, $filename, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur téléchargement export', ['error' => $e->getMessage()]);
            abort(500, 'Erreur de téléchargement');
        }
    }

    /**
     * Statistiques d'audit (PUBLIQUE - Pas besoin d'authentification)
     */
    public function getAuditStats(Request $request): JsonResponse
    {
        try {
            // ✅ CORRECTION: Utiliser l'email de l'utilisateur authentifié
            $userEmail = Auth::check() ? Auth::user()->email : 'Utilisateur non authentifié';
            $userId = Auth::id() ?? null;

            // Calculer les statistiques
            $stats = [
                'total_actions' => AuditSysteme::count(),
                'success_actions' => AuditSysteme::where(function ($query) {
                    $this->applyStatusFilter($query, 'Succès');
                })->count(),
                'failed_actions' => AuditSysteme::where(function ($query) {
                    $this->applyStatusFilter($query, 'Échec');
                })->count(),
                'top_users' => AuditSysteme::select('utilisateur')
                    ->selectRaw('COUNT(*) as action_count')
                    ->groupBy('utilisateur')
                    ->orderByDesc('action_count')
                    ->limit(5)
                    ->get(),
                'top_actions' => AuditSysteme::select('action')
                    ->selectRaw('COUNT(*) as count')
                    ->groupBy('action')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get(),
                'today_actions' => AuditSysteme::whereDate('timestamp', Carbon::today())->count(),
                'yesterday_actions' => AuditSysteme::whereDate('timestamp', Carbon::yesterday())->count(),
                'this_week_actions' => AuditSysteme::whereBetween('timestamp', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek()
                ])->count(),
                'last_week_actions' => AuditSysteme::whereBetween('timestamp', [
                    Carbon::now()->subWeek()->startOfWeek(),
                    Carbon::now()->subWeek()->endOfWeek()
                ])->count(),
                'recent_activity' => AuditSysteme::orderBy('timestamp', 'desc')
                    ->limit(10)
                    ->get(['timestamp', 'utilisateur', 'action', 'entite', 'statut']),
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'is_public_stats' => true
            ];

            // Log de la consultation des stats avec l'email réel
            AuditLogger::logConsultation(
                $userEmail,  // ✅ Ici aussi
                'Journal d\'audit',
                'Consultation des statistiques d\'audit',
                $userId
            );

            return response()->json([
                'success' => true,
                'data' => $stats,
                'authenticated' => Auth::check(),
                'user_email' => Auth::check() ? Auth::user()->email : null, // ✅ Ajout de l'email utilisateur
                'user_role' => Auth::check() ? Auth::user()->role : null
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur getAuditStats:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'is_public_access' => true
            ], 500);
        }
    }

    /**
     * Récupérer les logs de l'utilisateur courant seulement (PROTÉGÉ - Nécessite authentification)
     */
    public function getUserAuditLogs(Request $request): JsonResponse
    {
        try {
            // ✅ CONSERVÉ: Vérification d'authentification
            // Cette route reste protégée pour les logs personnels
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié. Cette route nécessite une connexion.',
                    'authenticated' => false
                ], 401);
            }

            $user = Auth::user();

            // Récupérer uniquement les logs de l'utilisateur courant
            $query = AuditSysteme::where('utilisateur', $user->email)
                ->orderBy('timestamp', 'desc');

            // Appliquer des filtres si présents
            if ($request->has('action') && $request->action) {
                $query->where('action', $request->action);
            }

            if ($request->has('start_date') && $request->start_date) {
                $query->whereDate('timestamp', '>=', $request->start_date);
            }

            if ($request->has('end_date') && $request->end_date) {
                $query->whereDate('timestamp', '<=', $request->end_date);
            }

            $userLogs = $query->get()
                ->map(function ($audit) {
                    return [
                        'id' => $audit->id,
                        'timestamp' => $audit->timestamp->format('Y-m-d H:i:s'),
                        'action' => $audit->action,
                        'entite' => $audit->entite,
                        'statut' => $audit->statut,
                        'ip' => $audit->ip,
                        'details' => $audit->details,
                    ];
                });

            // Log de la consultation des logs personnels
            AuditLogger::logConsultation(
                $user->email,
                'Journal d\'audit',
                'Consultation de ses propres logs d\'activité',
                $user->id
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'user_logs' => $userLogs,
                    'total' => $userLogs->count(),
                    'user_email' => $user->email,
                    'requires_auth' => true
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur getUserAuditLogs:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des logs personnels'
            ], 500);
        }
    }

    private function applyActionFilter($query, string $action): void
    {
        $normalized = mb_strtolower(trim($action));
        $variants = match ($normalized) {
            'création', 'creation' => ['Création', 'CrÃ©ation', 'Creation', 'POST'],
            'modification' => ['Modification', 'PUT', 'PATCH', 'Update'],
            'suppression' => ['Suppression', 'DELETE', 'Destroy', 'Remove'],
            'connexion' => ['Connexion', 'Login'],
            'déconnexion', 'deconnexion' => ['Déconnexion', 'DÃ©connexion', 'Logout'],
            'consultation' => ['Consultation', 'GET', 'Read'],
            'export' => ['Export'],
            default => [$action],
        };

        $query->where(function ($q) use ($variants) {
            foreach ($variants as $variant) {
                $q->orWhere('action', 'like', "%{$variant}%");
            }
        });
    }

    private function applyStatusFilter($query, string $status): void
    {
        $normalized = mb_strtolower(trim($status));
        $variants = match ($normalized) {
            'succès', 'succes', 'success' => ['Succès', 'SuccÃ¨s', 'Success', '200', '201', '204'],
            'échec', 'echec', 'fail', 'failed' => ['Échec', 'Ã‰chec', 'Echec', 'Fail', 'Erreur'],
            'refusé', 'refuse', 'refus' => ['Refusé', 'RefusÃ©', 'Refuse', '401', '403'],
            default => [$status],
        };

        $query->where(function ($q) use ($variants) {
            foreach ($variants as $variant) {
                $q->orWhere('statut', 'like', "%{$variant}%");
            }
        });
    }
}
