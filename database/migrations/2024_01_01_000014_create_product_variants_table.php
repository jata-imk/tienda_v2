<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_product')->constrained('products');
            $table->foreignId('id_size')->constrained('sizes');
            $table->foreignId('id_color')->constrained('colors');
            $table->string('sku')->unique();
            $table->string('code_bar')->nullable();
            $table->decimal('stock', 10, 3)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['id_product', 'id_size', 'id_color']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
