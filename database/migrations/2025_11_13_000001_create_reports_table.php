<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->enum('type', ['anonyme', 'identifie']);
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('category');
            $table->text('description');
            $table->json('files')->nullable();
            $table->string('ip_address');
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_vpn')->default(false);
            $table->boolean('vpn_blocked')->default(false);
            
            // ✅ AJOUT de 'classifier' dans l'enum status
            $table->enum('status', ['en_cours', 'finalise', 'doublon', 'refuse', 'classifier'])->default('en_cours');
            
            $table->json('workflow')->nullable();
            $table->boolean('accept_terms')->default(false);
            $table->boolean('accept_truth')->default(false);
            $table->timestamps();

            // Index pour améliorer les performances
            $table->index('reference');
            $table->index('status');
            $table->index('category');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
