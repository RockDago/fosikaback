<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Pour PostgreSQL, on doit modifier le type enum manuellement
        DB::statement("ALTER TABLE reports 
            DROP CONSTRAINT IF EXISTS reports_status_check,
            ADD CONSTRAINT reports_status_check 
            CHECK (status IN (
                'en_cours', 
                'finalise', 
                'doublon', 
                'refuse', 
                'classifier',
                'investigation',
                'transmis_autorite'
            ))");
        
        // Ajouter les autres champs manquants si nécessaire
        Schema::table('reports', function (Blueprint $table) {
            if (!Schema::hasColumn('reports', 'has_proof')) {
                $table->boolean('has_proof')->default(false)->after('accept_truth');
            }
            
            if (!Schema::hasColumn('reports', 'city')) {
                $table->string('city')->nullable()->after('has_proof');
            }
            
            if (!Schema::hasColumn('reports', 'province')) {
                $table->string('province')->nullable()->after('city');
            }
            
            if (!Schema::hasColumn('reports', 'region')) {
                $table->string('region')->nullable()->after('province');
            }
            
            if (!Schema::hasColumn('reports', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->after('region')
                    ->constrained('team_users')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        // Revenir aux anciens statuts
        DB::statement("ALTER TABLE reports 
            DROP CONSTRAINT IF EXISTS reports_status_check,
            ADD CONSTRAINT reports_status_check 
            CHECK (status IN (
                'en_cours', 
                'finalise', 
                'doublon', 
                'refuse', 
                'classifier'
            ))");
        
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['has_proof', 'city', 'province', 'region', 'assigned_to']);
        });
    }
};