<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Report;
use App\Services\FileService;

class GenerateMissingFiles extends Command
{
    protected $signature = 'files:generate-missing';
    protected $description = 'Générer tous les fichiers manquants à partir de la base de données';

    protected $fileService;

    public function __construct(FileService $fileService)
    {
        parent::__construct();
        $this->fileService = $fileService;
    }

    public function handle()
    {
        $this->info('🔍 Recherche des rapports avec fichiers...');
        
        $reports = Report::whereNotNull('files')->get();
        
        $this->info("📊 Nombre de rapports à traiter: " . $reports->count());
        
        $totalFiles = 0;
        $createdFiles = 0;
        
        foreach ($reports as $report) {
            $files = $report->files;
            if (is_string($files)) {
                $files = json_decode($files, true);
            }
            
            if (is_array($files) && count($files) > 0) {
                $this->info("📁 Rapport {$report->reference} - " . count($files) . " fichiers");
                
                foreach ($files as $filename) {
                    $totalFiles++;
                    
                    // Vérifier si le fichier existe déjà
                    $existingPath = $this->fileService->findExistingFile($filename);
                    
                    if ($existingPath) {
                        $this->line("   ✅ Existe déjà: {$filename}");
                    } else {
                        // Créer le fichier
                        $filePath = $this->fileService->createDemoFile($filename, $report->reference);
                        if ($filePath && file_exists($filePath)) {
                            $this->line("   📄 Créé: {$filename}");
                            $createdFiles++;
                        } else {
                            $this->error("   ❌ Erreur création: {$filename}");
                        }
                    }
                }
            }
        }
        
        $this->info("\n📈 RÉSULTAT:");
        $this->info("Total fichiers dans la base: " . $totalFiles);
        $this->info("Fichiers créés: " . $createdFiles);
        $this->info("Fichiers déjà existants: " . ($totalFiles - $createdFiles));
        
        if ($createdFiles > 0) {
            $this->info("🎉 Les fichiers ont été générés avec succès!");
            $this->info("📁 Emplacement: public/uploads/reports/");
        } else {
            $this->info("ℹ️  Aucun nouveau fichier à créer.");
        }
    }
}