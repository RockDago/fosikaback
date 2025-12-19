<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('trackings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('status')->default('en_cours');
            $table->text('notes')->nullable();
            $table->timestamp('last_update')->useCurrent();
            $table->timestamps();

            $table->index('reference');
            $table->index('status');
            $table->index('last_update');
        });
    }

    public function down()
    {
        Schema::dropIfExists('trackings');
    }
};