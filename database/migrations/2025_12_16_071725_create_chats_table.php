<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique()->nullable();
            $table->foreignId('dossier_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->enum('type', ['support', 'agent', 'investigation', 'general'])->default('support');
            $table->enum('status', ['active', 'closed', 'archived'])->default('active');
            $table->timestamp('last_message_at')->nullable();
            $table->boolean('is_important')->default(false);

            // MODIFIÉ : nullable pour les chats publics (visiteurs)
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->foreignId('closed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['reference', 'status']);
            $table->index(['dossier_id', 'type']);
            $table->index(['created_by', 'is_important']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('chats');
    }
};
