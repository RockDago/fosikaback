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

use App\Models\WorkflowLog;

class ReportController extends Controller
{
    protected $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * ✅ NOUVELLE MÉTHODE POUR CRÉER UN SIGNALEMENT (ADMIN)
     */
    public function createReport(Request $request): JsonResponse
    {
        try {
            // Validation des données
            $validator = Validator::make($request->all(), [
                'category' => 'required|string|max:255',
                'description' => 'required|string|min:10',
                'files.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,mp4|max:51200', 
                'nom_prenom' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'telephone' => 'nullable|string|max:20',
                'city' => 'nullable|string|max:255',
                'province' => 'nullable|string|max:255',
                'region' => 'nullable|string|max:255',
                'type_signalement' => 'required|in:anonyme,non-anonyme',
                'files.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,mp4|max:25600',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Vérification de la taille totale (25 Mo)
            if ($request->hasFile('files')) {
                $totalSize = 0;
                foreach ($request->file('files') as $file) {
                    $totalSize += $file->getSize();
                }
                if ($totalSize > 25 * 1024 * 1024) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La taille totale des fichiers ne doit pas dépasser 25 Mo.'
                    ], 422);
                }
            }

            // Déterminer le type
            $type = $request->type_signalement === 'anonyme' ? 'anonyme' : 'identifie';
            $isAnonymous = $type === 'anonyme';

            // Préparer les données
            $reportData = [
                'type' => $type,
                'category' => $request->category,
                'description' => $request->description,
                'status' => 'en_cours',
                'accept_terms' => true,
                'accept_truth' => true,
                'has_proof' => false // Initialisé à false
            ];

            // Ajouter les informations personnelles si non anonyme
            if (!$isAnonymous) {
                $reportData['name'] = $request->nom_prenom;
                $reportData['email'] = $request->email;
                $reportData['phone'] = $request->telephone;
            }

            // Ajouter les informations géographiques
            $reportData['city'] = $request->city;
            $reportData['province'] = $request->province;
            $reportData['region'] = $request->region;

            // Gérer les fichiers uploadés
            $uploadedFiles = [];
            if ($request->hasFile('files')) {
                // Utiliser la référence générée par le modèle ou en créer une
                $tempRef = Report::generateReference();
                foreach ($request->file('files') as $file) {
                    $fileName = $this->storeFile($file, $tempRef);
                    if ($fileName) {
                        $uploadedFiles[] = $fileName;
                    }
                }

                if (!empty($uploadedFiles)) {
                    $reportData['files'] = $uploadedFiles;
                    $reportData['has_proof'] = true;
                    $reportData['reference'] = $tempRef; // Forcer la référence utilisée pour les fichiers
                }
            }

            // Créer le signalement
            $report = Report::create($reportData);

            // Journal d'audit
            $userEmail = auth()->check() ? auth()->user()->email : 'Système';
            AuditLogger::logCreation(
                $userEmail,
                'Signalement',
                "Nouveau signalement créé: {$report->reference}",
                $report->reference
            );

            Log::info("✅ Signalement créé avec succès", [
                'reference' => $report->reference,
                'category' => $report->category,
                'has_proof' => $report->has_proof,
                'files_count' => count($uploadedFiles)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Signalement créé avec succès',
                'data' => [
                    'reference' => $report->reference,
                    'category' => $report->category,
                    'has_proof' => $report->has_proof,
                    'files_count' => count($uploadedFiles),
                    'created_at' => $report->created_at->toISOString()
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('💥 Erreur création signalement admin: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du signalement',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur interne du serveur'
            ], 500);
        }
    }

    /**
     * ✅ MÉTHODE POUR STOCKER UN FICHIER
     */
    private function storeFile($file, $reference = null)
    {
        try {
            $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
            $extension = strtolower($file->getClientOriginalExtension());
            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            $safeBaseName = Str::slug($baseName, '_') ?: 'piece_jointe';
            $fileName = now()->format('YmdHis') . '_' . Str::random(12) . '_' . $safeBaseName . '.' . $extension;

            // Si pas de référence, on met dans la racine, sinon dans un sous-dossier
            $folder = $reference ? 'reports/' . $reference : 'reports';

            // Stocker
            $path = $file->storeAs($folder, $fileName, 'public');

            // Retourner le chemin relatif pour la base de données
            return $reference ? $reference . '/' . $fileName : $fileName;

        } catch (\Exception $e) {
            Log::error('Erreur stockage fichier: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ MÉTHODE EXISTANTE POUR LE PUBLIC (MAINTENUE)
     */
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
            'files.*' => 'sometimes|file|mimes:jpg,jpeg,png,mp4,pdf|max:25600',
        ]);

        // Vérification de la taille totale (25 Mo)
        if ($request->hasFile('files')) {
            $totalSize = 0;
            foreach ($request->file('files') as $file) {
                $totalSize += $file->getSize();
            }
            if ($totalSize > 25 * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'message' => 'La taille totale des fichiers ne doit pas dépasser 25 Mo.'
                ], 422);
            }
        }

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
                'has_proof' => false // Initialisé à false
            ]);

            // Sauvegarder les fichiers
            $fileNames = [];
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $index => $file) {
                    $fileName = $this->storeFile($file, $report->reference);
                    if ($fileName) {
                        $fileNames[] = $fileName;
                    }
                }

                // Sauvegarder les noms de fichiers et mettre à jour has_proof
                if (!empty($fileNames)) {
                    $report->update([
                        'files' => $fileNames,
                        'has_proof' => true
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Signalement créé avec succès',
                'reference' => $report->reference,
                'has_proof' => $report->has_proof,
                'files' => $fileNames
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur création signalement public: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur interne: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ MÉTHODE POUR SUPPRIMER UN SIGNALEMENT
     */
    public function destroy($id): JsonResponse
    {
        try {
            $report = Report::findOrFail($id);
            $reference = $report->reference;

            // Supprimer les fichiers associés SI ils existent
            if (!empty($report->files)) {
                $files = $report->files;

                // Si files est une chaîne JSON, la décoder
                if (is_string($files)) {
                    $files = json_decode($files, true);
                }

                // Si files est un tableau, supprimer chaque fichier
                if (is_array($files)) {
                    foreach ($files as $filename) {
                        // Chemins possibles
                        $possiblePaths = [
                            storage_path('app/public/reports/' . $filename),
                            public_path('storage/reports/' . $filename),
                            public_path('uploads/reports/' . $filename),
                        ];

                        foreach ($possiblePaths as $filePath) {
                            if (file_exists($filePath)) {
                                try {
                                    unlink($filePath);
                                    \Log::info("Fichier supprimé: $filePath");
                                } catch (\Exception $e) {
                                    \Log::warning("Impossible de supprimer le fichier: $filePath - " . $e->getMessage());
                                }
                            }
                        }
                    }
                }
            }

            // Supprimer le signalement de la base de données
            $report->delete();

            // Journal d'audit (si AuditLogger existe)
            try {
                $userEmail = auth()->check() ? auth()->user()->email : 'Système';
                if (class_exists('AuditLogger')) {
                    \AuditLogger::logSuppression(
                        $userEmail,
                        'Signalement',
                        "Signalement supprimé : $reference",
                        $reference
                    );
                }
            } catch (\Exception $e) {
                \Log::warning("Erreur audit log: " . $e->getMessage());
            }

            \Log::info("Signalement supprimé avec succès: $reference");

            return response()->json([
                'success' => true,
                'message' => 'Signalement supprimé avec succès'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error("Signalement non trouvé: ID $id");
            return response()->json([
                'success' => false,
                'message' => 'Signalement non trouvé'
            ], 404);

        } catch (\Exception $e) {
            \Log::error('Erreur suppression signalement ID ' . $id . ': ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du signalement',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }

    /**
     * ✅ MÉTHODE POUR METTRE À JOUR UN SIGNALEMENT
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'category' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|required|string|min:10',
                'nom_prenom' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'telephone' => 'nullable|string|max:20',
                'city' => 'nullable|string|max:255',
                'province' => 'nullable|string|max:255',
                'region' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $report = Report::findOrFail($id);

            // Vérifier si le signalement peut être modifié
            if (!$report->canBeModified()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce signalement ne peut plus être modifié'
                ], 403);
            }

            $updateData = [];

            // Mettre à jour les champs fournis
            if ($request->has('category')) $updateData['category'] = $request->category;
            if ($request->has('description')) $updateData['description'] = $request->description;
            if ($request->has('nom_prenom')) $updateData['name'] = $request->nom_prenom;
            if ($request->has('email')) $updateData['email'] = $request->email;
            if ($request->has('telephone')) $updateData['phone'] = $request->telephone;
            if ($request->has('city')) $updateData['city'] = $request->city;
            if ($request->has('province')) $updateData['province'] = $request->province;
            if ($request->has('region')) $updateData['region'] = $request->region;

            $report->update($updateData);

            // Journal d'audit
            $userEmail = auth()->check() ? auth()->user()->email : 'Système';
            AuditLogger::logModification(
                $userEmail,
                'Signalement',
                "Signalement modifié: {$report->reference}",
                $report->reference
            );

            return response()->json([
                'success' => true,
                'message' => 'Signalement modifié avec succès',
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur modification signalement: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification du signalement'
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
     * Lister tous les signalements avec workflow
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userEmail = auth()->check() ? auth()->user()->email : 'API Guest';

            // Journal d'audit
            if (class_exists('App\\Services\\AuditLogger')) {
                AuditLogger::logConsultation(
                    $userEmail,
                    'Signalements',
                    "Consultation de la liste des signalements",
                    null
                );
            }

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
                // ✅ CORRECTION : GESTION SÉCURISÉE DES DATES
                $createdAt = $report->created_at ? $report->created_at->toISOString() : now()->toISOString();
                $updatedAt = $report->updated_at ? $report->updated_at->toISOString() : now()->toISOString();

                // Workflow depuis la base de données
                $workflowData = [
                    'drse' => [
                        'date' => $createdAt, // ✅ UTILISER LA VARIABLE CORRIGÉE
                        'status' => 'in_progress',
                        'progress' => 33,
                        'agent' => 'DAAQ / DRSE'
                    ],
                    'cac' => [
                        'date' => null,
                        'status' => 'pending',
                        'progress' => 0,
                        'agent' => 'DAAQ / CAC / DAJ'
                    ],
                    'bianco' => [
                        'date' => null,
                        'status' => 'pending',
                        'progress' => 0,
                        'agent' => 'DAAQ / BIANCO'
                    ]
                ];

                // Utiliser le workflow de la base s'il existe
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

                // Gérer les signalements sans preuves
                $hasProof = $report->has_proof ?? (count($filesArray) > 0);

                return [
                    'id' => $report->id,
                    'reference' => $report->reference ?? 'REF-' . $report->id,
                    'title' => $report->title ?? 'Sans titre',
                    'description' => $report->description ?? 'Aucune description',
                    'category' => $report->category ?? 'divers',
                    'status' => $report->status ?? 'traitement_classification',
                    'type' => $report->type ?? 'identifie',
                    'is_anonymous' => $report->type === 'anonyme',
                    'name' => $report->name ?? 'Anonyme',
                    'email' => $report->email ?? '',
                    'phone' => $report->phone ?? '',
                    'files' => $filesArray,
                    'has_proof' => $hasProof,
                    'workflow' => $workflowData,
                    'created_at' => $createdAt, // ✅ UTILISER LA VARIABLE CORRIGÉE
                    'updated_at' => $updatedAt, // ✅ UTILISER LA VARIABLE CORRIGÉE
                    'region' => $report->region ?? '',
                    'city' => $report->city ?? '',
                    'province' => $report->province ?? ''
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Rapports récupérés avec succès',
                'data' => $formattedReports,
                'count' => $reports->count()
            ], 200, [], JSON_UNESCAPED_SLASHES);

        } catch (\Exception $e) {
            if (class_exists('App\\Services\\AuditLogger')) {
                AuditLogger::logSystemAction(
                    'Système',
                    'Consultation',
                    'Signalements',
                    'Échec',
                    "Erreur lors de la récupération des rapports: " . $e->getMessage()
                );
            }

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
    private function getFile($filename, $requireAuth = false)
    {
        try {
            $decodedFilename = urldecode($filename);

            // Vérification de sécurité (on autorise / pour les sous-dossiers de référence)
            if (strpos($decodedFilename, '..') !== false) {
                return response()->json(['error' => 'Nom de fichier invalide'], 400);
            }

            $filePath = storage_path('app/public/reports/' . $decodedFilename);

            if (!file_exists($filePath)) {
                // Essayer de chercher si le fichier est dans un sous-dossier (en cherchant dans la DB)
                $pureFilename = basename($decodedFilename);
                $report = Report::where('files', 'LIKE', '%' . $pureFilename . '%')->first();

                if ($report && !empty($report->files)) {
                    foreach ($report->files as $f) {
                        if (basename($f) === $pureFilename) {
                            $filePath = storage_path('app/public/reports/' . $f);
                            break;
                        }
                    }
                }

                if (!file_exists($filePath)) {
                    return response()->json(['error' => 'Fichier non trouvé'], 404);
                }
            }

            // Vérification MIME type
            $mimeType = mime_content_type($filePath);

            if (strpos($mimeType, 'image/') === 0) {
                return response()->file($filePath);
            } elseif ($mimeType === 'application/pdf') {
                return response()->file($filePath);
            } else {
                return response()->download($filePath, $decodedFilename);
            }

        } catch (\Exception $e) {
            Log::error('File access error: ' . $e->getMessage());
            return response()->json(['error' => 'Erreur lors de l\'accès au fichier'], 500);
        }
    }

    /**
     * Télécharger un fichier
     */
    private function downloadFile($filename, $requireAuth = false)
    {
        try {
            $decodedFilename = urldecode($filename);

            // Vérification de sécurité
            if (strpos($decodedFilename, '..') !== false) {
                return response()->json(['error' => 'Nom de fichier invalide'], 400);
            }

            $filePath = storage_path('app/public/reports/' . $decodedFilename);

            if (!file_exists($filePath)) {
                // Recherche dans les sous-dossiers
                $pureFilename = basename($decodedFilename);
                $report = Report::where('files', 'LIKE', '%' . $pureFilename . '%')->first();

                if ($report && !empty($report->files)) {
                    foreach ($report->files as $f) {
                        if (basename($f) === $pureFilename) {
                            $filePath = storage_path('app/public/reports/' . $f);
                            break;
                        }
                    }
                }

                if (!file_exists($filePath)) {
                    return response()->json(['error' => 'Fichier non trouvé'], 404);
                }
            }

            return response()->download($filePath, basename($decodedFilename));

        } catch (\Exception $e) {
            Log::error('File download error: ' . $e->getMessage());
            return response()->json(['error' => 'Erreur lors du téléchargement'], 500);
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
            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fichier introuvable'
                ], 404);
            }

            $storedFilename = $filename;
            foreach (($report->files ?? []) as $file) {
                if (basename((string) $file) === $filename) {
                    $storedFilename = (string) $file;
                    break;
                }
            }

            // Vérifier si le fichier existe ou peut être créé
            $filePath = $this->fileService->findExistingFile($storedFilename, $report->reference);

            if (!$filePath) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de générer le fichier: ' . $filename
                ], 404);
            }

            // Générer les URLs publiques
            $encodedFilename = implode('/', array_map('rawurlencode', explode('/', $storedFilename)));
            $baseUrl = url('/api');
            $url = $baseUrl . '/files/public/' . $encodedFilename;
            $downloadUrl = $baseUrl . '/files/public/' . $encodedFilename . '/download';

            return response()->json([
                'success' => true,
                'url' => $url,
                'download_url' => $downloadUrl,
                'filename' => $storedFilename,
                'file_exists' => file_exists($filePath),
                'file_size' => file_exists($filePath) ? filesize($filePath) : 0,
                'reference' => $report->reference
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
                $filePath = $this->fileService->findExistingFile($file, $reference);
                $encodedFilename = implode('/', array_map('rawurlencode', explode('/', $file)));
                $filesWithUrls[] = [
                    'filename' => $file,
                    'view_url' => url('/api/files/public/' . $encodedFilename),
                    'download_url' => url('/api/files/public/' . $encodedFilename . '/download'),
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
     * Mettre à jour le statut d'un signalement avec workflow
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:traitement_classification,investigation,transmis_autorite,refuse,classifier', // ✅ AJOUTER LES STATUTS MANQUANTS
            'notes' => 'nullable|string|max:500'
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
            $newStatus = $request->status;

            Log::info("🚀 Mise à jour statut - Report: {$report->reference}, Ancien: {$oldStatus}, Nouveau: {$newStatus}");

            // ✅ WORKFLOW CORRECT POUR CHAQUE STATUT
            $workflow = [];

            switch ($newStatus) {
                case 'traitement_classification':
                    $workflow = [
                        'drse' => [
                            'date' => now()->toDateTimeString(),
                            'status' => 'in_progress',
                            'progress' => 33,
                            'agent' => 'DAAQ / DRSE'
                        ],
                        'cac' => [
                            'date' => null,
                            'status' => 'pending',
                            'progress' => 0,
                            'agent' => 'DAAQ / CAC / DAJ'
                        ],
                        'bianco' => [
                            'date' => null,
                            'status' => 'pending',
                            'progress' => 0,
                            'agent' => 'DAAQ / BIANCO'
                        ]
                    ];
                    break;

                case 'investigation':
                    $workflow = [
                        'drse' => [
                            'date' => now()->toDateTimeString(),
                            'status' => 'completed',
                            'progress' => 100,
                            'agent' => 'DAAQ / DRSE'
                        ],
                        'cac' => [
                            'date' => now()->toDateTimeString(),
                            'status' => 'in_progress',
                            'progress' => 66,
                            'agent' => 'DAAQ / CAC / DAJ'
                        ],
                        'bianco' => [
                            'date' => null,
                            'status' => 'pending',
                            'progress' => 0,
                            'agent' => 'DAAQ / BIANCO'
                        ]
                    ];
                    break;

                case 'transmis_autorite':
                    $workflow = [
                        'drse' => [
                            'date' => now()->toDateTimeString(),
                            'status' => 'completed',
                            'progress' => 100,
                            'agent' => 'DAAQ / DRSE'
                        ],
                        'cac' => [
                            'date' => now()->toDateTimeString(),
                            'status' => 'completed',
                            'progress' => 100,
                            'agent' => 'DAAQ / CAC / DAJ'
                        ],
                        'bianco' => [
                            'date' => now()->toDateTimeString(),
                            'status' => 'in_progress',
                            'progress' => 66,
                            'agent' => 'DAAQ / BIANCO'
                        ]
                    ];
                    break;

                case 'classifier':
                    $workflow = [
                        'drse' => [
                            'date' => now()->toDateTimeString(),
                            'status' => 'completed',
                            'progress' => 100,
                            'agent' => 'DAAQ / DRSE'
                        ],
                        'cac' => [
                            'date' => now()->toDateTimeString(),
                            'status' => 'completed',
                            'progress' => 100,
                            'agent' => 'DAAQ / CAC / DAJ'
                        ],
                        'bianco' => [
                            'date' => now()->toDateTimeString(),
                            'status' => 'completed',
                            'progress' => 100,
                            'agent' => 'DAAQ / BIANCO'
                        ]
                    ];
                    break;

                case 'refuse':
                    $workflow = [
                        'drse' => [
                            'date' => now()->toDateTimeString(),
                            'status' => 'rejected',
                            'progress' => 0,
                            'agent' => 'DAAQ / DRSE'
                        ],
                        'cac' => [
                            'date' => null,
                            'status' => 'not_required',
                            'progress' => 0,
                            'agent' => 'DAAQ / CAC / DAJ'
                        ],
                        'bianco' => [
                            'date' => null,
                            'status' => 'not_required',
                            'progress' => 0,
                            'agent' => 'DAAQ / BIANCO'
                        ]
                    ];
                    break;
            }

            Log::info("🔄 Nouveau workflow pour {$newStatus}:", $workflow);

            // ✅ SAUVEGARDER LE STATUT ET LE WORKFLOW
            $report->update([
                'status' => $newStatus,
                'workflow' => $workflow
            ]);

            Log::info("✅ Workflow sauvegardé dans la base");

            // ✅ METTRE À JOUR LE TRACKING
            if ($report->tracking) {
                $report->tracking->update([
                    'status' => $newStatus,
                    'last_update' => now(),
                    'notes' => $request->notes
                ]);
                Log::info("✅ Tracking mis à jour");
            } else {
                Tracking::create([
                    'reference' => $report->reference,
                    'status' => $newStatus,
                    'last_update' => now(),
                    'notes' => $request->notes
                ]);
                Log::info("✅ Tracking créé");
            }

            // ✅ JOURNALISATION
            if (class_exists('App\\Services\\AuditLogger')) {
                AuditLogger::logModification(
                    $userEmail,
                    'Signalement',
                    "Changement de statut: {$oldStatus} → {$newStatus}",
                    $report->reference
                );
            }

            Log::info("🎯 Statut du signalement {$report->reference} changé de {$oldStatus} à {$newStatus}");

            // ✅ RÉPONSE AVEC WORKFLOW POUR DEBUG
            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour avec succès',
                'data' => [
                    'reference' => $report->reference,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'workflow' => $workflow,
                    'workflow_saved' => $report->fresh()->workflow
                ]
            ], 200, [], JSON_UNESCAPED_SLASHES);

        } catch (\Exception $e) {
            Log::error("💥 Erreur updateStatus: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper pour obtenir le label du statut
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            'traitement_classification' => 'Traitement et Classification',
            'investigation' => 'Investigation',
            'transmis_autorite' => 'Transmis aux autorités compétentes',
            'refuse' => 'Refusé',
            'classifier' => 'Classifié'
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Récupérer le workflow d'un signalement
     */
    public function getWorkflow($id): JsonResponse
    {
        try {
            $report = Report::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'workflow' => $report->workflow,
                    'workflow_logs' => $report->workflowLogs,
                    'status' => $report->status
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du workflow'
            ], 500);
        }
    }

    /**
     * Mettre à jour une étape spécifique du workflow
     */
    public function updateWorkflowStep(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'step' => 'required|in:drse,cac,bianco',
            'status' => 'required|in:pending,in_progress,completed,rejected,duplicate,not_required',
            'progress' => 'nullable|integer|min:0|max:100',
            'notes' => 'nullable|string|max:500'
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

            $workflow = $report->workflow ?? [];

            // Mettre à jour l'étape spécifique
            $workflow[$request->step] = [
                'date' => $request->status !== 'pending' ? now()->toDateTimeString() : null,
                'status' => $request->status,
                'progress' => $request->progress ?? $workflow[$request->step]['progress'] ?? 0,
                'agent' => $workflow[$request->step]['agent'] ?? 'DAAQ / ' . strtoupper($request->step)
            ];

            // Mettre à jour le rapport
            $report->update(['workflow' => $workflow]);

            // Mettre à jour le WorkflowLog
            $log = $report->workflowLogs()->where('step', strtoupper($request->step))->first();

            if ($log) {
                $log->update([
                    'status' => $request->status,
                    'processed_at' => $request->status !== 'pending' ? now() : null,
                    'notes' => $request->notes
                ]);
            } else {
                WorkflowLog::create([
                    'report_id' => $report->id,
                    'step' => strtoupper($request->step),
                    'status' => $request->status,
                    'agent' => $workflow[$request->step]['agent'],
                    'notes' => $request->notes,
                    'processed_at' => $request->status !== 'pending' ? now() : null
                ]);
            }

            AuditLogger::logModification(
                $userEmail,
                'Workflow Signalement',
                "Étape {$request->step} mise à jour: {$request->status}",
                $report->reference
            );

            return response()->json([
                'success' => true,
                'message' => 'Étape du workflow mise à jour avec succès',
                'data' => [
                    'step' => $request->step,
                    'status' => $request->status,
                    'progress' => $request->progress,
                    'workflow' => $workflow
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Erreur updateWorkflowStep: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'étape du workflow'
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
     * ✅ ROUTE PUBLIQUE - Suivi de dossier avec données complètes (POUR VISITEURS)
     */
    public function publicTracking($reference): JsonResponse
    {
        try {
            $report = Report::where('reference', $reference)->first();

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Référence non trouvée'
                ], 404);
            }

            // Vérifier si anonyme
            $isAnonymous = $report->type === 'anonyme';

            // Gestion des fichiers
            $filesArray = [];
            if (!empty($report->files)) {
                if (is_string($report->files)) {
                    $decodedFiles = json_decode($report->files, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedFiles)) {
                        $filesArray = $decodedFiles;
                    }
                } elseif (is_array($report->files)) {
                    $filesArray = $report->files;
                }
            }

            // Workflow
            $workflowData = [];
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

            // Si pas de workflow, créer un workflow par défaut
            if (empty($workflowData)) {
                $workflowData = [
                    'drse' => [
                        'date' => $report->created_at->toDateTimeString(),
                        'status' => 'in_progress',
                        'progress' => 33,
                        'agent' => 'DAAQ / DRSE'
                    ],
                    'cac' => [
                        'date' => null,
                        'status' => 'pending',
                        'progress' => 0,
                        'agent' => 'DAAQ / CAC / DAJ'
                    ],
                    'bianco' => [
                        'date' => null,
                        'status' => 'pending',
                        'progress' => 0,
                        'agent' => 'DAAQ / BIANCO'
                    ]
                ];
            }

            // Données à retourner
            $responseData = [
                'success' => true,
                'data' => [
                    'reference' => $report->reference,
                    'status' => $report->status,
                    'category' => $report->category,
                    'created_at' => $report->created_at->toISOString(),
                    'updated_at' => $report->updated_at->toISOString(),
                    'has_proof' => $report->has_proof ?? false,
                    'is_anonymous' => $isAnonymous,
                    'type' => $report->type,

                    // Données personnelles (masquées si anonyme)
                    'name' => $isAnonymous ? 'Anonyme' : ($report->name ?? ''),
                    'email' => $isAnonymous ? '' : ($report->email ?? ''),
                    'phone' => $isAnonymous ? '' : ($report->phone ?? ''),

                    // Description
                    'description' => $report->description ?? '',

                    // Localisation
                    'city' => $report->city ?? '',
                    'province' => $report->province ?? '',
                    'region' => $report->region ?? '',
                    'address' => $report->address ?? '',

                    // Fichiers
                    'files' => $filesArray,

                    // Workflow
                    'workflow' => $workflowData,

                    // Autres champs
                    'accept_terms' => $report->accept_terms ?? true,
                    'accept_truth' => $report->accept_truth ?? true,
                ]
            ];

            return response()->json($responseData, 200, [], JSON_UNESCAPED_SLASHES);

        } catch (\Exception $e) {
            \Log::error("Erreur publicTracking {$reference}: " . $e->getMessage());

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
     * ✅ Récupérer les dossiers assignés à un investigateur
     */
    public function getAssignedReports(Request $request)
    {
        try {
            $user = $request->user();

            // Récupérer les rapports assignés à l'utilisateur connecté
            $reports = Report::where('assigned_to', $user->id)
                ->with(['category', 'assignedUser'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculer les statistiques
            $stats = [
                'dossiersAssignes' => $reports->count(),
                'soumisBianco' => $reports->where('status', 'finalise')->count(),
                'enquetesCompletees' => $reports->where('status', 'classifier')->count(),
                'totalDossiers' => $reports->count(),
                'byCategory' => $reports->groupBy('category')->map->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $reports,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des rapports assignés',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ Assigner un dossier à un investigateur
     */
    public function assignInvestigator(Request $request, $id): JsonResponse
    {
        // Normaliser la valeur avant validation
        $assignedTo = $request->assigned_to;

        // Si le front envoie "Non assigné", on remplace par NULL
        if ($assignedTo === "Non assigné" || $assignedTo === "" || $assignedTo === null) {
            $assignedTo = null;
        }

        // Validation (null est autorisé)
        $validator = Validator::make([
            'assigned_to' => $assignedTo,
            'reason'      => $request->reason
        ], [
            'assigned_to' => 'nullable|integer|exists:team_users,id',
            'reason'      => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $report = Report::findOrFail($id);
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }

            $report->update([
                'assigned_to' => $assignedTo
            ]);

            \Log::info("Dossier {$report->reference} assigné à : " . ($assignedTo ?? "Aucun"));

            return response()->json([
                'success' => true,
                'message' => 'Dossier assigné avec succès',
                'data' => [
                    'reference' => $report->reference,
                    'assigned_to' => $assignedTo,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Erreur assignation: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'assignation',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur serveur'
            ], 500);
        }
    }

    /**
     * ✅ MÉTHODE POUR METTRE À JOUR LES PIÈCES JOINTES D'UN SIGNALEMENT
     */
    public function updateFiles(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'files_to_remove' => 'nullable|array',
                'files_to_remove.*' => 'string|max:255',
                'new_files.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,mp4|max:51200',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->fails()
                ], 422);
            }

            $report = Report::findOrFail($id);

            // Vérifier les permissions
            if (!auth()->check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentification requise'
                ], 403);
            }

            // Supprimer les fichiers spécifiés
            $currentFiles = $report->files ?? [];
            if (is_string($currentFiles)) {
                $currentFiles = json_decode($currentFiles, true) ?? [];
            }

            $filesToRemove = $request->input('files_to_remove', []);
            foreach ($filesToRemove as $filename) {
                // Chercher le fichier dans le système
                $filePath = $this->fileService->findExistingFile($filename);
                if ($filePath && file_exists($filePath)) {
                    unlink($filePath);
                }

                // Retirer du tableau
                $currentFiles = array_filter($currentFiles, function($file) use ($filename) {
                    return $file !== $filename;
                });
            }

            // Ajouter les nouveaux fichiers
            $newFiles = [];
            if ($request->hasFile('new_files')) {
                foreach ($request->file('new_files') as $file) {
                    $fileName = $this->storeFile($file);
                    if ($fileName) {
                        $newFiles[] = $fileName;
                    }
                }
            }

            // Fusionner les fichiers
            $allFiles = array_merge($currentFiles, $newFiles);

            // Mettre à jour le rapport
            $report->update([
                'files' => $allFiles,
                'has_proof' => !empty($allFiles)
            ]);

            // Journal d'audit
            $userEmail = auth()->user()->email;
            AuditLogger::logModification(
                $userEmail,
                'Signalement',
                "Mise à jour des pièces jointes: {$report->reference}",
                $report->reference
            );

            return response()->json([
                'success' => true,
                'message' => 'Pièces jointes mises à jour avec succès',
                'data' => [
                    'files' => $allFiles,
                    'has_proof' => !empty($allFiles)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur mise à jour fichiers: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour des fichiers'
            ], 500);
        }
    }

    /**
     * Ajouter des fichiers à un signalement existant (route publique)
     */
    public function addFilesToReport(Request $request, $reference)
    {
        \Log::info('addFilesToReport appelée', ['reference' => $reference]);

        try {
            // Valider la référence
            $report = \App\Models\Report::where('reference', $reference)->first();

            if (!$report) {
                \Log::warning('Dossier non trouvé', ['reference' => $reference]);
                return response()->json([
                    'success' => false,
                    'message' => 'Dossier non trouvé'
                ], 404);
            }

            \Log::info('Dossier trouvé', [
                'id' => $report->id,
                'status' => $report->status,
                'type' => $report->type
            ]);

            // Vérifier si le dossier est encore ouvert au public
            if (!in_array($report->status, ['en_cours', 'traitement_classification'])) {
                \Log::warning('Dossier fermé aux modifications', ['status' => $report->status]);
                return response()->json([
                    'success' => false,
                    'message' => 'Ce dossier n\'accepte plus de nouveaux fichiers'
                ], 400);
            }

            // Vérifier si c'est un signalement anonyme
            $isAnonymous = $report->type === 'anonyme' ||
                (strtolower($report->name) === 'anonyme');

            if ($isAnonymous) {
                \Log::warning('Tentative d\'ajout sur dossier anonyme');
                return response()->json([
                    'success' => false,
                    'message' => 'Les signalements anonymes ne peuvent pas ajouter de fichiers'
                ], 400);
            }

            \Log::info('Validation des fichiers reçus', ['files_count' => count($request->allFiles())]);

            // Valider les fichiers
            $validator = \Validator::make($request->all(), [
                'files' => 'required|array|min:1|max:5',
                'files.*' => 'file|mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt|max:2048', // 2MB par fichier
            ]);

            if ($validator->fails()) {
                \Log::error('Validation échouée', ['errors' => $validator->errors()->all()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()->all()
                ], 422);
            }

            $uploadedFiles = [];
            $currentFiles = [];

            // Récupérer les fichiers existants
            if (!empty($report->files)) {
                if (is_string($report->files)) {
                    try {
                        $currentFiles = json_decode($report->files, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $currentFiles = [];
                        }
                    } catch (\Exception $e) {
                        $currentFiles = [];
                    }
                } elseif (is_array($report->files)) {
                    $currentFiles = $report->files;
                }
            }

            \Log::info('Fichiers actuels', ['count' => count($currentFiles)]);

            // Vérifier la limite de fichiers
            if (count($currentFiles) >= 10) {
                \Log::warning('Limite de fichiers atteinte');
                return response()->json([
                    'success' => false,
                    'message' => 'Limite de 10 fichiers atteinte pour ce dossier'
                ], 400);
            }

            // Traiter chaque fichier
            foreach ($request->file('files') as $index => $file) {
                if (count($currentFiles) + count($uploadedFiles) >= 10) {
                    \Log::info('Limite atteinte, arrêt du traitement');
                    break;
                }

                try {
                    $originalName = $file->getClientOriginalName();
                    $safeName = preg_replace('/[^A-Za-z0-9\.\-]/', '_', $originalName);
                    $fileName = 'report_' . $report->id . '_' . time() . '_' . $index . '_' . $safeName;

                    \Log::info('Upload du fichier', [
                        'original' => $originalName,
                        'safe' => $safeName,
                        'final' => $fileName
                    ]);

                    // Stocker le fichier
                    $path = $file->storeAs('reports', $fileName, 'public');

                    if ($path) {
                        $fileData = [
                            'filename' => $fileName,
                            'original_name' => $originalName,
                            'path' => $path,
                            'size' => $file->getSize(),
                            'mime_type' => $file->getMimeType(),
                            'uploaded_at' => now()->toDateTimeString()
                        ];

                        $uploadedFiles[] = $fileData;
                        $currentFiles[] = $fileData;

                        \Log::info('Fichier uploadé avec succès', ['path' => $path]);
                    } else {
                        \Log::error('Échec du stockage du fichier', ['file' => $originalName]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Erreur lors du traitement du fichier', [
                        'file' => $originalName ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if (empty($uploadedFiles)) {
                \Log::error('Aucun fichier n\'a pu être uploadé');
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun fichier n\'a pu être uploadé'
                ], 500);
            }

            // Mettre à jour le dossier
            $report->files = json_encode($currentFiles);
            $report->has_proof = true;
            $report->updated_at = now();
            $report->save();

            \Log::info('Dossier mis à jour avec succès', [
                'total_files' => count($currentFiles),
                'new_files' => count($uploadedFiles)
            ]);

            // Journal d'audit (optionnel)
            try {
                $this->logActivity(
                    'AJOUT_FICHIER',
                    'Rapport',
                    $report->id,
                    'Ajout de ' . count($uploadedFiles) . ' fichier(s) au dossier ' . $reference,
                    'success'
                );
            } catch (\Exception $e) {
                \Log::warning('Erreur lors du journal d\'audit', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Fichiers ajoutés avec succès',
                'files' => $uploadedFiles,
                'total_files' => count($currentFiles)
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur critique dans addFilesToReport: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'reference' => $reference
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur interne du serveur',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * ✅ MÉTHODE POUR ACCÉDER AUX FICHIERS PUBLIQUES
     */
    private function findReportForPublicFile(string $requestedFilename): ?Report
    {
        $basename = basename(str_replace('\\', '/', $requestedFilename));
        $like = addcslashes($basename, '%_\\');

        return Report::where('files', 'LIKE', '%' . $like . '%')
            ->get()
            ->first(function (Report $report) use ($requestedFilename, $basename) {
                $files = $report->files ?? [];
                if (is_string($files)) {
                    $files = json_decode($files, true) ?: [];
                }

                foreach ($files as $file) {
                    $storedFile = (string) $file;
                    if ($storedFile === $requestedFilename || basename(str_replace('\\', '/', $storedFile)) === $basename) {
                        return true;
                    }
                }

                return false;
            });
    }

    public function getPublicFile($filename): Response
    {
        try {
            // Nettoyer le nom du fichier
            $decodedFilename = urldecode($filename);

            // Vérification de sécurité
            if (strpos($decodedFilename, '..') !== false) {
                abort(400, 'Nom de fichier invalide');
            }

            // Chercher le rapport pour voir s'il y a un sous-dossier de référence
            $report = $this->findReportForPublicFile($decodedFilename);
            if (!$report) {
                abort(404, 'Fichier non trouvÃ©');
            }

            // Chercher dans plusieurs chemins possibles
            $possiblePaths = [
                storage_path('app/public/reports/' . $decodedFilename),
                public_path('storage/reports/' . $decodedFilename),
                public_path('uploads/reports/' . $decodedFilename),
            ];

            if ($report) {
                array_unshift($possiblePaths, storage_path('app/public/reports/' . $report->reference . '/' . $decodedFilename));
            }

            $filePath = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $filePath = $path;
                    break;
                }
            }

            if (!$filePath) {
                // Chercher dans la base de données si le fichier existe
                $report = $this->findReportForPublicFile($decodedFilename);

                if (!$report) {
                    abort(404, 'Fichier non trouvé');
                }

                // Essayer de générer le fichier si possible
                $generatedPath = $this->fileService->findExistingFile($decodedFilename, $report->reference);

                if (!$generatedPath || !file_exists($generatedPath)) {
                    abort(404, 'Fichier non trouvé');
                }

                $filePath = $generatedPath;
            }

            // Déterminer le type de contenu
            $mimeType = mime_content_type($filePath);

            // Retourner le fichier
            return response()->file($filePath, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . basename($decodedFilename) . '"',
                'Cache-Control' => 'public, max-age=31536000',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => 'sandbox',
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur accès fichier public: ' . $e->getMessage());

            if (config('app.debug')) {
                return response()->json([
                    'error' => 'Erreur lors de l\'accès au fichier',
                    'message' => $e->getMessage()
                ], 500);
            }

            abort(500, 'Erreur lors de l\'accès au fichier');
        }
    }

    /**
     * ✅ MÉTHODE PUBLIQUE POUR TÉLÉCHARGER UN FICHIER
     */
    public function downloadPublicFile($filename): Response
    {
        try {
            // Nettoyer le nom du fichier
            $decodedFilename = urldecode($filename);

            // Vérification de sécurité
            if (strpos($decodedFilename, '..') !== false) {
                abort(400, 'Nom de fichier invalide');
            }

            // Chercher le rapport pour voir s'il y a un sous-dossier de référence
            $report = $this->findReportForPublicFile($decodedFilename);
            if (!$report) {
                abort(404, 'Fichier non trouvÃ©');
            }

            // Chercher dans plusieurs chemins possibles
            $possiblePaths = [
                storage_path('app/public/reports/' . $decodedFilename),
                public_path('storage/reports/' . $decodedFilename),
                public_path('uploads/reports/' . $decodedFilename),
            ];

            if ($report) {
                array_unshift($possiblePaths, storage_path('app/public/reports/' . $report->reference . '/' . $decodedFilename));
            }

            $filePath = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $filePath = $path;
                    break;
                }
            }

            // Si fichier non trouvé, chercher dans la base de données
            if (!$filePath) {
                $report = $this->findReportForPublicFile($decodedFilename);

                if (!$report) {
                    abort(404, 'Fichier non trouvé');
                }

                // Essayer de générer le fichier si possible
                $generatedPath = $this->fileService->findExistingFile($decodedFilename, $report->reference);

                if (!$generatedPath || !file_exists($generatedPath)) {
                    abort(404, 'Fichier non trouvé');
                }

                $filePath = $generatedPath;
            }

            // Déterminer le type de contenu
            $mimeType = mime_content_type($filePath);

            // Retourner le fichier en téléchargement
            return response()->download($filePath, basename($decodedFilename), [
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur téléchargement fichier public: ' . $e->getMessage());

            if (config('app.debug')) {
                return response()->json([
                    'error' => 'Erreur lors du téléchargement',
                    'message' => $e->getMessage()
                ], 500);
            }

            abort(500, 'Erreur lors du téléchargement');
        }
    }

    /**
     * ✅ MÉTHODE PUBLIQUE POUR VISUALISER UN FICHIER
     */
    public function viewPublicFile($filename): Response
    {
        try {
            // Nettoyer le nom du fichier
            $decodedFilename = urldecode($filename);

            // Vérification de sécurité
            if (strpos($decodedFilename, '..') !== false) {
                abort(400, 'Nom de fichier invalide');
            }

            // Chercher dans plusieurs chemins possibles
            $possiblePaths = [
                storage_path('app/public/reports/' . $decodedFilename),
                public_path('storage/reports/' . $decodedFilename),
                public_path('uploads/reports/' . $decodedFilename),
            ];

            // Chercher le rapport pour voir s'il y a un sous-dossier de référence
            $report = $this->findReportForPublicFile($decodedFilename);
            if (!$report) {
                abort(404, 'Fichier non trouvÃ©');
            }
            if ($report) {
                array_unshift($possiblePaths, storage_path('app/public/reports/' . $report->reference . '/' . $decodedFilename));
            }

            $filePath = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $filePath = $path;
                    break;
                }
            }

            if (!$filePath) {
                if ($report) {
                    $generatedPath = $this->fileService->findExistingFile($decodedFilename, $report->reference);
                    if ($generatedPath && file_exists($generatedPath)) {
                        $filePath = $generatedPath;
                    }
                }

                if (!$filePath) {
                    abort(404, 'Fichier non trouvé');
                }
            }

            // Déterminer le type de contenu
            $mimeType = mime_content_type($filePath);

            // Retourner le fichier pour visualisation
            return response()->file($filePath, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . basename($decodedFilename) . '"',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => 'sandbox',
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur visualisation fichier public: ' . $e->getMessage());
            abort(500, 'Erreur lors de la visualisation');
        }
    }

    public function getAdminFile($filename)
    {
        try {
            $user = request()->user();
            \Log::info('getAdminFile debug', [
                'user_id' => $user?->id,
                'email'   => $user?->email,
                'has_token' => request()->bearerToken() !== null,
                'session_2fa' => session('2fa_verified', false),
            ]);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token invalide ou non authentifié',
                    'requires_logout' => true,
                    'error_code' => 'UNAUTHENTICATED',
                ], 401);
            }

            return $this->getPublicFile($filename);
        } catch (\Exception $e) {
            \Log::error('Erreur dans getAdminFile', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'accès au fichier',
            ], 500);
        }
    }

    public function downloadAdminFile($filename)
    {
        try {
            $user = request()->user();
            \Log::info('downloadAdminFile debug', [
                'user_id' => $user?->id,
                'email'   => $user?->email,
                'has_token' => request()->bearerToken() !== null,
                'session_2fa' => session('2fa_verified', false),
            ]);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token invalide ou non authentifié',
                    'requires_logout' => true,
                    'error_code' => 'UNAUTHENTICATED',
                ], 401);
            }

            return $this->downloadPublicFile($filename);
        } catch (\Exception $e) {
            \Log::error('Erreur dans downloadAdminFile', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement',
            ], 500);
        }
    }
}
