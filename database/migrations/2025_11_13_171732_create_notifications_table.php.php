<?php
// database/migrations/2024_01_01_create_notifications_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationsTable extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'signalement_urgent', 'doublon', 'activite_suspecte', 'faux_documents'
            $table->string('titre');
            $table->text('message');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['active', 'read', 'archived'])->default('active');
            $table->string('reference_dossier')->nullable(); // Référence du signalement
            $table->json('metadata')->nullable(); // Données supplémentaires
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'created_at']);
            $table->index('reference_dossier');
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
}