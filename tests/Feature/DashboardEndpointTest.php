<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_dashboard_returns_top_products_stock_ranking_and_totals(): void
    {
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail();

        InventoryMovement::create([
            'id_product_variant' => $variant->id,
            'movement_type'      => 'sale',
            'quantity'           => 2,
            'previous_stock'     => 3,
            'new_stock'          => 1,
            'reference_type'     => 'test',
            'reference_id'       => null,
            'notes'              => null,
            'id_user'            => 1,
        ]);

        $response = $this->withoutMiddleware()->getJson('/api/dashboard?limit=3&lowStockThreshold=10');

        $response
            ->assertOk()
            ->assertJsonPath('data.topProducts.0.id', $variant->id_product)
            ->assertJsonPath('data.topProducts.0.quantitySold', 2)
            ->assertJsonCount(1, 'data.topProducts')
            ->assertJsonStructure([
                'data' => [
                    'topProducts',
                    'lowestStock',
                    'highestStock',
                    'summary' => [
                        'totalProducts', 'activeProducts', 'totalVariants',
                        'totalStock', 'inventoryValue', 'lowStockCount',
                    ],
                ],
            ]);

        $this->assertSame(2, $response->json('data.summary.totalProducts'));

        $lowest  = $response->json('data.lowestStock');
        $highest = $response->json('data.highestStock');

        $this->assertLessThanOrEqual($lowest[count($lowest) - 1]['stock'], $lowest[0]['stock']);
        $this->assertGreaterThanOrEqual($highest[count($highest) - 1]['stock'], $highest[0]['stock']);
    }

    public function test_limit_caps_the_rankings(): void
    {
        $this->withoutMiddleware()
            ->getJson('/api/dashboard?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.lowestStock')
            ->assertJsonCount(1, 'data.highestStock');
    }

    public function test_sales_outside_the_date_range_are_excluded(): void
    {
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail();

        InventoryMovement::create([
            'id_product_variant' => $variant->id,
            'movement_type'      => 'sale',
            'quantity'           => 2,
            'previous_stock'     => 3,
            'new_stock'          => 1,
            'reference_type'     => 'test',
            'reference_id'       => null,
            'notes'              => null,
            'id_user'            => 1,
        ]);

        $this->withoutMiddleware()
            ->getJson('/api/dashboard?dateFrom=2000-01-01&dateTo=2000-01-31')
            ->assertOk()
            ->assertJsonCount(0, 'data.topProducts');
    }

    public function test_invalid_limit_is_rejected(): void
    {
        $this->withoutMiddleware()
            ->getJson('/api/dashboard?limit=999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);
    }
}
