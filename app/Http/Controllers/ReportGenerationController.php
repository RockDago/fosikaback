<?php
// app/Http/Controllers/ReportGenerationController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ReportGenerationController extends Controller
{
    /**
     * Générer un nouveau rapport
     */
    public function generateReport(Request $request): JsonResponse
    {
        Log::info('=== DÉBUT GÉNÉRATION RAPPORT ===', $request->all());

        // Validation
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:hebdo,mensuel,categorie,final'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Vérifier l'authentification
            if (!auth()->check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }

            // Vérifier que le type existe
            $typeExists = DB::table('report_types')
                ->where('id', $request->type)
                ->exists();

            if (!$typeExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Type de rapport non valide'
                ], 400);
            }

            // Générer les données du rapport
            $reportData = $this->generateReportData($request->type);
            
            // Créer le rapport
            $id = DB::table('generated_reports')->insertGetId(array_merge($reportData, [
                'report_type_id' => $request->type,
                'generated_by' => auth()->id()
            ]));

            DB::commit();

            Log::info('✅ RAPPORT CRÉÉ AVEC SUCCÈS', ['id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Rapport généré avec succès!',
                'data' => [
                    'id' => $id,
                    'title' => $reportData['title'],
                    'type' => $request->type,
                    'summary' => json_decode($reportData['summary_data'], true),
                    'results' => json_decode($reportData['key_results'], true),
                    'challenges' => json_decode($reportData['challenges'], true),
                    'recommendations' => json_decode($reportData['recommendations'], true),
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ ERREUR GÉNÉRATION RAPPORT', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du rapport',
                'debug' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Récupérer le dernier rapport généré
     */
    public function getLastGeneratedReport(): JsonResponse
    {
        try {
            $lastReport = DB::table('generated_reports')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastReport) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'Aucun rapport généré'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatReportData($lastReport)
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur récupération dernier rapport: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du rapport'
            ], 500);
        }
    }

    /**
     * Récupérer tous les rapports générés
     */
    public function getGeneratedReports(): JsonResponse
    {
        try {
            $reports = DB::table('generated_reports')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $reports->map(function($report) {
                    return $this->formatReportData($report);
                }),
                'count' => $reports->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur récupération rapports: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des rapports'
            ], 500);
        }
    }

    /**
     * Envoyer un rapport à une institution
     */
    public function sendReportToInstitution(Request $request, $reportId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'institution' => 'required|in:drse,cac,bianco'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $report = DB::table('generated_reports')->where('id', $reportId)->first();

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rapport non trouvé'
                ], 404);
            }

            $institution = $request->institution;
            $field = 'is_sent_to_' . $institution;

            // Vérifier si déjà envoyé
            if ($report->$field) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rapport déjà envoyé à ' . strtoupper($institution)
                ], 400);
            }

            // Mettre à jour
            DB::table('generated_reports')
                ->where('id', $reportId)
                ->update([
                    $field => true,
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Rapport envoyé avec succès à ' . strtoupper($institution)
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur envoi rapport: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du rapport'
            ], 500);
        }
    }

    /**
     * Télécharger un rapport
     */
    public function downloadReport($reportId): JsonResponse
    {
        try {
            $report = DB::table('generated_reports')->where('id', $reportId)->first();

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rapport non trouvé'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatReportData($report),
                'message' => 'Rapport prêt pour téléchargement'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur téléchargement rapport: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement du rapport'
            ], 500);
        }
    }

    /**
     * Générer les données du rapport
     */
    private function generateReportData($type): array
    {
        $data = [
            'hebdo' => [
                'title' => 'Rapport Hebdomadaire - ' . now()->format('d/m/Y'),
                'period_start' => now()->subWeek()->format('Y-m-d'),
                'period_end' => now()->format('Y-m-d'),
                'summary_data' => json_encode([
                    "245 signalements reçus cette semaine",
                    "189 signalements traités et résolus", 
                    "56 dossiers transférés au BIANCO"
                ]),
                'key_results' => json_encode([
                    "Taux de résolution moyen: 85.2%",
                    "Délai moyen de traitement: 42h",
                    "Satisfaction citoyens: 88%"
                ]),
                'challenges' => json_encode([
                    "Volume élevé de signalements nécessitant un traitement urgent",
                    "Taux de résolution inférieur aux objectifs fixés"
                ]),
                'recommendations' => json_encode([
                    "Intensifier les campagnes de sensibilisation",
                    "Améliorer la coordination avec les institutions partenaires",
                    "Développer des outils automatiques de détection des doublons"
                ])
            ],
            'mensuel' => [
                'title' => 'Rapport Mensuel - ' . now()->locale('fr')->monthName . ' ' . now()->year,
                'period_start' => now()->startOfMonth()->format('Y-m-d'),
                'period_end' => now()->endOfMonth()->format('Y-m-d'),
                'summary_data' => json_encode([
                    "1,245 signalements reçus ce mois",
                    "985 signalements traités et résolus", 
                    "260 dossiers transférés au BIANCO"
                ]),
                'key_results' => json_encode([
                    "Taux de résolution moyen: 79.1%",
                    "Délai moyen de traitement: 51h",
                    "Satisfaction citoyens: 82%"
                ]),
                'challenges' => json_encode([
                    "Volume élevé de signalements dans la catégorie fraudes académiques",
                    "Besoin de renforcement des capacités de vérification"
                ]),
                'recommendations' => json_encode([
                    "Intensifier les campagnes de sensibilisation",
                    "Améliorer la coordination avec les institutions partenaires", 
                    "Développer des outils automatiques de détection des doublons",
                    "Renforcer la formation des agents de traitement"
                ])
            ],
            'categorie' => [
                'title' => 'Rapport par Catégorie - Analyse détaillée',
                'period_start' => now()->subMonth()->format('Y-m-d'),
                'period_end' => now()->format('Y-m-d'),
                'summary_data' => json_encode([
                    "Analyse détaillée par catégorie de signalements",
                    "Identification des tendances et patterns",
                    "Recommandations spécifiques par domaine"
                ]),
                'key_results' => json_encode([
                    "Catégorie la plus active: Fraudes académiques (35%)",
                    "Taux de résolution par catégorie: 65-92%",
                    "Délais variables selon la complexité"
                ]),
                'challenges' => json_encode([
                    "Défis spécifiques à chaque catégorie de signalements",
                    "Besoins en ressources supplémentaires variables",
                    "Coordination avec les partenaires spécialisés"
                ]),
                'recommendations' => json_encode([
                    "Actions ciblées pour chaque catégorie",
                    "Amélioration des processus de traitement spécifiques",
                    "Renforcement des capacités spécialisées"
                ])
            ],
            'final' => [
                'title' => 'Rapport Final d\'Opération',
                'summary_data' => json_encode([
                    "Synthèse complète de l'opération",
                    "Bilan des activités et des résultats", 
                    "Analyse de l'impact global"
                ]),
                'key_results' => json_encode([
                    "Taux de succès: 95.5%",
                    "Impact mesuré: Élevé",
                    "Rétroaction positive des parties prenantes"
                ]),
                'challenges' => json_encode([
                    "Principaux défis rencontrés durant l'opération",
                    "Contraintes logistiques et opérationnelles", 
                    "Adaptation aux circonstances changeantes"
                ]),
                'recommendations' => json_encode([
                    "Recommandations pour les opérations futures",
                    "Améliorations des processus opérationnels",
                    "Renforcement des capacités institutionnelles"
                ])
            ]
        ];

        return $data[$type] ?? $data['hebdo'];
    }

    /**
     * Formater les données du rapport pour l'API
     */
    private function formatReportData($report): array
    {
        return [
            'id' => $report->id,
            'title' => $report->title,
            'type' => $report->report_type_id,
            'period_start' => $report->period_start,
            'period_end' => $report->period_end,
            'summary' => json_decode($report->summary_data, true) ?: [],
            'results' => json_decode($report->key_results, true) ?: [],
            'challenges' => json_decode($report->challenges, true) ?: [],
            'recommendations' => json_decode($report->recommendations, true) ?: [],
            'is_sent_to_drse' => (bool) $report->is_sent_to_drse,
            'is_sent_to_cac' => (bool) $report->is_sent_to_cac,
            'is_sent_to_bianco' => (bool) $report->is_sent_to_bianco,
            'generated_at' => $report->created_at,
        ];
    }
}