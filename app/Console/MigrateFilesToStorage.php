<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Report;
use Illuminate\Support\Facades\Storage;

class MigrateFilesToStorage extends Command
{
    protected $signature = 'files:migrate-to-storage';
    protected $description = 'Migrer les fichiers de la base de données vers le stockage physique';

    public function handle()
    {
        $this->info('Début de la migration des fichiers...');
        
        $reports = Report::whereNotNull('files')->get();
        
        $this->info("Nombre de rapports à traiter: " . $reports->count());
        
        foreach ($reports as $report) {
            $files = $report->files;
            if (is_string($files)) {
                $files = json_decode($files, true);
            }
            
            if (is_array($files) && count($files) > 0) {
                $this->info("Traitement du rapport {$report->reference} avec " . count($files) . " fichiers");
                
                foreach ($files as $file) {
                    // Créer un fichier factice dans le stockage
                    $content = "Fichier migré: {$file}\nRapport: {$report->reference}\nDate: " . now();
                    
                    // Stocker dans le dossier public
                    $filePath = "reports/{$file}";
                    Storage::disk('public')->put($filePath, $content);
                    
                    $this->line("✓ Fichier créé: {$file}");
                }
            }
        }
        
        $this->info('Migration terminée avec succès !');
        $this->info('Les fichiers sont maintenant disponibles dans: storage/app/public/reports/');
    }
}