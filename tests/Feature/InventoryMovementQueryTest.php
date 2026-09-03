<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMovementQueryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->user = User::firstOrFail();
        $this->variant = ProductVariant::firstOrFail();

        InventoryMovement::create([
            'id_product_variant' => $this->variant->id,
            'movement_type'      => 'entry',
            'quantity'           => 10,
            'previous_stock'     => 0,
            'new_stock'          => 10,
            'reference_type'     => 'initial_load',
            'reference_id'       => null,
            'notes'              => 'Carga inicial especial de prueba',
            'id_user'            => $this->user->id,
        ]);

        InventoryMovement::create([
            'id_product_variant' => $this->variant->id,
            'movement_type'      => 'adjustment',
            'quantity'           => 2,
            'previous_stock'     => 10,
            'new_stock'          => 8,
            'reference_type'     => 'manual_adjustment',
            'reference_id'       => null,
            'notes'              => 'Ajuste mensual de inventario',
            'id_user'            => $this->user->id,
        ]);
    }

    public function test_get_inventory_movements_returns_paginated_grid(): void
    {
        $response = $this->withoutMiddleware()->getJson('/api/inventory/movements?p[page]=1&p[per_page]=1&totalCount=true');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.totalCount', InventoryMovement::count())
            ->assertJsonCount(1, 'data.items');

        $item = $response->json('data.items.0');
        $this->assertArrayHasKey('userName', $item);
        $this->assertArrayHasKey('sku', $item);
        $this->assertArrayHasKey('productName', $item);
    }

    public function test_post_inventory_movements_query_filters_by_type(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/inventory/movements/query', [
            'w' => [
                ['f' => 'movement_type', 'ao' => '==', 'v' => 'adjustment', 'lo' => '&&'],
            ],
            'totalCount' => true,
        ]);

        $response->assertOk();
        $items = $response->json('data.items');
        $expected = InventoryMovement::where('movement_type', 'adjustment')->count();
        $this->assertCount($expected, $items);
        $this->assertSame('adjustment', $items[0]['movementType']);
    }

    public function test_post_inventory_movements_query_search(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/inventory/movements/query', [
            'w' => [
                ['f' => 'search', 'ao' => 'contains', 'v' => 'especial de prueba', 'lo' => '&&'],
            ],
            'totalCount' => true,
        ]);

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertSame('Carga inicial especial de prueba', $items[0]['notes']);
    }

    public function test_post_inventory_movements_query_by_product(): void
    {
        $product = $this->variant->product;
        $expected = InventoryMovement::whereHas('variant', fn($q) => $q->where('id_product', $product->id))->count();

        $response = $this->withoutMiddleware()->postJson('/api/inventory/movements/query', [
            'w' => [
                ['f' => 'id_product', 'ao' => '==', 'v' => $product->id, 'lo' => '&&'],
            ],
            'totalCount' => true,
        ]);

        $response->assertOk();
        $this->assertSame($expected, $response->json('data.totalCount'));
    }
}
