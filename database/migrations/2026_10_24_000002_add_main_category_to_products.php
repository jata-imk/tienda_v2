<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categoria principal: da el prefijo de la clave del producto. Siempre es
     * tambien una de sus categorias de la pivote.
     *
     * Backfill: entre las categorias del producto, la cuya clave es el prefijo
     * de su clave (`GUA-NIN-003` -> `GUA`); si ninguna coincide, la primera de
     * la pivote. No agrega categorias al producto.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('id_main_category')->nullable()->after('id')->constrained('categories');
        });

        $categoryIdsByKey = DB::table('categories')->pluck('id', 'key')->mapWithKeys(
            fn($id, $key) => [strtoupper($key) => (int) $id],
        );

        foreach (DB::table('products')->get(['id', 'key']) as $product) {
            $ownCategoryIds = DB::table('category_product')
                ->where('id_product', $product->id)
                ->orderBy('id_category')
                ->pluck('id_category')
                ->map(fn($id) => (int) $id)
                ->all();

            // Solo una categoria a la que el producto ya pertenece: una categoria
            // ajena que coincida con el prefijo (clave generada por la migracion
            // anterior) no lo mete en ella.
            $prefixCategoryId = $categoryIdsByKey[strtoupper(explode('-', $product->key)[0])] ?? null;
            $categoryId       = in_array($prefixCategoryId, $ownCategoryIds, true)
                ? $prefixCategoryId
                : ($ownCategoryIds[0] ?? null);

            if ($categoryId !== null) {
                DB::table('products')->where('id', $product->id)->update(['id_main_category' => $categoryId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['id_main_category']);
            $table->dropColumn('id_main_category');
        });
    }
};
