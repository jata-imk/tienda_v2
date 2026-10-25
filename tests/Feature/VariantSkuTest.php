<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use App\Support\VariantSku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SKU automatico: `CLAVENORMALIZADA-S{idTalla}-C{idColor}`, misma regla que el
 * frontend. Datos del seeder: producto 1 (CAM-001) con variantes en los colores
 * 1 y 2; el color 3 esta libre. Talla 2 = 34, talla 3 = 36 (Adultos).
 */
class VariantSkuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_normalize_key_matches_the_frontend_rule(): void
    {
        $this->assertSame('CAM001', VariantSku::normalizeKey('CAM-001'));
        $this->assertSame('FIL004', VariantSku::normalizeKey('FIl-004'));
        $this->assertSame('CAMNINO01', VariantSku::normalizeKey('Cam niño 01'));
        $this->assertSame('PROD', VariantSku::normalizeKey(' -- '));
    }

    public function test_for_builds_the_sku_from_catalog_ids(): void
    {
        $this->assertSame('CAM001-S2-C17', VariantSku::for('CAM-001', 2, 17));
    }

    public function test_product_store_generates_missing_skus(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.key', 'CAM-002')
            ->assertJsonPath('data.variants.0.sku', 'CAM002-S2-C1');
    }

    public function test_variant_store_generates_with_the_current_product_key(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/products/1/variants', [
                'variants' => [['idSize' => 2, 'idColor' => 3, 'stock' => 1]],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('product_variants', [
            'id_product' => 1, 'id_size' => 2, 'id_color' => 3, 'sku' => 'CAM001-S2-C3',
        ]);
    }

    public function test_keys_that_normalize_alike_get_a_numeric_suffix(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/products/1/variants', [
                'variants' => [['idSize' => 2, 'idColor' => 3, 'stock' => 1]],
            ])
            ->assertCreated();

        $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload([
                'key'      => 'CAM 001',
                'variants' => [['idSize' => 2, 'idColor' => 3, 'stock' => 1]],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.variants.0.sku', 'CAM001-S2-C3-2');
    }

    public function test_manual_skus_are_kept_and_reserved_within_the_payload(): void
    {
        $response = $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload([
                'key'      => 'NEW-001',
                'variants' => [
                    ['idSize' => 3, 'idColor' => 1, 'sku' => 'NEW001-S2-C1', 'stock' => 1],
                    ['idSize' => 2, 'idColor' => 1, 'stock' => 1],
                ],
            ]))
            ->assertCreated();

        $skus = collect($response->json('data.variants'))->pluck('sku')->sort()->values()->all();

        $this->assertSame(['NEW001-S2-C1', 'NEW001-S2-C1-2'], $skus);
    }

    public function test_manual_skus_are_stored_in_uppercase(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload([
                'variants' => [['idSize' => 2, 'idColor' => 1, 'sku' => ' cam002-manual ', 'stock' => 1]],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.variants.0.sku', 'CAM002-MANUAL');

        $this->withoutMiddleware()
            ->postJson('/api/products/1/variants', [
                'variants' => [['idSize' => 2, 'idColor' => 3, 'sku' => 'cam001-beige', 'stock' => 1]],
            ])
            ->assertCreated();

        $variantId = ProductVariant::where('sku', 'CAM001-BEIGE')->value('id');
        $this->assertNotNull($variantId);

        $this->withoutMiddleware()
            ->putJson("/api/products/1/variants/{$variantId}", ['sku' => 'cam001-beige-2'])
            ->assertOk()
            ->assertJsonPath('data.sku', 'CAM001-BEIGE-2');
    }

    public function test_skus_that_only_differ_in_case_are_rejected_in_the_same_payload(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/products', $this->payload([
                'variants' => [
                    ['idSize' => 2, 'idColor' => 1, 'sku' => 'abc', 'stock' => 1],
                    ['idSize' => 3, 'idColor' => 1, 'sku' => 'ABC', 'stock' => 1],
                ],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['variants.0.sku']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'categories'   => [1],
            'idSizeGroup'  => 1,
            'name'         => 'Producto de prueba',
            'price'        => 100,
            'cost'         => 50,
            'stockControl' => true,
            'typeIva'      => 1,
            'variants'     => [
                ['idSize' => 2, 'idColor' => 1, 'stock' => 5],
            ],
        ], $overrides);
    }
}
