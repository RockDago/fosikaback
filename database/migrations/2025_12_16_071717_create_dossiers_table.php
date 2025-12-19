// database/migrations/2024_01_01_create_dossiers_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('dossiers', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('type')->default('signalement');
            $table->boolean('statut')->default(true);
            $table->enum('priorite', ['basse', 'normale', 'elevee', 'urgente'])->default('normale');
            $table->foreignId('citoyen_id')->constrained('users');
            $table->foreignId('agent_id')->nullable()->constrained('users');
            $table->foreignId('investigateur_id')->nullable()->constrained('users');
            $table->timestamp('date_creation')->useCurrent();
            $table->timestamp('date_cloture')->nullable();
            $table->string('lieu')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['reference', 'statut']);
            $table->index(['citoyen_id', 'statut']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('dossiers');
    }
};