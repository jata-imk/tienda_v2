<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Realinea los datos que quedaron desincronizados antes de que la baja de un
 * producto cascadeara a sus variantes: las variantes activas de productos
 * inactivos pasan a inactivas.
 *
 * Solo baja; no reactiva. No es una regla de negocio sino una limitacion de
 * este respaldo puntual: aqui no hay forma de saber por que una variante quedo
 * inactiva, asi que reactivar a ciegas las variantes de productos activos
 * perderia las que se descontinuaron a proposito. En runtime si se reactiva en
 * cascada (ProductService::update), donde el cambio de status del producto es
 * una accion explicita del usuario.
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
