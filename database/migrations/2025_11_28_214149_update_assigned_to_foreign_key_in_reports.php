<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // On essaie de drop l'ancienne FK sans casser
            try {
                $table->dropForeign(['assigned_to']);
            } catch (\Throwable $e) {
                // ignorer si elle n'existe pas
            }
        });

        // Recréer proprement la FK vers users
        Schema::table('reports', function (Blueprint $table) {
            $table->foreign('assigned_to')
                ->references('id')
                ->on('users')      // important: plus team_users
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reports DROP CONSTRAINT IF EXISTS reports_assigned_to_foreign;');
        DB::statement('ALTER TABLE reports DROP CONSTRAINT IF EXISTS reports_assigned_to_foreign_key;');
    }

};
