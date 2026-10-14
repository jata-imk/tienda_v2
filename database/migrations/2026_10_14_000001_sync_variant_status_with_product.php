<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Realinea los datos que quedaron desincronizados antes de que la baja de un
 * producto cascadeara a sus variantes: las variantes activas de productos
 * inactivos pasan a inactivas.
 *
 * Solo baja; no reactiva. Una variante inactiva en un producto activo es un
 * estado legitimo (talla descontinuada) y revivirla destruiria informacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('product_variants')
            ->where('status', 'active')
            ->whereIn('id_product', function ($query) {
                $query->select('id')->from('products')->where('status', 'inactive');
            })
            ->update(['status' => 'inactive']);
    }

    public function down(): void
    {
        // Irreversible: no queda registro de cuales variantes estaban activas.
    }
};
