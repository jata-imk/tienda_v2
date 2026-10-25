<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

/**
 * Backfill de las migraciones de claves sobre datos con la forma del respaldo
 * de produccion: prefijos capturados a mano (`VEST` de 4 letras), subseries
 * (`GUA-NIN`), typos de mayusculas (`FIl-`) y categorias-atributo que chocan
 * en las 3 letras (`Manga corta` / `Manga larga`).
 */
class ProductKeyBackfillTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sin transaccion: dentro de una, SQLite ignora `foreign_keys = OFF`.
     *
     * @var array<int, string>
     */
    protected $connectionsToTransact = [];

    private const KEYS_MIGRATION = '2026_10_24_000001_add_key_to_categories_and_size_groups.php';
    private const MAIN_MIGRATION = '2026_10_24_000002_add_main_category_to_products.php';

    /**
     * En proceso aparte: tras otras pruebas, la reconstruccion de tablas de
     * SQLite al revertir la migracion no ve las columnas de `products`.
     */
    #[RunInSeparateProcess]
    public function test_backfill_assigns_category_keys_and_main_categories(): void
    {
        $keys = require database_path('migrations/' . self::KEYS_MIGRATION);
        $main = require database_path('migrations/' . self::MAIN_MIGRATION);

        // SQLite reconstruye la tabla al quitar columnas y las FK de la pivote
        // impedirian el DROP intermedio.
        Schema::withoutForeignKeyConstraints(function () use ($keys, $main) {
            $main->down();
            $keys->down();
        });

        $categories = [
            1 => 'Camisa', 2 => 'Guayabera', 3 => 'Vestidos', 4 => 'Manga corta',
            5 => 'Manga larga', 6 => '100% Lino', 7 => 'Filipina', 8 => 'Accesorios',
        ];

        foreach ($categories as $id => $name) {
            DB::table('categories')->insert(['id' => $id, 'name' => $name, 'status' => 'active']);
        }

        DB::table('size_groups')->insert([
            ['id' => 1, 'name' => 'Adultos', 'status' => 'active'],
            ['id' => 2, 'name' => 'Niños', 'status' => 'active'],
        ]);

        $products = [
            1 => ['CAM-001', [1, 4]],
            2 => ['CAM-002', [1, 5, 6]],
            3 => ['GUA-NIN-003', [4, 2]],
            4 => ['VEST-006', [6, 3]],
            5 => ['FIl-004', [7, 5]],
            6 => ['SERV-001', [5]],
            // Su prefijo coincide con la clave generada de "Accesorios" (`ACC`),
            // pero no pertenece a ella: no debe quedar dentro.
            7 => ['ACC-001', [5]],
        ];

        foreach ($products as $id => [$key, $categoryIds]) {
            DB::table('products')->insert(['id' => $id, 'key' => $key, 'name' => $key, 'price' => 1, 'cost' => 1]);

            foreach ($categoryIds as $categoryId) {
                DB::table('category_product')->insert(['id_product' => $id, 'id_category' => $categoryId]);
            }
        }

        Schema::withoutForeignKeyConstraints(function () use ($keys, $main) {
            $keys->up();
            $main->up();
        });

        $this->assertSame(
            [1 => 'CAM', 2 => 'GUA', 3 => 'VEST', 4 => 'MAN', 5 => 'MLA', 6 => 'LIN', 7 => 'FIL', 8 => 'ACC'],
            DB::table('categories')->orderBy('id')->pluck('key', 'id')->all(),
        );

        $this->assertSame([1 => null, 2 => 'NIN'], DB::table('size_groups')->orderBy('id')->pluck('key', 'id')->all());

        $this->assertSame('FIL-004', DB::table('products')->where('id', 5)->value('key'));

        $this->assertSame(
            [1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 7, 6 => 5, 7 => 5],
            DB::table('products')->orderBy('id')->pluck('id_main_category', 'id')->map(fn($v) => (int) $v)->all(),
        );

        $this->assertSame([5], DB::table('category_product')->where('id_product', 7)->pluck('id_category')->map(fn($v) => (int) $v)->all());
    }
}
