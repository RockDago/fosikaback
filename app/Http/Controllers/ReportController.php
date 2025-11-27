<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Tracking;
use App\Models\ReportFile;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    protected $fileService;
    
    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }
public function store(Request $request)
{
    $validated = $request->validate([
        'type' => 'required|string|in:anonyme,identifie',
        'name' => 'required|string|max:255',
        'email' => 'nullable|email|max:255',
        'phone' => 'nullable|string|max:50',
        'address' => 'nullable|string|max:500',
        'category' => 'required|string|max:255',
        'description' => 'required|string',
        'accept_terms' => 'required|boolean',
        'accept_truth' => 'required|boolean',
        'files.*' => 'sometimes|file|mimes:jpg,jpeg,png,mp4,pdf|max:51200',
    ]);

    try {
        // Créer le rapport
        $report = Report::create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'category' => $validated['category'],
            'description' => $validated['description'],
            'accept_terms' => $validated['accept_terms'],
            'accept_truth' => $validated['accept_truth'],
            'status' => 'en_cours',
        ]);

        // Sauvegarder les fichiers - STOCKER UNIQUEMENT LES NOMS
        $fileNames = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $index => $file) {
                $extension = $file->getClientOriginalExtension();
                $fileName = "preuve_" . ($index + 1) . "_" . $report->reference . "." . $extension;
                
                // Stocker le fichier
                $file->storeAs('reports', $fileName, 'public');
                
                // Ajouter uniquement le nom du fichier (pas l'URL complète)
                $fileNames[] = $fileName;
            }
            
            // Sauvegarder les noms de fichiers en JSON
            $report->files = $fileNames;
            $report->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Signalement créé avec succès',
            'reference' => $report->reference,
            'files' => $fileNames
        ]);

    } catch (\Exception $e) {
        Log::error('Erreur création signalement: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur interne: ' . $e->getMessage()
        ], 500);
    }
}




    /**
     * Fonction pour sauvegarder les fichiers Base64
     */
    private function saveBase64File(Report $report, array $fileData)
    {
        try {
            $base64Data = $fileData['base64_data'];
            
            \Log::info('🔍 Analyse données Base64', [
                'debut' => substr($base64Data, 0, 50),
                'longueur' => strlen($base64Data)
            ]);

            // Extraire les données Base64 pures
            if (strpos($base64Data, 'base64,') !== false) {
                $base64Data = explode('base64,', $base64Data)[1];
            }

            // Décoder les données Base64
            $fileContent = base64_decode($base64Data);
            
            if ($fileContent === false) {
                throw new \Exception('Erreur de décodage Base64 - données invalides');
            }

            if (empty($fileContent)) {
                throw new \Exception('Données Base64 vides après décodage');
            }

            // Générer un nom de fichier unique
            $filename = uniqid() . '_' . Str::slug(pathinfo($fileData['original_name'], PATHINFO_FILENAME));
            $extension = pathinfo($fileData['original_name'], PATHINFO_EXTENSION);
            $filenameWithExt = $filename . '.' . $extension;
            
            $filePath = 'uploads/reports/' . $report->id . '/' . $filenameWithExt;
            
            \Log::info('📂 Sauvegarde fichier', [
                'chemin' => $filePath,
                'taille' => strlen($fileContent)
            ]);

            // Créer le dossier si nécessaire
            Storage::disk('public')->makeDirectory('uploads/reports/' . $report->id);
            
            // Sauvegarder le fichier
            $saved = Storage::disk('public')->put($filePath, $fileContent);
            
            if (!$saved) {
                throw new \Exception('Impossible de sauvegarder le fichier sur le disque');
            }

            // Vérifier que le fichier existe
            if (!Storage::disk('public')->exists($filePath)) {
                throw new \Exception('Fichier non trouvé après sauvegarde');
            }

            // Créer l'entrée en base de données
            $reportFile = ReportFile::create([
                'report_id' => $report->id,
                'original_name' => $fileData['original_name'],
                'file_path' => $filePath,
                'mime_type' => $fileData['mime_type'],
                'size' => $fileData['size'],
            ]);

            \Log::info('✅ Fichier sauvegardé', [
                'id' => $reportFile->id,
                'chemin' => $filePath,
                'taille_reelle' => Storage::disk('public')->size($filePath)
            ]);

            return [
                'filename' => $filenameWithExt,
                'path' => $filePath,
                'file_id' => $reportFile->id
            ];

        } catch (\Exception $e) {
            \Log::error('💥 Erreur sauvegarde fichier Base64', [
                'message' => $e->getMessage(),
                'fichier' => $fileData['original_name'] ?? 'inconnu',
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Fonction pour générer une référence
     */
    private function generateReference()
    {
        return 'FOS-' . date('Ymd') . '-' . strtoupper(Str::random(6));
    }

    public function uploadFile(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:jpg,jpeg,png,mp4,pdf|max:25600', // 25MB
            ]);

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('uploads', $filename, 'public');

                return response()->json([
                    'success' => true,
                    'url' => asset('storage/' . $path),
                    'path' => $path,
                    'filename' => $filename
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Aucun fichier trouvé'
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lister tous les signalements avec formatage
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userEmail = auth()->check() ? auth()->user()->email : 'API Guest';
            
            AuditLogger::logConsultation(
                $userEmail,
                'Signalements',
                "Consultation de la liste des signalements",
                null
            );

            Log::info('API Reports accessed', [
                'url' => $request->fullUrl(),
                'user_agent' => $request->userAgent()
            ]);

            // Récupérer tous les rapports
            $reports = Report::orderBy('created_at', 'desc')->get();

            if ($reports->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Aucun rapport trouvé',
                    'data' => [],
                    'count' => 0
                ], 200);
            }

            // Formater les données
            $formattedReports = $reports->map(function ($report) {
                // Workflow par défaut
                $defaultWorkflow = [
                    'drse' => [
                        'date' => $report->created_at->toISOString(),
                        'status' => 'in_progress',
                        'agent' => 'DRSE Analamanga',
                        'progress' => 33
                    ],
                    'cac' => [
                        'date' => null,
                        'status' => 'pending',
                        'agent' => 'CAC - Cellule Anti-Corruption',
                        'progress' => 0
                    ],
                    'bianco' => [
                        'date' => null,
                        'status' => 'pending',
                        'agent' => 'BIANCO',
                        'progress' => 0
                    ]
                ];

                // Utiliser le workflow de la base s'il existe
                $workflowData = $defaultWorkflow;
                if (!empty($report->workflow)) {
                    if (is_array($report->workflow)) {
                        $workflowData = $report->workflow;
                    } elseif (is_string($report->workflow)) {
                        $decoded = json_decode($report->workflow, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $workflowData = $decoded;
                        }
                    }
                }

                // Gestion des fichiers
                $filesArray = [];
                if (!empty($report->files)) {
                    if (is_string($report->files)) {
                        $decodedFiles = json_decode($report->files, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $filesArray = $decodedFiles;
                        }
                    } elseif (is_array($report->files)) {
                        $filesArray = $report->files;
                    }
                }

                // ✅ GÉRER LES SIGNALEMENTS SANS PREUVES
                $hasProof = $report->has_proof ?? (count($filesArray) > 0);

                return [
                    'id' => $report->id,
                    'reference' => $report->reference ?? 'REF-' . $report->id,
                    'title' => $report->title ?? 'Sans titre',
                    'description' => $report->description ?? 'Aucune description',
                    'category' => $report->category ?? 'divers',
                    'status' => $report->status ?? 'en_cours',
                    'type' => $report->type ?? 'identifie',
                    'is_anonymous' => $report->type === 'anonyme',
                    'name' => $report->name ?? 'Anonyme',
                    'email' => $report->email ?? '',
                    'phone' => $report->phone ?? '',
                    'files' => $filesArray,
                    'has_proof' => $hasProof, // ✅ AJOUT DU CHAMP HAS_PROOF
                    'workflow' => $workflowData,
                    'created_at' => $report->created_at->toISOString(),
                    'updated_at' => $report->updated_at->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Rapports récupérés avec succès',
                'data' => $formattedReports,
                'count' => $reports->count()
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            AuditLogger::logSystemAction(
                'Système',
                'Consultation',
                'Signalements',
                'Échec',
                "Erreur lors de la récupération des rapports: " . $e->getMessage()
            );

            Log::error('Error in reports API: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des rapports',
                'error' => $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Afficher un signalement spécifique
     */
    public function show($reference): JsonResponse
    {
        try {
            $userEmail = auth()->check() ? auth()->user()->email : 'API Guest';
            
            $report = Report::with(['workflowLogs', 'tracking'])
                ->where('reference', $reference)
                ->firstOrFail();

            AuditLogger::logConsultation(
                $userEmail,
                'Signalement',
                "Consultation du signalement {$reference}",
                $reference
            );

            $formattedReport = [
                'reference' => $report->reference,
                'date' => $report->created_at->toISOString(),
                'category' => $report->category,
                'status' => $report->status,
                'is_anonymous' => $report->type === 'anonyme',
                'name' => $report->name,
                'email' => $report->email,
                'phone' => $report->phone,
                'address' => $report->address,
                'description' => $report->description,
                'files' => $report->files ?? [],
                'files_count' => $report->files ? count($report->files) : 0,
                'has_proof' => $report->has_proof ?? false, // ✅ AJOUT DU CHAMP HAS_PROOF
                'workflow' => $report->workflow,
                'ip_address' => $report->ip_address,
                'created_at' => $report->created_at->toISOString(),
                'updated_at' => $report->updated_at->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $formattedReport
            ]);

        } catch (\Exception $e) {
            AuditLogger::logSystemAction(
                'Système',
                'Consultation',
                'Signalement',
                'Échec',
                "Erreur lors de la consultation du signalement {$reference}: " . $e->getMessage(),
                $reference
            );

            return response()->json([
                'success' => false,
                'message' => 'Signalement non trouvé'
            ], 404);
        }
    }

    /**
     * Récupérer les statistiques
     */
    public function getStats(): JsonResponse
    {
        try {
            $userEmail = auth()->check() ? auth()->user()->email : 'API Guest';

            AuditLogger::logConsultation(
                $userEmail,
                'Statistiques',
                "Consultation des statistiques des signalements"
            );

            $stats = [
                'total' => Report::count(),
                'en_cours' => Report::where('status', 'en_cours')->count(),
                'finalise' => Report::where('status', 'finalise')->count(),
                'doublon' => Report::where('status', 'doublon')->count(),
                'refuse' => Report::where('status', 'refuse')->count(),
                'classifier' => Report::where('status', 'classifier')->count(),
                'anonyme' => Report::where('type', 'anonyme')->count(),
                'identifie' => Report::where('type', 'identifie')->count(),
                'with_proof' => Report::where('has_proof', true)->count(), // ✅ NOUVELLE STAT
                'without_proof' => Report::where('has_proof', false)->orWhereNull('has_proof')->count(), // ✅ NOUVELLE STAT
                'by_category' => Report::groupBy('category')
                    ->selectRaw('category, count(*) as count')
                    ->get()
                    ->pluck('count', 'category')
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            AuditLogger::logSystemAction(
                'Système',
                'Consultation',
                'Statistiques',
                'Échec',
                "Erreur lors de la récupération des statistiques: " . $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques'
            ], 500);
        }
    }

    /**
     * Visualiser un fichier
     */
  public function getFile($filename): Response
{
    try {
        Log::info("Tentative de visualisation du fichier: " . $filename);
        
        // Nettoyer le nom du fichier
        $filename = basename($filename);
        
        // Chemins possibles
        $possiblePaths = [
            public_path('storage/reports/' . $filename),
            storage_path('app/public/reports/' . $filename),
        ];
        
        $filePath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path) && filesize($path) > 0) {
                $filePath = $path;
                break;
            }
        }
        
        if (!$filePath) {
            Log::error("Fichier non trouvé: " . $filename);
            return response()->json([
                'success' => false,
                'message' => 'Fichier non trouvé: ' . $filename
            ], 404);
        }
        
        // Obtenir le type MIME
        $mimeType = File::mimeType($filePath);
        
        Log::info("Fichier trouvé: " . $filePath);
        
        // Retourner le fichier pour visualisation
        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
        
    } catch (\Exception $e) {
        Log::error("Erreur visualisation fichier {$filename}: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ], 500);
    }
}


    /**
     * Télécharger un fichier
     */
    public function downloadFile($filename): Response
    {
        try {
            Log::info("📥 Tentative de téléchargement du fichier: {$filename}");
            
            // Nettoyer le nom du fichier
            $filename = basename($filename);
            
            // Chercher le rapport associé pour avoir la référence
            $report = Report::where('files', 'LIKE', '%' . $filename . '%')->first();
            $reference = $report ? $report->reference : 'REF-' . time();
            
            // Trouver ou créer le fichier
            $filePath = $this->fileService->findOrCreateFile($filename, $reference);
            
            if (!$filePath || !file_exists($filePath)) {
                Log::error("❌ Fichier non trouvé pour téléchargement: {$filename}");
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de télécharger le fichier: ' . $filename
                ], 404);
            }

            // Journaliser le téléchargement
            AuditLogger::logConsultation(
                'Public',
                'Fichier',
                "Téléchargement du fichier: {$filename}",
                $reference
            );

            Log::info("✅ Fichier téléchargé: {$filePath} (Taille: " . filesize($filePath) . " bytes)");

            // Retourner le fichier en téléchargement
            return response()->download($filePath, $filename, [
                'Content-Type' => File::mimeType($filePath),
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Erreur téléchargement fichier {$filename}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement du fichier: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir l'URL publique d'un fichier
     */
    public function getFileUrl($filename): JsonResponse
    {
        try {
            // Nettoyer le nom du fichier
            $filename = basename($filename);
            
            // Chercher le rapport associé
            $report = Report::where('files', 'LIKE', '%' . $filename . '%')->first();
            $reference = $report ? $report->reference : 'REF-' . time();
            
            // Vérifier si le fichier existe ou peut être créé
            $filePath = $this->fileService->findOrCreateFile($filename, $reference);
            
            if (!$filePath) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de générer le fichier: ' . $filename
                ], 404);
            }

            // Générer les URLs publiques
            $baseUrl = url('/api');
            $url = $baseUrl . '/files/' . urlencode($filename);
            $downloadUrl = $baseUrl . '/files/' . urlencode($filename) . '/download';

            return response()->json([
                'success' => true,
                'url' => $url,
                'download_url' => $downloadUrl,
                'filename' => $filename,
                'file_exists' => file_exists($filePath),
                'file_size' => file_exists($filePath) ? filesize($filePath) : 0,
                'reference' => $reference
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur génération URL fichier {$filename}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération de l\'URL'
            ], 500);
        }
    }

    /**
     * API pour lister tous les fichiers d'un rapport
     */
    public function getReportFiles($reference): JsonResponse
    {
        try {
            $report = Report::where('reference', $reference)->firstOrFail();
            
            $files = $report->files;
            if (is_string($files)) {
                $files = json_decode($files, true);
            }

            $filesWithUrls = [];
            foreach ($files as $file) {
                $filePath = $this->fileService->findOrCreateFile($file, $reference);
                $filesWithUrls[] = [
                    'filename' => $file,
                    'view_url' => url('/api/files/' . $file),
                    'download_url' => url('/api/files/' . $file . '/download'),
                    'file_exists' => $filePath !== null,
                    'file_size' => $filePath ? filesize($filePath) : 0
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $filesWithUrls,
                'count' => count($filesWithUrls)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des fichiers'
            ], 500);
        }
    }

    /**
     * Vérifier l'état des fichiers d'un rapport
     */
    public function getFilesStatus($reference): JsonResponse
    {
        try {
            $report = Report::where('reference', $reference)->firstOrFail();
            
            $files = $report->files;
            if (is_string($files)) {
                $files = json_decode($files, true);
            }

            $filesStatus = [];
            $totalFiles = 0;
            $existingFiles = 0;
            $missingFiles = 0;

            foreach ($files as $filename) {
                $totalFiles++;
                $filePath = $this->fileService->findExistingFile($filename);
                $fileExists = $filePath !== null;
                
                if ($fileExists) {
                    $existingFiles++;
                } else {
                    $missingFiles++;
                }

                $filesStatus[] = [
                    'filename' => $filename,
                    'exists' => $fileExists,
                    'file_path' => $filePath,
                    'file_size' => $fileExists ? filesize($filePath) : 0,
                    'view_url' => $fileExists ? url('/api/files/' . $filename) : null,
                    'download_url' => $fileExists ? url('/api/files/' . $filename . '/download') : null,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'reference' => $reference,
                    'total_files' => $totalFiles,
                    'existing_files' => $existingFiles,
                    'missing_files' => $missingFiles,
                    'files' => $filesStatus,
                    'can_generate_missing' => $missingFiles > 0,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification des fichiers'
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut d'un signalement
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:en_cours,finalise,doublon,refuse,classifier',
            'workflow' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userEmail = auth()->check() ? auth()->user()->email : 'Système';
            $report = Report::findOrFail($id);

            $oldStatus = $report->status;
            $updateData = ['status' => $request->status];

            // Mettre à jour le workflow si fourni
            if ($request->has('workflow')) {
                $updateData['workflow'] = $request->workflow;
            }

            $report->update($updateData);

            // Mettre à jour le tracking
            if ($report->tracking) {
                $report->tracking->update([
                    'status' => $request->status,
                    'last_update' => now()
                ]);
            }

            // Journaliser le changement de statut
            AuditLogger::logModification(
                $userEmail,
                'Signalement',
                "Changement de statut: {$oldStatus} → {$request->status}",
                $report->reference
            );

            // Créer une notification pour les changements importants
            if (in_array($request->status, ['finalise', 'refuse', 'classifier'])) {
                NotificationService::createNotification([
                    'type' => 'statut_modifie',
                    'titre' => 'Statut du signalement mis à jour',
                    'message' => "Le statut du signalement {$report->reference} a été changé en: " .
                        $this->getStatusLabel($request->status),
                    'priority' => 'medium',
                    'reference_dossier' => $report->reference,
                    'metadata' => [
                        'ancien_statut' => $oldStatus,
                        'nouveau_statut' => $request->status
                    ]
                ]);
            }

            Log::info("Statut du signalement {$report->reference} changé de {$oldStatus} à {$request->status}");

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            AuditLogger::logSystemAction(
                'Système',
                'Modification',
                'Signalement',
                'Échec',
                "Erreur lors de la mise à jour du statut: " . $e->getMessage(),
                $id
            );

            Log::error("Erreur updateStatus: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut'
            ], 500);
        }
    }

    /**
     * Mettre à jour le workflow d'un signalement
     */
    public function updateWorkflow(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'step' => 'required|in:drse,cac,bianco',
            'status' => 'required|in:pending,in_progress,completed,rejected,duplicate,not_required',
            'agent' => 'nullable|string|max:255',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userEmail = auth()->check() ? auth()->user()->email : 'Système';
            $report = Report::findOrFail($id);

            $workflowLog = $report->workflowLogs()
                ->where('step', $request->step)
                ->firstOrFail();

            $oldStatus = $workflowLog->status;

            $workflowLog->update([
                'status' => $request->status,
                'agent' => $request->agent,
                'notes' => $request->notes,
                'processed_at' => $request->status !== 'pending' ? now() : null
            ]);

            $report->updateWorkflowSummary();

            AuditLogger::logModification(
                $userEmail,
                'Workflow Signalement',
                "Étape {$request->step} changée: {$oldStatus} → {$request->status}",
                $report->reference
            );

            return response()->json([
                'success' => true,
                'message' => 'Workflow mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            AuditLogger::logSystemAction(
                'Système',
                'Modification',
                'Workflow Signalement',
                'Échec',
                "Erreur lors de la mise à jour du workflow: " . $e->getMessage(),
                $id
            );

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du workflow'
            ], 500);
        }
    }

    /**
     * Vérifier le suivi d'un signalement (tracking public)
     */
    public function checkTracking($reference): JsonResponse
    {
        try {
            $tracking = Tracking::where('reference', $reference)->first();

            if (!$tracking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Référence non trouvée'
                ], 404);
            }

            $report = Report::where('reference', $reference)->first();

            AuditLogger::logConsultation(
                'Public',
                'Suivi Signalement',
                "Consultation du suivi pour la référence: {$reference}",
                $reference
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'reference' => $tracking->reference,
                    'status' => $tracking->status,
                    'status_label_fr' => $tracking->getStatusLabel('fr'),
                    'status_label_mg' => $tracking->getStatusLabel('mg'),
                    'last_update' => $tracking->last_update?->toISOString(),
                    'notes' => $tracking->notes,
                    'workflow' => $report?->workflow,
                    'category' => $report?->category,
                    'has_proof' => $report?->has_proof ?? false, // ✅ AJOUT DU CHAMP HAS_PROOF
                    'date_submitted' => $report?->created_at?->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            AuditLogger::logSystemAction(
                'Système',
                'Consultation',
                'Suivi Signalement',
                'Échec',
                "Erreur lors de la consultation du suivi {$reference}: " . $e->getMessage(),
                $reference
            );

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la consultation du suivi'
            ], 500);
        }
    }

    /**
     * Upload de fichiers supplémentaires pour un signalement
     */
    public function uploadFiles(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array',
            'files.*' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240' // 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $report = Report::findOrFail($id);

            if (!$report->canBeModified()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce dossier ne peut plus être modifié'
                ], 403);
            }

            $uploadedFiles = [];
            $existingFiles = $report->files ?? [];

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $fileName = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                    $file->move(public_path('uploads/reports'), $fileName);
                    $uploadedFiles[] = $fileName;
                }
            }

            $allFiles = array_merge($existingFiles, $uploadedFiles);
            $report->update(['files' => $allFiles]);

            $userEmail = auth()->check() ? auth()->user()->email : 'Système';
            AuditLogger::logModification(
                $userEmail,
                'Signalement',
                'Ajout de ' . count($uploadedFiles) . ' fichier(s)',
                $report->reference
            );

            return response()->json([
                'success' => true,
                'message' => count($uploadedFiles) . ' fichier(s) uploadé(s) avec succès',
                'data' => [
                    'uploaded_files' => $uploadedFiles,
                    'all_files' => $allFiles
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Erreur upload fichiers: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload'
            ], 500);
        }
    }

    /**
     * Helper pour obtenir le label du statut
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            'en_cours' => 'En cours',
            'finalise' => 'Finalisé',
            'doublon' => 'Doublon',
            'refuse' => 'Refusé',
            'classifier' => 'Classifié'
        ];

        return $labels[$status] ?? $status;
    }
}