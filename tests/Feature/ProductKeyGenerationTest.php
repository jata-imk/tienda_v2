<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clave de producto automatica y categoria principal.
 *
 * Datos del seeder: categorias 1 `CAM` (Camisas lino) y 2 `CAB` (Caballero);
 * grupos de tallas 1 Adultos (sin clave) y 2 Niños (`NIN`); producto CAM-001.
 */
class ProductKeyGenerationTest extends TestCase
{
    use RefreshDatabase;

    private const ADULT_SIZE = 2;
    private const KIDS_SIZE  = 15;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_store_without_key_continues_the_main_category_series(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.key', 'CAM-002')
            ->assertJsonPath('data.idMainCategory', 1)
            ->assertJsonPath('data.mainCategory.key', 'CAM');
    }

    public function test_next_number_starts_from_the_max_and_ignores_other_series(): void
    {
        $this->makeProduct('CAM-007');
        $this->makeProduct('CAM-003');
        $this->makeProduct('CAM-NIN-030');
        $this->makeProduct('CAMX-050');

        $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.key', 'CAM-008');
    }

    public function test_existing_keys_are_compared_case_insensitively(): void
    {
        $this->makeProduct('Cam-010');

        $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.key', 'CAM-011');
    }

    public function test_kids_size_group_adds_its_segment(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload([
                'idSizeGroup' => 2,
                'variants'    => [['idSize' => self::KIDS_SIZE, 'idColor' => 1, 'stock' => 1]],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.key', 'CAM-NIN-001');
    }

    public function test_key_segment_is_appended_in_uppercase(): void
    {
        $this->makeProduct('CAM-DAM-001');

        $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload(['keySegment' => 'dam']))
            ->assertCreated()
            ->assertJsonPath('data.key', 'CAM-DAM-002');
    }

    public function test_manual_key_is_respected(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload(['key' => 'ESPECIAL-1']))
            ->assertCreated()
            ->assertJsonPath('data.key', 'ESPECIAL-1');
    }

    public function test_manual_key_is_stored_in_uppercase(): void
    {
        $id = $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload(['key' => 'cam-050']))
            ->assertCreated()
            ->assertJsonPath('data.key', 'CAM-050')
            ->json('data.id');

        $this->withoutMiddleware()
            ->putJson("/api/products/{$id}", ['key' => 'cam-051'])
            ->assertOk()
            ->assertJsonPath('data.key', 'CAM-051');
    }

    public function test_categories_must_be_a_list(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload(['categories' => ['1' => 2]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['categories']);
    }

    public function test_main_category_defaults_to_the_first_category(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload(['categories' => [2, 1]]))
            ->assertCreated()
            ->assertJsonPath('data.idMainCategory', 2)
            ->assertJsonPath('data.key', 'CAB-001');
    }

    public function test_main_category_is_added_to_the_categories(): void
    {
        $response = $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload(['categories' => [2], 'idMainCategory' => 1]))
            ->assertCreated()
            ->assertJsonPath('data.key', 'CAM-002');

        $product = Product::findOrFail($response->json('data.id'));

        $this->assertEqualsCanonicalizing([1, 2], $product->categories->pluck('id')->all());
    }

    public function test_update_moves_the_main_category_when_it_is_removed(): void
    {
        $id = $this->withoutMiddleware()->postJson('/api/products', $this->payload())->json('data.id');

        $this->withoutMiddleware()
            ->putJson("/api/products/{$id}", ['categories' => [2]])
            ->assertOk()
            ->assertJsonPath('data.idMainCategory', 2)
            ->assertJsonPath('data.key', 'CAM-002')
            ->assertJsonCount(1, 'data.categories');
    }

    public function test_update_main_category_attaches_it_without_changing_the_key(): void
    {
        $id = $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload(['categories' => [1]]))
            ->json('data.id');

        $this->withoutMiddleware()
            ->putJson("/api/products/{$id}", ['idMainCategory' => 2])
            ->assertOk()
            ->assertJsonPath('data.idMainCategory', 2)
            ->assertJsonPath('data.key', 'CAM-002')
            ->assertJsonCount(2, 'data.categories');
    }

    public function test_next_key_endpoint_previews_without_reserving(): void
    {
        $this->withoutMiddleware()
            ->getJson('/api/products/next-key?idMainCategory=1')
            ->assertOk()
            ->assertJsonPath('data.key', 'CAM-002');

        $this->withoutMiddleware()
            ->getJson('/api/products/next-key?idMainCategory=1&idSizeGroup=2&keySegment=dam')
            ->assertOk()
            ->assertJsonPath('data.key', 'CAM-NIN-DAM-001');

        $this->withoutMiddleware()
            ->getJson('/api/products/next-key?idMainCategory=1')
            ->assertJsonPath('data.key', 'CAM-002');
    }

    public function test_next_key_requires_an_existing_main_category(): void
    {
        $this->withoutMiddleware()
            ->getJson('/api/products/next-key')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idMainCategory']);

        $this->withoutMiddleware()
            ->getJson('/api/products/next-key?idMainCategory=999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idMainCategory']);
    }

    private function makeProduct(string $key): Product
    {
        return Product::create([
            'id_main_category' => 1,
            'key'              => $key,
            'name'             => "Producto {$key}",
            'price'            => 100,
            'cost'             => 50,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'categories'   => [1, 2],
            'idSizeGroup'  => 1,
            'name'         => 'Producto de prueba',
            'price'        => 100,
            'cost'         => 50,
            'stockControl' => true,
            'typeIva'      => 1,
            'rateIva'      => 16,
            'variants'     => [
                ['idSize' => self::ADULT_SIZE, 'idColor' => 1, 'stock' => 5],
            ],
        ], $overrides);
    }
}
