<?php
// database/migrations/2024_01_01_create_report_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Table des types de rapports
        if (!Schema::hasTable('report_types')) {
            Schema::create('report_types', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->text('description');
                $table->string('icon');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Table des rapports générés
        if (!Schema::hasTable('generated_reports')) {
            Schema::create('generated_reports', function (Blueprint $table) {
                $table->id();
                $table->string('report_type_id');
                $table->string('title');
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->json('summary_data')->nullable();
                $table->json('key_results')->nullable();
                $table->json('challenges')->nullable();
                $table->json('recommendations')->nullable();
                $table->string('file_path')->nullable();
                $table->foreignId('generated_by')->constrained('users');
                $table->boolean('is_sent_to_drse')->default(false);
                $table->boolean('is_sent_to_cac')->default(false);
                $table->boolean('is_sent_to_bianco')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('generated_reports');
        Schema::dropIfExists('report_types');
    }
};