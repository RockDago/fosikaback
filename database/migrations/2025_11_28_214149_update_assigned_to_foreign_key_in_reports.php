<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // Supprimer l'ancienne clé étrangère si elle existe
            try {
                $table->dropForeign(['assigned_to']);
            } catch (\Exception $e) {
                // Ignorer si elle n'existe pas
            }
            
            // Ajouter la nouvelle clé étrangère vers team_users
            $table->foreign('assigned_to')
                  ->references('id')
                  ->on('team_users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
        });
    }
};
