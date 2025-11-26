<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TeamUser;
use Illuminate\Support\Facades\Hash;

class TeamUserSeeder extends Seeder
{
    public function run()
    {
        TeamUser::create([
            'nom_complet' => 'Agent Test',
            'email' => 'agent@fosika.mg',
            'telephone' => '+261 32 12 345 67',
            'adresse' => 'Antananarivo',
            'departement' => 'Enquêtes',
            'username' => 'agent',
            'password' => Hash::make('agent123'),
            'role' => 'Agent',
            'responsabilites' => 'Traitement des signalements initiaux',
            'specialisations' => ['Diplômes', 'Expérience professionnelle'],
            'statut' => true,
        ]);

        TeamUser::create([
            'nom_complet' => 'Investigateur Test',
            'email' => 'investigateur@fosika.mg',
            'telephone' => '+261 33 12 345 67',
            'adresse' => 'Antananarivo',
            'departement' => 'Investigations',
            'username' => 'investigateur',
            'password' => Hash::make('invest123'),
            'role' => 'Investigateur',
            'responsabilites' => 'Enquêtes approfondies',
            'specialisations' => ['Corruption', 'Fraude'],
            'statut' => true,
        ]);
    }
}