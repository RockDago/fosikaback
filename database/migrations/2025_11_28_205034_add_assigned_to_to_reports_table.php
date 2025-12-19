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
            if (!Schema::hasColumn('reports', 'assigned_to')) {
                $table->unsignedBigInteger('assigned_to')->nullable()->after('status');

                if (Schema::hasTable('users')) {
                    $table->foreign('assigned_to')
                        ->references('id')
                        ->on('users')
                        ->onDelete('set null');
                }
            }
        });
    }

    public function down(): void
    {
        // Supprimer FK et colonne de manière sûre
        DB::statement('ALTER TABLE reports DROP CONSTRAINT IF EXISTS reports_assigned_to_foreign;');
        DB::statement('ALTER TABLE reports DROP CONSTRAINT IF EXISTS reports_assigned_to_foreign_key;');

        if (Schema::hasColumn('reports', 'assigned_to')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->dropColumn('assigned_to');
            });
        }
    }
};
