// database/migrations/2025_12_16_000003_create_enseignants_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enseignants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('universite_id')->constrained('universites')->onDelete('cascade');
            $table->foreignId('etablissement_id')->constrained('etablissements')->onDelete('cascade');
            $table->string('nom');
            $table->enum('sexe', ['M', 'F']);
            $table->string('im', 6);
            $table->date('date_naissance');
            $table->string('corps');
            $table->string('diplome');
            $table->string('specialite');
            $table->string('categorie');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enseignants');
    }
};
