<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categorias');
            $table->enum('status', ['activo', 'baja'])->default('activo');
            $table->string('clave')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('codebar')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2);
            $table->boolean('stock_control')->default(false);
            $table->decimal('stock', 10, 3)->default(0);
            $table->decimal('discount', 5, 2)->default(0);
            $table->foreignId('type_iva_id')->constrained('tipos_iva');
            $table->decimal('rate_iva', 5, 2)->nullable();
            $table->decimal('quota_iva', 10, 2)->nullable();
            $table->decimal('isr', 5, 2)->default(0);
            $table->decimal('imp_esp', 5, 2)->default(0);
            $table->timestamp('date_creation')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario');
    }
};
