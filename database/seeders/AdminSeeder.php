<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run()
    {
        // Empêcher doublons si le seeder tourne plusieurs fois
        if (!Admin::where('email', 'admin@fosika.mg')->exists()) {

            Admin::create([
                'name'       => 'Administrateur FOSIKA',
                'email'      => 'admin@fosika.mg',
                'password'   => Hash::make('admin123'),
                'first_name' => 'Admin',
                'last_name'  => 'FOSIKA',
                'phone'      => '+261 34 00 000 00',
            ]);
        }
    }
}