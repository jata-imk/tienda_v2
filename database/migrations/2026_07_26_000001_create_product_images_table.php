<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_product')->constrained('products')->cascadeOnDelete();
            $table->foreignId('id_color')->constrained('colors');
            // Paths relativos del disco `public`; la URL se arma en el Resource.
            $table->string('path');
            $table->string('path_thumb');
            $table->timestamps();

            $table->index(['id_product', 'id_color']);
        });

        // MariaDB puede agregar DEFAULT CURRENT_TIMESTAMP ON UPDATE de forma
        // implicita en la primera columna TIMESTAMP de la tabla (ver migracion
        // 2026_07_21_000001_make_updated_at_nullable). Se normaliza igual aqui
        // para que NullsUpdatedAtOnCreate funcione como en el resto de modelos.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `product_images` MODIFY `updated_at` TIMESTAMP NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
