// database/migrations/2024_01_03_create_chat_participants_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('chat_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['citoyen', 'agent', 'investigateur', 'support', 'admin'])->default('citoyen');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();
            
            $table->unique(['chat_id', 'user_id']);
            $table->index(['user_id', 'chat_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_participants');
    }
};