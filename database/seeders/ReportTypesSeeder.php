<?php
// database/seeders/ReportTypesSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportTypesSeeder extends Seeder
{
    public function run()
    {
        $reportTypes = [
            [
                'id' => 'hebdo',
                'name' => 'Rapport Hebdomadaire',
                'description' => 'Synthèse des activités de la semaine',
                'icon' => '📅',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 'mensuel',
                'name' => 'Rapport Mensuel',
                'description' => 'Bilan complet des activités mensuelles',
                'icon' => '🗓️',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 'categorie',
                'name' => 'Rapport par Catégorie',
                'description' => 'Analyse détaillée par signalement',
                'icon' => '📊',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 'final',
                'name' => 'Rapport Final d\'Opération',
                'description' => 'Synthèse globale de l\'opération',
                'icon' => '🏁',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        foreach ($reportTypes as $type) {
            DB::table('report_types')->updateOrInsert(
                ['id' => $type['id']],
                $type
            );
        }
    }
}