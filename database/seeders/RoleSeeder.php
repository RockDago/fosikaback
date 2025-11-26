<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            [
                'name' => 'Agent de Suivi',
                'code' => 'agent_suivi',
                'description' => 'Équipe de Suivi (DAAQ/DRSE) - Gestion des dossiers initiaux',
                'permissions' => [
                    'voir_nouveaux_dossiers',
                    'modifier_infos_visiteur',
                    'classer_dossiers',
                    'ajouter_pieces_internes',
                    'maj_statuts_1_4',
                    'assigner_dossier_cac_dagi',
                    'ajouter_commentaires_internes',
                    'demander_infos_supplementaires'
                ]
            ],
            [
                'name' => 'Investigateur',
                'code' => 'investigateur',
                'description' => 'Équipe d\'Investigation (CAC/DAGI) - Investigation approfondie',
                'permissions' => [
                    'voir_dossiers_statut_4',
                    'ajouter_rapport_investigation',
                    'ajouter_corriger_preuves',
                    'modifier_classification',
                    'maj_statuts_5_8',
                    'reassigner_dossier_autre_agent'
                ]
            ],
            [
                'name' => 'Administrateur',
                'code' => 'admin',
                'description' => 'Accès complet au système',
                'permissions' => [
                    'gerer_utilisateurs_roles',
                    'modifier_tous_dossiers',
                    'reassigner_quelconque_dossier',
                    'modifier_statuts_manuellement',
                    'acces_tous_logs',
                    'exporter_rapports_pdf_excel',
                    'configurer_types_fraude_statuts'
                ]
            ]
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}