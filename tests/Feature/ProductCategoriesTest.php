<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_store_saves_multiple_categories_and_returns_id_desc_pairs(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products', $this->payload());

        $response
            ->assertCreated()
            ->assertJsonPath('data.categories.0.id', 1)
            ->assertJsonPath('data.categories.0.desc', 'Camisas lino')
            ->assertJsonPath('data.categories.1.id', 2)
            ->assertJsonPath('data.categories.1.desc', 'Caballero');

        $product = Product::where('key', 'TEST-001')->firstOrFail();

        $this->assertSame([1, 2], $product->categories->pluck('id')->all());
    }

    public function test_updated_at_is_null_on_create_and_set_on_update(): void
    {
        $created = $this->withoutMiddleware()->postJson('/api/products', $this->payload());

        $created->assertCreated()->assertJsonPath('data.updatedAt', null);

        $id = $created->json('data.id');

        $this->withoutMiddleware()
            ->putJson("/api/products/{$id}", ['name' => 'Producto modificado'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Producto modificado');

        $this->assertNotNull(Product::find($id)->updated_at);
    }

    public function test_update_syncs_categories(): void
    {
        $id = $this->withoutMiddleware()->postJson('/api/products', $this->payload())->json('data.id');

        $this->withoutMiddleware()
            ->putJson("/api/products/{$id}", ['categories' => [2]])
            ->assertOk()
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.categories.0.id', 2);
    }

    public function test_store_rejects_a_scalar_category(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload(['categories' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['categories']);
    }

    public function test_store_accepts_category_objects_from_dx_tag_box(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload([
                'categories' => [['id' => 1, 'desc' => 'Camisas lino'], ['id' => 2, 'desc' => 'Caballero']],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.categories.0.id', 1)
            ->assertJsonPath('data.categories.1.id', 2);
    }

    public function test_query_filters_products_by_category_through_the_pivot(): void
    {
        $this->withoutMiddleware()->postJson('/api/products', $this->payload())->assertCreated();

        // Sembrado: CAM-001 -> categorias [1,2]; SERV-001 -> [2]. TEST-001 -> [1,2].
        $response = $this->withoutMiddleware()->postJson('/api/products/query', [
            'w'          => ['categories' => 1],
            'totalCount' => true,
        ]);

        $response->assertOk();

        $keys = collect($response->json('data.items'))->pluck('key');

        $this->assertTrue($keys->contains('TEST-001'));
        $this->assertTrue($keys->contains('CAM-001'));
        $this->assertFalse($keys->contains('SERV-001'), 'SERV-001 solo pertenece a la categoria 2');
    }

    public function test_query_still_accepts_the_id_category_filter_key(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products/query', [
            'w'          => [['f' => 'id_category', 'ao' => '==', 'v' => 2, 'lo' => '&&']],
            'totalCount' => true,
        ]);

        $response->assertOk();

        $this->assertSame(2, $response->json('data.totalCount'));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'categories'   => [1, 2],
            'idSizeGroup'  => 1,
            'key'          => 'TEST-001',
            'name'         => 'Producto de prueba',
            'price'        => 100,
            'cost'         => 50,
            'stockControl' => true,
            'typeIva'      => 1,
            'rateIva'      => 16,
            'variants'     => [
                ['idSize' => 2, 'idColor' => 1, 'sku' => 'TEST-001-34-BLA', 'stock' => 5],
            ],
        ], $overrides);
    }
}
