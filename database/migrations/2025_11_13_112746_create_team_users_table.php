<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('team_users', function (Blueprint $table) {
            $table->id();
            $table->string('nom_complet');
            $table->string('first_name')->nullable(); // AJOUTÉ
            $table->string('last_name')->nullable();  // AJOUTÉ
            $table->string('email')->unique();
            $table->string('telephone')->nullable();
            $table->text('adresse')->nullable();
            $table->string('departement');
            $table->string('username')->unique();
            $table->string('password');
            $table->enum('role', ['Agent', 'Investigateur', 'Admin'])->default('Agent'); // AJOUTÉ Admin
            $table->text('responsabilites')->nullable();
            $table->json('specialisations')->nullable();
            $table->boolean('statut')->default(true);
            $table->string('avatar')->nullable(); // AJOUTÉ pour les avatars
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_users');
    }
};