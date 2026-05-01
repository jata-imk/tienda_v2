<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impuestos_config', function (Blueprint $table) {
            $table->id();
            $table->decimal('iva', 5, 2)->default(16.00);
            $table->decimal('isr', 5, 2)->default(10.00);
            $table->decimal('imp_esp', 5, 2)->default(0.00);
            $table->timestamp('date_creation')->useCurrent();
            $table->timestamp('date_update')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impuestos_config');
    }
};
