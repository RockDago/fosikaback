<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'permissions'
    ];

    protected $casts = [
        'permissions' => 'array'
    ];

    public function users()
    {
        return $this->hasMany(TeamUser::class);
    }

    // Permissions prédéfinies
    public static function getDefaultPermissions()
    {
        return [
            'agent_suivi' => [
                'voir_nouveaux_dossiers',
                'modifier_infos_visiteur',
                'classer_dossiers',
                'ajouter_pieces_internes',
                'maj_statuts_1_4',
                'assigner_dossier_cac_dagi',
                'ajouter_commentaires_internes',
                'demander_infos_supplementaires'
            ],
            'investigateur' => [
                'voir_dossiers_statut_4',
                'ajouter_rapport_investigation',
                'ajouter_corriger_preuves',
                'modifier_classification',
                'maj_statuts_5_8',
                'reassigner_dossier_autre_agent'
            ],
            'admin' => [
                'gerer_utilisateurs_roles',
                'modifier_tous_dossiers',
                'reassigner_quelconque_dossier',
                'modifier_statuts_manuellement',
                'acces_tous_logs',
                'exporter_rapports_pdf_excel',
                'configurer_types_fraude_statuts'
            ]
        ];
    }
}