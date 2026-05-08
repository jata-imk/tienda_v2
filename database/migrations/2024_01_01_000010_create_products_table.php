<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_category')->constrained('categories');
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('code_bar')->nullable();
            $table->string('size')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2);
            $table->boolean('stock_control')->default(false);
            $table->decimal('stock', 10, 3)->default(0);
            $table->decimal('discount', 5, 2)->default(0);
            $table->tinyInteger('type_iva')->default(1);
            $table->decimal('rate_iva', 5, 2)->nullable();
            $table->decimal('quota_iva', 10, 2)->nullable();
            $table->decimal('isr', 5, 2)->default(0);
            $table->decimal('imp_esp', 5, 2)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
