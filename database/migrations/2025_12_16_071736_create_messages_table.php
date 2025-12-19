<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained()->onDelete('cascade');

            // MODIFIÉ : nullable pour les messages de visiteurs
            $table->foreignId('sender_id')->nullable()->constrained('users')->onDelete('set null');

            // AJOUTÉ : Informations des visiteurs non authentifiés
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();

            $table->text('content')->nullable();
            $table->enum('type', ['text', 'file', 'image'])->default('text');
            $table->string('reference')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('file_type')->nullable();

            // AJOUTÉ : Contrôle de visibilité publique
            $table->boolean('is_public')->default(true);

            // ✅ AJOUTÉ : Statut du message (comme WhatsApp)
            $table->enum('status', ['sent', 'delivered', 'read'])->default('sent');

            // ✅ AJOUTÉ : Timestamps pour le suivi de statut
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['chat_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
            $table->index('reference');
            $table->index('status'); // ✅ Index pour les requêtes de statut
        });
    }

    public function down()
    {
        Schema::dropIfExists('messages');
    }
};
