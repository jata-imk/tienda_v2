<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesiones', function (Blueprint $table) {
            $table->id();
            $table->json('session')->nullable();
            $table->foreignId('user_id')->constrained('usuarios');
            $table->foreignId('token_id')->constrained('tokens');
            $table->enum('status', ['vigente', 'finalizado'])->default('vigente');
            $table->timestamp('date_start')->useCurrent();
            $table->timestamp('date_end')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones');
    }
};
