<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsPurchaseOrders;
use Tests\TestCase;

class OrderQueryTest extends TestCase
{
    use BuildsPurchaseOrders;
    use RefreshDatabase;

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootPurchaseOrders();

        $blanco = $this->variant('CAM-001-34-BLA');
        $azul   = $this->variant('CAM-001-38-AZM');

        // PED-1 draft (blanco), PED-2 confirmed (azul), PED-3 draft (azul).
        $this->ids[] = $this->createOrder([['idProductVariant' => $blanco->id, 'quantityOrdered' => 1]], ['supplierName' => 'Telas del Sureste'])->json('data.id');
        $this->ids[] = $this->confirmedOrder([['idProductVariant' => $azul->id, 'quantityOrdered' => 1]], ['notes' => 'urgente'])['id'];
        $this->ids[] = $this->createOrder([['idProductVariant' => $azul->id, 'quantityOrdered' => 1]])->json('data.id');
    }

    public function test_default_order_is_newest_first_without_lines(): void
    {
        $response = $this->asAdmin()->postJson('/api/orders/query', [])->assertOk();

        $this->assertSame(array_reverse($this->ids), array_column($response->json('data.items'), 'id'));
        $response->assertJsonPath('data.totalCount', 3)->assertJsonMissingPath('data.items.0.details');
    }

    public function test_filters_by_status(): void
    {
        $response = $this->asAdmin()->postJson('/api/orders/query', [
            'w' => [['f' => 'status', 'ao' => '==', 'v' => 'draft']],
        ])->assertOk();

        $this->assertEqualsCanonicalizing([$this->ids[0], $this->ids[2]], array_column($response->json('data.items'), 'id'));
    }

    public function test_search_matches_folio_supplier_and_notes(): void
    {
        foreach (['PED-0000002' => $this->ids[1], 'PED-2' => $this->ids[1], '2' => $this->ids[1], 'sureste' => $this->ids[0], 'urgente' => $this->ids[1]] as $term => $expected) {
            $response = $this->asAdmin()->postJson('/api/orders/query', [
                'w' => [['f' => 'search', 'ao' => 'contains', 'v' => $term]],
            ])->assertOk();

            $this->assertSame([$expected], array_column($response->json('data.items'), 'id'), "search={$term}");
        }
    }

    public function test_filters_by_product_and_sku_through_the_lines(): void
    {
        $response = $this->asAdmin()->postJson('/api/orders/query', [
            'w' => [['f' => 'sku', 'ao' => '==', 'v' => 'CAM-001-38-AZM']],
        ])->assertOk();

        $this->assertEqualsCanonicalizing([$this->ids[1], $this->ids[2]], array_column($response->json('data.items'), 'id'));

        $productId = $this->variant('CAM-001-38-AZM')->id_product;
        $this->asAdmin()->postJson('/api/orders/query', [
            'w' => [['f' => 'idProduct', 'ao' => '==', 'v' => $productId]],
        ])->assertOk()->assertJsonPath('data.totalCount', 3);
    }

    public function test_paginates_and_sorts(): void
    {
        $response = $this->asAdmin()->postJson('/api/orders/query', [
            'p' => ['page' => 0, 'per_page' => 2],
            'o' => ['column' => 'folio', 'direction' => 'asc'],
        ])->assertOk();

        $response->assertJsonPath('data.totalCount', 3);
        $this->assertSame([1, 2], array_column($response->json('data.items'), 'folio'));

        $this->asAdmin()->postJson('/api/orders/query', ['o' => ['column' => 'nope']])->assertUnprocessable();
    }
}
