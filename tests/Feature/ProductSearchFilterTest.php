<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SizeGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProductSearchFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /** Crea un producto con defaults válidos y overrides puntuales. */
    private function makeProduct(array $overrides = [], array $categoryIds = []): Product
    {
        $product = Product::create(array_merge([
            'id_size_group' => null,
            'key'           => 'PLAIN-' . uniqid(),
            'name'          => 'Producto neutro',
            'description'   => 'Descripcion neutra',
            'code_bar'      => null,
            'price'         => 100.00,
            'cost'          => 50.00,
            'stock_control' => false,
            'discount'      => 0.00,
            'type_iva'      => 1,
            'rate_iva'      => 16.00,
            'quota_iva'     => null,
            'isr'           => 0.00,
            'imp_esp'       => 0.00,
            'status'        => 'active',
        ], $overrides));

        if ($categoryIds !== []) {
            $product->categories()->sync($categoryIds);
        }

        return $product;
    }

    private function query(array $w): TestResponse
    {
        return $this->withoutMiddleware()->postJson('/api/products/query', [
            'w'          => $w,
            'totalCount' => true,
        ]);
    }

    private function search(string $term, array $extra = []): TestResponse
    {
        return $this->query(array_merge($extra, [
            ['f' => 'search', 'ao' => 'contains', 'v' => $term, 'lo' => '&&'],
        ]));
    }

    private function keys(TestResponse $response): array
    {
        return collect($response->json('data.items'))->pluck('key')->all();
    }

    /** Payload completo (`p`, `o`, `w`, `totalCount`), tal cual lo manda el grid. */
    private function queryFull(array $body): TestResponse
    {
        return $this->withoutMiddleware()->postJson('/api/products/query', $body);
    }

    private function makeCategory(string $name): Category
    {
        return Category::create(['name' => $name, 'description' => 'x', 'status' => 'active']);
    }

    public function test_search_matches_by_name(): void
    {
        $this->makeProduct(['key' => 'PN-1', 'name' => 'Gorra ZUNIQNAME edicion']);

        $keys = $this->keys($this->search('ZUNIQNAME'));

        $this->assertSame(['PN-1'], $keys);
    }

    public function test_search_matches_by_key(): void
    {
        $this->makeProduct(['key' => 'ZUNIQKEY-9', 'name' => 'nombre plano']);

        $keys = $this->keys($this->search('ZUNIQKEY'));

        $this->assertSame(['ZUNIQKEY-9'], $keys);
    }

    public function test_search_matches_by_description(): void
    {
        $this->makeProduct(['key' => 'PD-1', 'description' => 'trae ZUNIQDESC adentro']);

        $keys = $this->keys($this->search('ZUNIQDESC'));

        $this->assertSame(['PD-1'], $keys);
    }

    public function test_search_matches_by_code_bar(): void
    {
        $this->makeProduct(['key' => 'PB-1', 'code_bar' => 'ZUNIQBAR7']);

        $keys = $this->keys($this->search('ZUNIQBAR7'));

        $this->assertSame(['PB-1'], $keys);
    }

    public function test_search_matches_by_category_name(): void
    {
        $category = Category::create([
            'name'        => 'ZCATNAME especial',
            'description' => 'x',
            'status'      => 'active',
        ]);
        // Columnas propias planas: sólo debe hacer match por la categoría.
        $this->makeProduct(['key' => 'PC-1', 'name' => 'nombre plano', 'description' => 'desc plana'], [$category->id]);

        $keys = $this->keys($this->search('ZCATNAME'));

        $this->assertSame(['PC-1'], $keys);
    }

    public function test_search_matches_by_size_group_name(): void
    {
        $group = SizeGroup::create([
            'name'        => 'ZSGNAME grupo',
            'description' => 'x',
            'status'      => 'active',
        ]);
        $this->makeProduct(['key' => 'PS-1', 'name' => 'nombre plano', 'description' => 'desc plana', 'id_size_group' => $group->id]);

        $keys = $this->keys($this->search('ZSGNAME'));

        $this->assertSame(['PS-1'], $keys);
    }

    public function test_search_combined_with_status_active_excludes_inactive_matches(): void
    {
        // Producto inactivo cuyo nombre matchea: NO debe colarse pese al OR interno.
        $this->makeProduct(['key' => 'INACT-1', 'name' => 'ZLEAK inactivo', 'status' => 'inactive']);
        $this->makeProduct(['key' => 'ACT-1', 'name' => 'ZLEAK activo', 'status' => 'active']);

        $response = $this->search('ZLEAK', [
            ['f' => 'status', 'ao' => '==', 'v' => 'active', 'lo' => '&&'],
        ]);

        $keys = $this->keys($response);

        $this->assertSame(['ACT-1'], $keys);
        $this->assertNotContains('INACT-1', $keys);
    }

    public function test_search_combined_with_a_category(): void
    {
        $this->makeProduct(['key' => 'CATX-1', 'name' => 'ZBOTH uno'], [1]);
        $this->makeProduct(['key' => 'CATX-2', 'name' => 'ZBOTH dos'], [2]);

        // search ZBOTH + categoría 1 -> sólo el de la categoría 1.
        $response = $this->search('ZBOTH', [
            ['f' => 'categories', 'ao' => '==', 'v' => 1, 'lo' => '&&'],
        ]);

        $this->assertSame(['CATX-1'], $this->keys($response));
    }

    public function test_empty_search_term_is_ignored(): void
    {
        $baseline = $this->query([])->json('data.totalCount');

        $this->assertSame($baseline, $this->search('')->json('data.totalCount'));
    }

    public function test_whitespace_search_term_is_ignored(): void
    {
        $baseline = $this->query([])->json('data.totalCount');

        $this->assertSame($baseline, $this->search('   ')->json('data.totalCount'));
    }

    public function test_search_escapes_like_wildcards(): void
    {
        // "100%" literal existe en la descripcion del seeder (CAM-001).
        $this->makeProduct(['key' => 'NOPCT-1', 'description' => 'promo 100 sin simbolo']);

        $keys = $this->keys($this->search('100%'));

        // Sin escapado, '%100%%' tambien casaria "100 sin simbolo".
        $this->assertContains('CAM-001', $keys);
        $this->assertNotContains('NOPCT-1', $keys);
    }

    public function test_search_escapes_underscore_wildcard(): void
    {
        $this->makeProduct(['key' => 'US-1', 'name' => 'code a_b end']);
        $this->makeProduct(['key' => 'US-2', 'name' => 'code aXb end']);

        $keys = $this->keys($this->search('a_b'));

        $this->assertSame(['US-1'], $keys);
    }

    public function test_search_does_not_error_on_virtual_column(): void
    {
        // Si `search` se tratara como columna SQL, esto lanzaria 500.
        $this->search('cualquier')->assertOk();
    }

    public function test_anyof_returns_products_in_any_of_the_categories(): void
    {
        $catOther = Category::create(['name' => 'ZOTHER', 'description' => 'x', 'status' => 'active']);

        $this->makeProduct(['key' => 'A1'], [1]);
        $this->makeProduct(['key' => 'B1'], [2]);
        $this->makeProduct(['key' => 'AB1'], [1, 2]);
        $this->makeProduct(['key' => 'O1'], [$catOther->id]);

        $keys = $this->keys($this->query([
            ['f' => 'categories', 'ao' => 'anyof', 'v' => [1, 2], 'lo' => '&&'],
        ]));

        $this->assertContains('A1', $keys);
        $this->assertContains('B1', $keys);
        $this->assertContains('AB1', $keys);
        $this->assertNotContains('O1', $keys);
    }

    public function test_anyof_lists_a_product_in_both_categories_only_once(): void
    {
        $this->makeProduct(['key' => 'AB1'], [1, 2]);

        $keys = $this->keys($this->query([
            ['f' => 'categories', 'ao' => 'anyof', 'v' => [1, 2], 'lo' => '&&'],
        ]));

        $this->assertSame(1, collect($keys)->filter(fn($k) => $k === 'AB1')->count());
    }

    public function test_anyof_respects_status_and_other_filters(): void
    {
        $this->makeProduct(['key' => 'INACTCAT', 'name' => 'ZAF match', 'status' => 'inactive'], [1]);
        $this->makeProduct(['key' => 'ACTCAT', 'name' => 'ZAF match', 'status' => 'active'], [1]);

        $keys = $this->keys($this->query([
            ['f' => 'status', 'ao' => '==', 'v' => 'active', 'lo' => '&&'],
            ['f' => 'name', 'ao' => 'contains', 'v' => 'ZAF', 'lo' => '&&'],
            ['f' => 'categories', 'ao' => 'anyof', 'v' => [1, 2], 'lo' => '&&'],
        ]));

        $this->assertSame(['ACTCAT'], $keys);
    }

    public function test_anyof_with_empty_array_is_ignored(): void
    {
        $baseline = $this->query([
            ['f' => 'status', 'ao' => '==', 'v' => 'active', 'lo' => '&&'],
        ])->json('data.totalCount');

        $response = $this->query([
            ['f' => 'status', 'ao' => '==', 'v' => 'active', 'lo' => '&&'],
            ['f' => 'categories', 'ao' => 'anyof', 'v' => [], 'lo' => '&&'],
        ]);

        $response->assertOk();
        $this->assertSame($baseline, $response->json('data.totalCount'));
    }

    /**
     * `in` es el operador del contrato; `anyof` es su alias de DevExtreme. Antes
     * `in` caia en `=` y Laravel colapsaba el array a su primer id.
     */
    public function test_in_operator_behaves_like_anyof(): void
    {
        $third = $this->makeCategory('ZTHIRD');
        $other = $this->makeCategory('ZOTHER');

        $this->makeProduct(['key' => 'IN-A'], [1]);
        $this->makeProduct(['key' => 'IN-B'], [2]);
        $this->makeProduct(['key' => 'IN-C'], [$third->id]);
        $this->makeProduct(['key' => 'IN-AB'], [1, 2]);
        $this->makeProduct(['key' => 'IN-O'], [$other->id]);

        $keys = $this->keys($this->query([
            ['f' => 'categories', 'ao' => 'in', 'v' => [1, 2, $third->id], 'lo' => '&&'],
        ]));

        $this->assertContains('IN-A', $keys);
        $this->assertContains('IN-B', $keys);
        $this->assertContains('IN-C', $keys);
        $this->assertContains('IN-AB', $keys);
        $this->assertNotContains('IN-O', $keys);
        // El producto de dos categorias seleccionadas no se duplica.
        $this->assertSame(1, collect($keys)->filter(fn($k) => $k === 'IN-AB')->count());
    }

    /** Payload literal del reporte: paginado + orden + status + `categories in`. */
    public function test_partner_payload_with_three_categories(): void
    {
        $third = $this->makeCategory('ZTHIRD');

        $this->makeProduct(['key' => 'PP-A', 'name' => 'AAA uno'], [1]);
        $this->makeProduct(['key' => 'PP-B', 'name' => 'BBB dos'], [2]);
        $this->makeProduct(['key' => 'PP-C', 'name' => 'CCC tres'], [$third->id]);

        $response = $this->queryFull([
            'p' => ['page' => 0, 'per_page' => 10],
            'f' => [],
            'o' => ['column' => 'name', 'direction' => 'asc'],
            'w' => [
                ['f' => 'status', 'ao' => '==', 'v' => 'active', 'lo' => '&&'],
                ['f' => 'categories', 'ao' => 'in', 'v' => [1, 2, $third->id], 'lo' => '&&'],
            ],
            'totalCount' => true,
        ]);

        $response->assertOk();

        $keys = $this->keys($response);

        $this->assertContains('PP-A', $keys);
        $this->assertContains('PP-B', $keys);
        $this->assertContains('PP-C', $keys);
        // Todo cabe en la primera pagina, asi que el total debe coincidir.
        $this->assertSame(count($keys), $response->json('data.totalCount'));
    }

    /**
     * El contrato exige que un `&&` seguido de varios `||` forme UN grupo OR
     * AND-eado con el resto; planos, el OR se llevaria los filtros anteriores.
     */
    public function test_contract_payload_groups_the_or_conditions(): void
    {
        $other = $this->makeCategory('ZOUT');

        // La `key` tambien lleva el termino: planos, los `||` se lo llevarian
        // por delante al status y a la categoria.
        $this->makeProduct(['key' => 'ZGRUPO-ACT', 'name' => 'ZGRUPO activo'], [1]);
        $this->makeProduct(['key' => 'ZGRUPO-INACT', 'name' => 'plano', 'status' => 'inactive'], [1]);
        $this->makeProduct(['key' => 'ZGRUPO-OUT', 'name' => 'plano'], [$other->id]);

        $keys = $this->keys($this->query([
            ['f' => 'status', 'ao' => '==', 'v' => 'active', 'lo' => '&&'],
            ['f' => 'categories', 'ao' => 'in', 'v' => [1, 2], 'lo' => '&&'],
            ['f' => 'name', 'ao' => 'contains', 'v' => 'ZGRUPO', 'lo' => '&&'],
            ['f' => 'key', 'ao' => 'contains', 'v' => 'ZGRUPO', 'lo' => '||'],
            ['f' => 'categories', 'ao' => 'contains', 'v' => 'ZGRUPO', 'lo' => '||'],
            ['f' => 'idSizeGroup', 'ao' => 'contains', 'v' => 'ZGRUPO', 'lo' => '||'],
        ]));

        $this->assertSame(['ZGRUPO-ACT'], $keys);
    }

    public function test_categories_contains_matches_the_category_name(): void
    {
        $category = $this->makeCategory('ZCATNOM especial');

        $this->makeProduct(
            ['key' => 'CN-1', 'name' => 'nombre plano', 'description' => 'desc plana'],
            [$category->id],
        );

        $keys = $this->keys($this->query([
            ['f' => 'categories', 'ao' => 'contains', 'v' => 'ZCATNOM', 'lo' => '&&'],
        ]));

        $this->assertSame(['CN-1'], $keys);
    }

    public function test_id_size_group_contains_matches_the_group_name(): void
    {
        $group = SizeGroup::create([
            'name'        => 'ZSGFILTRO grupo',
            'description' => 'x',
            'status'      => 'active',
        ]);

        $this->makeProduct([
            'key'           => 'SG-1',
            'name'          => 'nombre plano',
            'description'   => 'desc plana',
            'id_size_group' => $group->id,
        ]);

        $keys = $this->keys($this->query([
            ['f' => 'idSizeGroup', 'ao' => 'contains', 'v' => 'ZSGFILTRO', 'lo' => '&&'],
        ]));

        $this->assertSame(['SG-1'], $keys);
    }

    /** Fuera de `categories`, el sentinel `in` generaba `columna = 'in'`. */
    public function test_in_operator_on_a_plain_column(): void
    {
        $this->makeProduct(['key' => 'ZK-1']);
        $this->makeProduct(['key' => 'ZK-2']);
        $this->makeProduct(['key' => 'ZK-3']);

        $keys = $this->keys($this->query([
            ['f' => 'key', 'ao' => 'in', 'v' => ['ZK-1', 'ZK-2'], 'lo' => '&&'],
        ]));

        sort($keys);

        $this->assertSame(['ZK-1', 'ZK-2'], $keys);
    }

    public function test_noneof_excludes_products_in_those_categories(): void
    {
        $third = $this->makeCategory('ZTHIRD');

        $this->makeProduct(['key' => 'NO-A'], [1]);
        $this->makeProduct(['key' => 'NO-C'], [$third->id]);

        $keys = $this->keys($this->query([
            ['f' => 'categories', 'ao' => 'noneof', 'v' => [1, 2], 'lo' => '&&'],
        ]));

        $this->assertContains('NO-C', $keys);
        $this->assertNotContains('NO-A', $keys);
        // Los del seeder viven en las categorias 1 y 2.
        $this->assertNotContains('CAM-001', $keys);
        $this->assertNotContains('SERV-001', $keys);
    }

    /**
     * `between` es UNA condicion: partirlo en `>=` + `<=` rompia la agrupacion
     * cuando llega con `lo: '||'` (el `<=` abria bloque propio).
     */
    public function test_between_stays_whole_inside_an_or_group(): void
    {
        $this->makeProduct(['key' => 'BW-CARO', 'name' => 'ZBW caro', 'price' => 900.00]);
        $this->makeProduct(['key' => 'BW-RANGO', 'name' => 'ZBW rango', 'price' => 500.00]);
        $this->makeProduct(['key' => 'BW-BARATO', 'name' => 'ZBW barato', 'price' => 10.00]);

        // (name contains ZBW-caro) OR (price entre 400 y 600)
        $keys = $this->keys($this->query([
            ['f' => 'name', 'ao' => 'contains', 'v' => 'ZBW caro', 'lo' => '&&'],
            ['f' => 'price', 'ao' => 'between', 'v' => [400, 600], 'lo' => '||'],
        ]));

        sort($keys);

        $this->assertSame(['BW-CARO', 'BW-RANGO'], $keys);
    }
}
