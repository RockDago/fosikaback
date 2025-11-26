<?php
// database/migrations/2024_01_01_create_audit_systemes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_systemes', function (Blueprint $table) {
            $table->id();
            $table->timestamp('timestamp')->useCurrent();
            $table->string('utilisateur'); // Email de l'admin
            $table->string('action'); // Consultation, Modification, Export, etc.
            $table->string('entite'); // Signalements, Journal, etc.
            $table->string('statut'); // Succès, Refusé
            $table->string('ip');
            $table->text('details');
            $table->string('reference_dossier')->nullable(); // Si applicable
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Index pour améliorer les performances
            $table->index('timestamp');
            $table->index('utilisateur');
            $table->index('action');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_systemes');
    }
};
