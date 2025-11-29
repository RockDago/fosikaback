<?php
// app/Http/Controllers/JournalAuditController.php

namespace App\Http\Controllers;

use App\Models\AuditSysteme;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class JournalAuditController extends Controller
{
    public function getJournalData(Request $request): JsonResponse
    {
        // Récupérer les données d'audit système
        $auditSysteme = AuditSysteme::orderBy('timestamp', 'desc')
            ->get()
            ->map(function ($audit) {
                return [
                    'id' => $audit->id,
                    'timestamp' => $audit->timestamp->format('Y-m-d H:i:s'),
                    'utilisateur' => $audit->utilisateur,
                    'action' => $audit->action,
                    'entite' => $audit->entite,
                    'statut' => $audit->statut,
                    'ip' => $audit->ip,
                    'details' => $audit->details,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'audit_log' => $auditSysteme,
            ]
        ]);
    }

    public function exportAudit(Request $request): JsonResponse
    {
        $type = $request->get('type', 'systeme');
        if ($type === 'signalements') {
            return response()->json([
                'success' => false,
                'message' => 'Audit des signalements supprimé'
            ], 400);
        }

        $data = AuditSysteme::all();
        $fileName = 'audit_systeme_' . date('Y-m-d') . '.csv';

        // Log de l'export
        AuditLogger::logExport(
            $request->user()->email,
            $type === 'signalements' ? 'Signalements' : 'Système',
            'Export des données d\'audit'
        );

        return response()->json([
            'success' => true,
            'message' => 'Export fonctionnel - À implémenter avec Excel/CSV',
            'file_name' => $fileName,
            'data_count' => $data->count()
        ]);
    }
}