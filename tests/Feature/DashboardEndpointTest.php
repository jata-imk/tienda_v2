<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
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
            'movement_type' => 'sale',
            'quantity' => 2,
            'previous_stock' => 3,
            'new_stock' => 1,
            'reference_type' => 'test',
            'reference_id' => null,
            'notes' => null,
            'id_user' => 1,
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
                    'criticalStockBySize',
                    'summary' => [
                        'totalProducts', 'activeProducts', 'totalVariants',
                        'totalStock', 'inventoryValue', 'inventorySaleValue', 'lowStockCount',
                        'outOfStockCount',
                    ],
                ],
            ]);

        $this->assertSame(2, $response->json('data.summary.totalProducts'));
        $this->assertSame(6600, $response->json('data.summary.inventoryValue'));
        $this->assertSame(8800, $response->json('data.summary.inventorySaleValue'));

        $lowest = $response->json('data.lowestStock');
        $highest = $response->json('data.highestStock');

        $this->assertLessThanOrEqual($lowest[count($lowest) - 1]['stock'], $lowest[0]['stock']);
        $this->assertGreaterThanOrEqual($highest[count($highest) - 1]['stock'], $highest[0]['stock']);
    }

    public function test_post_query_reads_filters_from_the_json_body(): void
    {
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail();

        InventoryMovement::create([
            'id_product_variant' => $variant->id,
            'movement_type' => 'sale',
            'quantity' => 2,
            'previous_stock' => 3,
            'new_stock' => 1,
            'reference_type' => 'test',
            'reference_id' => null,
            'notes' => null,
            'id_user' => 1,
        ]);

        $filters = [
            'limit' => 1,
            'dateFrom' => '2000-01-01',
            'dateTo' => '2000-01-31',
            'lowStockThreshold' => 0,
        ];

        $getData = $this->withoutMiddleware()
            ->getJson('/api/dashboard?'.http_build_query($filters))
            ->assertOk()
            ->json('data');

        $this->withoutMiddleware()
            ->postJson('/api/dashboard/query', $filters)
            ->assertOk()
            ->assertJsonPath('data', $getData)
            ->assertJsonCount(0, 'data.topProducts')
            ->assertJsonCount(1, 'data.lowestStock')
            ->assertJsonCount(1, 'data.highestStock')
            ->assertJsonCount(0, 'data.criticalStockBySize');
    }

    public function test_post_query_validates_the_json_body(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/dashboard/query', [
                'limit' => 999,
                'dateFrom' => '2026-08-20',
                'dateTo' => '2026-08-19',
                'lowStockThreshold' => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit', 'dateTo', 'lowStockThreshold']);
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
            'movement_type' => 'sale',
            'quantity' => 2,
            'previous_stock' => 3,
            'new_stock' => 1,
            'reference_type' => 'test',
            'reference_id' => null,
            'notes' => null,
            'id_user' => 1,
        ]);

        $this->withoutMiddleware()
            ->getJson('/api/dashboard?dateFrom=2000-01-01&dateTo=2000-01-31')
            ->assertOk()
            ->assertJsonCount(0, 'data.topProducts');
    }

    public function test_sales_on_the_last_day_of_the_range_are_included(): void
    {
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail();

        InventoryMovement::create([
            'id_product_variant' => $variant->id,
            'movement_type' => 'sale',
            'quantity' => 2,
            'previous_stock' => 3,
            'new_stock' => 1,
            'reference_type' => 'test',
            'reference_id' => null,
            'notes' => null,
            'id_user' => 1,
        ]);

        $today = now()->toDateString();

        $this->withoutMiddleware()
            ->getJson("/api/dashboard?dateFrom={$today}&dateTo={$today}")
            ->assertOk()
            ->assertJsonCount(1, 'data.topProducts')
            ->assertJsonPath('data.topProducts.0.quantitySold', 2);
    }

    /**
     * Seed: CAM-001 tiene talla 34 = 3 (BLA) + 1 (AZM) = 4, talla 36 = 1 + 0 = 1
     * y talla 38 = 2 + 4 = 6. Con umbral 5 entran 34 y 36, no 38.
     */
    public function test_critical_stock_by_size_sums_colors_and_applies_the_threshold(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('/api/dashboard?lowStockThreshold=5')
            ->assertOk()
            ->assertJsonCount(2, 'data.criticalStockBySize')
            ->assertJsonPath('data.criticalStockBySize.0.product', 'Camisa lino caballero')
            ->assertJsonPath('data.criticalStockBySize.0.key', 'CAM-001')
            ->assertJsonPath('data.criticalStockBySize.0.size', '36')
            ->assertJsonPath('data.criticalStockBySize.0.stock', 1)
            ->assertJsonPath('data.criticalStockBySize.1.size', '34')
            ->assertJsonPath('data.criticalStockBySize.1.stock', 4);

        $this->assertNotContains('38', array_column($response->json('data.criticalStockBySize'), 'size'));
    }

    public function test_critical_stock_by_size_is_not_capped_by_limit(): void
    {
        $this->withoutMiddleware()
            ->getJson('/api/dashboard?limit=1&lowStockThreshold=5')
            ->assertOk()
            ->assertJsonCount(1, 'data.lowestStock')
            ->assertJsonCount(2, 'data.criticalStockBySize');
    }

    public function test_critical_stock_by_size_excludes_products_without_stock_control(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('/api/dashboard?lowStockThreshold=1000')
            ->assertOk()
            ->assertJsonCount(3, 'data.criticalStockBySize');

        $this->assertNotContains('SERV-001', array_column($response->json('data.criticalStockBySize'), 'key'));
    }

    public function test_zero_stock_without_movements_is_excluded_from_all_alerts(): void
    {
        [$product] = $this->createInventoryProductWithVariant(
            key: 'NEW-001',
            sku: 'NEW-001-34-BLA',
            stock: 0,
        );

        $response = $this->withoutMiddleware()
            ->getJson('/api/dashboard?limit=50&lowStockThreshold=5')
            ->assertOk()
            ->assertJsonPath('data.summary.lowStockCount', 0)
            ->assertJsonPath('data.summary.outOfStockCount', 0);

        $this->assertNotContains($product->key, array_column($response->json('data.criticalStockBySize'), 'key'));
        $this->assertNotContains($product->key, array_column($response->json('data.lowestStock'), 'key'));
    }

    public function test_depleted_stock_with_movements_is_included_in_all_alerts(): void
    {
        [$product, $variant] = $this->createInventoryProductWithVariant(
            key: 'OUT-001',
            sku: 'OUT-001-34-BLA',
            stock: 0,
        );

        InventoryMovement::create([
            'id_product_variant' => $variant->id,
            'movement_type' => 'sale',
            'quantity' => 1,
            'previous_stock' => 1,
            'new_stock' => 0,
            'reference_type' => 'test',
            'reference_id' => null,
            'notes' => null,
            'id_user' => 1,
        ]);

        $response = $this->withoutMiddleware()
            ->getJson('/api/dashboard?limit=50&lowStockThreshold=5')
            ->assertOk()
            ->assertJsonPath('data.summary.lowStockCount', 1)
            ->assertJsonPath('data.summary.outOfStockCount', 1);

        $criticalRow = collect($response->json('data.criticalStockBySize'))
            ->firstWhere('key', $product->key);
        $lowestRow = collect($response->json('data.lowestStock'))
            ->firstWhere('key', $product->key);

        $this->assertSame(0, $criticalRow['stock'] ?? null);
        $this->assertSame(0, $lowestRow['stock'] ?? null);
    }

    public function test_positive_stock_without_movements_is_still_included_in_alerts(): void
    {
        [$product] = $this->createInventoryProductWithVariant(
            key: 'LEGACY-001',
            sku: 'LEGACY-001-34-BLA',
            stock: 2,
        );

        $response = $this->withoutMiddleware()
            ->getJson('/api/dashboard?limit=50&lowStockThreshold=5')
            ->assertOk()
            ->assertJsonPath('data.summary.lowStockCount', 1)
            ->assertJsonPath('data.summary.outOfStockCount', 0);

        $this->assertContains($product->key, array_column($response->json('data.criticalStockBySize'), 'key'));
        $this->assertContains($product->key, array_column($response->json('data.lowestStock'), 'key'));
    }

    /**
     * Seed: CAM-001 (unico producto con control de existencias) suma 11.
     * Ningun umbral por debajo de 11 debe contarlo — el umbral llega como
     * float y un binding mal comparado haria pasar cualquier producto.
     */
    public function test_stock_counters_compare_the_threshold_numerically(): void
    {
        $this->withoutMiddleware()
            ->getJson('/api/dashboard?lowStockThreshold=5')
            ->assertOk()
            ->assertJsonPath('data.summary.lowStockCount', 0)
            ->assertJsonPath('data.summary.outOfStockCount', 0);

        $this->withoutMiddleware()
            ->getJson('/api/dashboard?lowStockThreshold=11')
            ->assertOk()
            ->assertJsonPath('data.summary.lowStockCount', 1);
    }

    public function test_out_of_stock_count_is_global_and_ignores_limit(): void
    {
        $this->withoutMiddleware()
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.outOfStockCount', 0);

        ProductVariant::query()->update(['stock' => 0]);

        $this->withoutMiddleware()
            ->getJson('/api/dashboard?limit=1')
            ->assertOk()
            ->assertJsonPath('data.summary.outOfStockCount', 1);

        $this->withoutMiddleware()
            ->getJson('/api/dashboard?limit=50')
            ->assertOk()
            ->assertJsonPath('data.summary.outOfStockCount', 1);
    }

    public function test_invalid_limit_is_rejected(): void
    {
        $this->withoutMiddleware()
            ->getJson('/api/dashboard?limit=999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);
    }

    /**
     * @return array{Product, ProductVariant}
     */
    private function createInventoryProductWithVariant(string $key, string $sku, float $stock): array
    {
        $product = Product::where('key', 'CAM-001')->firstOrFail()->replicate();
        $product->key = $key;
        $product->name = $key;
        $product->save();

        $variant = ProductVariant::create([
            'id_product' => $product->id,
            'id_size' => 2,
            'id_color' => 1,
            'sku' => $sku,
            'code_bar' => null,
            'stock' => $stock,
            'status' => 'active',
        ]);

        return [$product, $variant];
    }
}
