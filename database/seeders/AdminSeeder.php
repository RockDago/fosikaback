<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@fosika.mg'],
            [
                'first_name'      => 'Admin',
                'last_name'       => 'FOSIKA',
                'username'        => 'admin',
                'password'        => 'admin123',
                'role'            => 'Admin',
                'statut'          => true,
                'phone'           => null,
                'telephone'       => null,
                'adresse'         => 'Siège FOSIKA',
                'departement'     => 'Administration',
                'responsabilites' => [],
                'specialisations' => [],
            ]
        );
    }
}
