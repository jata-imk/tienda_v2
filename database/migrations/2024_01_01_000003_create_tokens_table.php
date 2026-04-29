<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tokens', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['vigente', 'caducado'])->default('vigente');
            $table->text('token');
            $table->timestamp('date_start')->useCurrent();
            $table->timestamp('date_expiration')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokens');
    }
};
