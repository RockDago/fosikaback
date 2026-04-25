<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            UniversiteSeeder::class,
            AdminSeeder::class,
            TeamUserSeeder::class,
        ]);
    }
}
