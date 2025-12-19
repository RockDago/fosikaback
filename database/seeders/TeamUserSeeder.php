<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class TeamUserSeeder extends Seeder
{
    public function run(): void
    {
        // --- AGENT ---
        User::updateOrCreate(
            ['email' => 'agent@fosika.mg'],
            [
                'first_name'      => 'Agent',
                'last_name'       => 'Test',
                'username'        => 'agent',
                'password'        => 'agent123',
                'role'            => 'Agent',
                'statut'          => true,
                'telephone'       => '+261 32 12 345 67',
                'adresse'         => 'Antananarivo',
                'departement'     => 'Enquêtes',
                'responsabilites' => ['Traitement des signalements initiaux'],
                'specialisations' => ['Diplômes', 'Expérience professionnelle'],
            ]
        );
    }
}
