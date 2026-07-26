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
}
