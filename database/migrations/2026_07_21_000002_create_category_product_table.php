<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('id_product')->constrained('products')->cascadeOnDelete();
            $table->foreignId('id_category')->constrained('categories');

            $table->primary(['id_product', 'id_category']);
            $table->index('id_category');
        });

        // Migra la relacion 1-N existente a la pivote antes de soltar la columna.
        if (Schema::hasColumn('products', 'id_category')) {
            DB::statement('INSERT INTO category_product (id_product, id_category) SELECT id, id_category FROM products');

            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['id_category']);
                $table->dropColumn('id_category');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('id_category')->nullable()->constrained('categories');
        });

        // Se conserva solo la primera categoria de cada producto.
        DB::statement('UPDATE products p SET id_category = (SELECT MIN(id_category) FROM category_product cp WHERE cp.id_product = p.id)');

        Schema::dropIfExists('category_product');
    }
};
