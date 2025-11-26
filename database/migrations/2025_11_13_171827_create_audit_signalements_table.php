<?php
// database/migrations/2024_01_01_create_audit_signalements_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditSignalementsTable extends Migration
{
    public function up()
    {
        Schema::create('audit_signalements', function (Blueprint $table) {
            $table->id();
            $table->string('reference_dossier'); // Référence du dossier
            $table->timestamp('timestamp');
            $table->enum('type_anonymat', ['Anonyme', 'Non-Anonyme']);
            $table->string('adresse_ip');
            $table->string('geolocalisation')->nullable();
            $table->string('identite'); // "Anonyme" ou nom complet
            $table->string('telephone');
            $table->string('email');
            $table->string('region_province');
            $table->string('type_fraude');
            $table->string('statut')->default('Reçu');
            $table->json('details_supplementaires')->nullable();
            $table->timestamps();
            
            $table->index('reference_dossier');
            $table->index('timestamp');
            $table->index('type_anonymat');
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_signalements');
    }
}