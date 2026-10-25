<?php

use App\Support\CategoryKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clave corta de categoria (prefijo de la clave de producto: `CAM-001`) y de
     * grupo de tallas (segmento opcional: `GUA-NIN-001`).
     *
     * El backfill respeta los prefijos que ya se capturaron a mano: si los
     * productos `VEST-*` pertenecen a "Vestidos", la categoria queda con `VEST`
     * aunque la regla de 3 letras daria `VES`.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('key', 10)->nullable()->unique()->after('id');
        });

        Schema::table('size_groups', function (Blueprint $table) {
            $table->string('key', 10)->nullable()->unique()->after('id');
        });

        $this->uppercaseProductKeys();
        $this->assignKeysFromProductPrefixes();

        foreach (DB::table('categories')->whereNull('key')->orderBy('id')->get(['id', 'name']) as $category) {
            DB::table('categories')->where('id', $category->id)->update([
                'key' => CategoryKey::generate($category->name),
            ]);
        }

        foreach (DB::table('size_groups')->get(['id', 'name']) as $group) {
            if (implode('', CategoryKey::words($group->name)) === 'NINOS') {
                DB::table('size_groups')->where('id', $group->id)->update(['key' => 'NIN']);
            }
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->string('key', 10)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn('key');
        });

        Schema::table('size_groups', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn('key');
        });
    }

    /**
     * `FIl-004` -> `FIL-004`. La collation `_ci` ya los trataba como iguales en
     * el indice unico, asi que no hay choque posible.
     */
    private function uppercaseProductKeys(): void
    {
        foreach (DB::table('products')->get(['id', 'key']) as $product) {
            $upper = strtoupper($product->key);

            if ($upper !== $product->key) {
                DB::table('products')->where('id', $product->id)->update(['key' => $upper]);
            }
        }
    }

    /**
     * Por cada prefijo de clave (`CAM`, `VEST`) busca, entre las categorias de
     * esos productos, la que mas productos comparte y cuyo nombre empieza con
     * el prefijo (`CAMISA`, `VESTIDOS`).
     */
    private function assignKeysFromProductPrefixes(): void
    {
        $prefixes = DB::table('products')
            ->get(['id', 'key'])
            ->groupBy(fn($product) => explode('-', $product->key)[0]);

        foreach ($prefixes as $prefix => $products) {
            if (!preg_match('/^[A-Z]{2,10}$/', $prefix)) {
                continue;
            }

            $category = DB::table('category_product')
                ->join('categories', 'categories.id', '=', 'category_product.id_category')
                ->whereIn('category_product.id_product', $products->pluck('id'))
                ->whereNull('categories.key')
                ->groupBy('categories.id', 'categories.name')
                ->orderByRaw('COUNT(*) DESC')
                ->orderBy('categories.id')
                ->get(['categories.id', 'categories.name'])
                ->first(fn($row) => str_starts_with(implode('', CategoryKey::words($row->name)), $prefix));

            if ($category && !DB::table('categories')->where('key', $prefix)->exists()) {
                DB::table('categories')->where('id', $category->id)->update(['key' => $prefix]);
            }
        }
    }
};
