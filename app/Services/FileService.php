<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class FileService
{
    /**
     * Trouve ou crée un fichier
     */
    public function findOrCreateFile($filename, $reference = null)
    {
        // Nettoyer le nom du fichier
        $filename = basename($filename);
        $reference = $reference ?? 'REF-' . time();
        
        // D'abord chercher le fichier existant
        $filePath = $this->findExistingFile($filename);
        if ($filePath) {
            return $filePath;
        }
        
        // Si non trouvé, créer un fichier de démonstration
        return $this->createDemoFile($filename, $reference);
    }
    
    /**
     * Cherche un fichier existant
     */
    public function findExistingFile($filename)
    {
        $possiblePaths = [
            public_path('uploads/reports/' . $filename),
            public_path('storage/reports/' . $filename),
            storage_path('app/public/reports/' . $filename),
            storage_path('app/public/uploads/' . $filename),
            public_path('uploads/' . $filename),
            storage_path('app/public/' . $filename),
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path) && filesize($path) > 0) {
                Log::info("Fichier trouvé: {$path}");
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * Crée un fichier de démonstration réaliste
     */
    public function createDemoFile($filename, $reference)
    {
        $uploadDir = public_path('uploads/reports/');
        
        // S'assurer que le dossier existe
        if (!File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }
        
        $filePath = $uploadDir . $filename;
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        Log::info("Création fichier démo: {$filename} pour référence: {$reference}");
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
            case 'png':
                $this->createDemoImage($filePath, $filename, $reference, $extension);
                break;
                
            case 'pdf':
                $this->createDemoPdf($filePath, $filename, $reference);
                break;
                
            case 'doc':
            case 'docx':
                $this->createDemoDocument($filePath, $filename, $reference);
                break;
                
            default:
                $this->createDemoText($filePath, $filename, $reference);
                break;
        }
        
        return $filePath;
    }
    
    /**
     * Crée une image de démonstration avec GD
     */
    private function createDemoImage($filePath, $filename, $reference, $extension)
    {
        // Vérifier si GD est disponible
        if (!extension_loaded('gd')) {
            Log::error("GD extension non disponible, création fichier texte à la place");
            $this->createDemoText($filePath, $filename, $reference);
            return;
        }
        
        try {
            $width = 800;
            $height = 600;
            
            // Créer l'image
            $image = imagecreate($width, $height);
            
            // Couleurs - Vert FOSIKA (#4a7026)
            $backgroundColor = imagecolorallocate($image, 74, 112, 38);
            $textColor = imagecolorallocate($image, 255, 255, 255);
            $borderColor = imagecolorallocate($image, 180, 205, 123); // Vert clair
            
            // Remplir le fond
            imagefill($image, 0, 0, $backgroundColor);
            
            // Ajouter une bordure décorative
            imagerectangle($image, 10, 10, $width-11, $height-11, $borderColor);
            imagerectangle($image, 12, 12, $width-13, $height-13, $borderColor);
            
            // Texte à afficher
            $lines = [
                ["FOSIKA - PLATEFORME DE SIGNALEMENT", 5, 80],
                ["Ministère de l'Enseignement Supérieur", 4, 130],
                ["et de la Recherche Scientifique", 4, 160],
                ["", 0, 200], // Espace
                ["Référence: {$reference}", 4, 240],
                ["Fichier: {$filename}", 3, 270],
                ["", 0, 300], // Espace
                ["Ceci est un fichier de démonstration", 3, 340],
                ["Système de gestion des signalements", 2, 370],
                ["Date: " . date('d/m/Y H:i:s'), 2, 400],
            ];
            
            foreach ($lines as $line) {
                list($text, $size, $y) = $line;
                if ($text) {
                    $textWidth = strlen($text) * 8; // Estimation largeur
                    $x = ($width - $textWidth) / 2; // Centrer
                    imagestring($image, $size, $x, $y, $text, $textColor);
                }
            }
            
            // Ajouter un "logo" FOSIKA
            $logoX = 50;
            $logoY = 450;
            $logoSize = 80;
            
            // Cercle pour le logo
            imagefilledellipse($image, $logoX + $logoSize/2, $logoY + $logoSize/2, $logoSize, $logoSize, $textColor);
            imagefilledellipse($image, $logoX + $logoSize/2, $logoY + $logoSize/2, $logoSize-15, $logoSize-15, $backgroundColor);
            imagestring($image, 2, $logoX + 12, $logoY + 35, "FOSIKA", $textColor);
            
            // Sauvegarder selon l'extension
            if ($extension === 'png') {
                imagepng($image, $filePath);
            } else {
                imagejpeg($image, $filePath, 85); // Qualité 85%
            }
            
            imagedestroy($image);
            
            Log::info("✅ Image démo créée avec GD: {$filePath}");
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur création image GD: " . $e->getMessage());
            // Fallback: créer un fichier texte
            $this->createDemoText($filePath, $filename, $reference);
        }
    }
    
    /**
     * Crée un PDF de démonstration
     */
    private function createDemoPdf($filePath, $filename, $reference)
    {
        $content = "%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>
endobj
4 0 obj
<< /Length 600 >>
stream
BT
/F1 18 Tf
100 750 Td
(FOSIKA - Plateforme de Signalement) Tj
0 -30 Td
/F1 14 Tf
(Ministere de l'Enseignement Superieur) Tj
0 -25 Td
(et de la Recherche Scientifique) Tj
0 -40 Td
/F1 12 Tf
(Reference: {$reference}) Tj
0 -20 Td
(Fichier: {$filename}) Tj
0 -20 Td
(Date: " . date('d/m/Y H:i:s') . ") Tj
0 -40 Td
(Ceci est un fichier de demonstration) Tj
0 -20 Td
(Systeme de gestion des signalements) Tj
ET
endstream
endobj
5 0 obj
<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>
endobj
xref
0 6
0000000000 65535 f 
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
0000000239 00000 n 
0000000900 00000 n 
trailer
<< /Size 6 /Root 1 0 R >>
startxref
1000
%%EOF";
        
        file_put_contents($filePath, $content);
        Log::info("✅ PDF démo créé: {$filePath}");
    }
    
    /**
     * Crée un document de démonstration
     */
    private function createDemoDocument($filePath, $filename, $reference)
    {
        $content = "FOSIKA - PLATEFORME DE SIGNALEMENT\n";
        $content .= "===================================\n\n";
        $content .= "Ministère de l'Enseignement Supérieur\n";
        $content .= "et de la Recherche Scientifique\n\n";
        $content .= "RÉFÉRENCE: {$reference}\n";
        $content .= "FICHIER: {$filename}\n";
        $content .= "DATE: " . date('d/m/Y H:i:s') . "\n\n";
        $content .= "DESCRIPTION:\n";
        $content .= "Ceci est un fichier de démonstration généré automatiquement\n";
        $content .= "par le système FOSIKA. Dans un environnement de production,\n";
        $content .= "ce fichier contiendrait les documents réels soumis avec\n";
        $content .= "le signalement.\n\n";
        $content .= "===================================\n";
        $content .= "SYSTÈME FOSIKA - Gestion des signalements\n";
        $content .= "===================================\n";
        
        file_put_contents($filePath, $content);
        Log::info("✅ Document démo créé: {$filePath}");
    }
    
    /**
     * Crée un fichier texte de démonstration
     */
    private function createDemoText($filePath, $filename, $reference)
    {
        $content = "FOSIKA - PLATEFORME DE SIGNALEMENT\n";
        $content .= "===================================\n\n";
        $content .= "Ministère de l'Enseignement Supérieur\n";
        $content .= "et de la Recherche Scientifique\n\n";
        $content .= "RÉFÉRENCE DU SIGNALEMENT: {$reference}\n";
        $content .= "NOM DU FICHIER: {$filename}\n";
        $content .= "DATE DE GÉNÉRATION: " . date('d/m/Y H:i:s') . "\n\n";
        $content .= "INFORMATIONS:\n";
        $content .= "Ce fichier a été généré automatiquement par le système FOSIKA.\n";
        $content .= "Il sert de démonstration pour l'interface de suivi des dossiers.\n\n";
        $content .= "FONCTIONNALITÉS:\n";
        $content .= "- Visualisation des pièces jointes\n";
        $content .= "- Téléchargement des documents\n";
        $content .= "- Suivi en temps réel des signalements\n\n";
        $content .= "===================================\n";
        $content .= "SYSTÈME FOSIKA\n";
        $content .= "Lutte contre les fraudes académiques\n";
        $content .= "===================================\n";
        
        file_put_contents($filePath, $content);
        Log::info("✅ Fichier texte démo créé: {$filePath}");
    }
    
    /**
     * Vérifie si l'extension GD est disponible
     */
    public function isGdAvailable()
    {
        return extension_loaded('gd') && function_exists('imagecreate');
    }
    
    /**
     * Génère tous les fichiers manquants pour un rapport
     */
    public function generateAllMissingFiles($reference, $filenames)
    {
        $results = [
            'generated' => [],
            'existing' => [],
            'errors' => []
        ];

        foreach ($filenames as $filename) {
            $existingPath = $this->findExistingFile($filename);
            
            if ($existingPath) {
                $results['existing'][] = $filename;
            } else {
                try {
                    $filePath = $this->createDemoFile($filename, $reference);
                    if ($filePath && file_exists($filePath)) {
                        $results['generated'][] = $filename;
                        Log::info("✅ Fichier généré: {$filename} pour {$reference}");
                    } else {
                        $results['errors'][] = $filename;
                        Log::error("❌ Erreur génération: {$filename} pour {$reference}");
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = $filename;
                    Log::error("❌ Exception génération: {$filename} - " . $e->getMessage());
                }
            }
        }

        return $results;
    }
}