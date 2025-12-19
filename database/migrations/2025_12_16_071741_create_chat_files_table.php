// database/migrations/2024_01_04_create_chat_files_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('chat_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->nullable()->constrained('messages')->onDelete('cascade');
            $table->foreignId('chat_id')->constrained()->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type');
            $table->bigInteger('file_size')->default(0);
            $table->string('mime_type')->nullable();
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index(['chat_id', 'created_at']);
            $table->index('message_id');
            $table->index('reference');
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_files');
    }
};