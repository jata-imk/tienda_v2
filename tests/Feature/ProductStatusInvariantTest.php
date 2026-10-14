<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Coherencia de status entre producto y variantes en los caminos que la cascada
 * de ProductService::update()/destroy() no cubre: el alta y la edicion de una
 * variante suelta.
 *
 * Regla: un producto inactivo no puede tener variantes activas. Al crear, las
 * variantes heredan el status del producto si este esta inactivo; al editar,
 * activar una variante de un producto inactivo se rechaza.
 *
 * Datos del seeder: producto 1 (CAM-001, stockControl, grupo de tallas 1) con
 * seis variantes. Color 3 (Beige) libre. Tallas 2=34, 3=36, 4=38.
 */
class ProductStatusInvariantTest extends TestCase
{
    use RefreshDatabase;

    private const SIZE_34 = 2;
    private const SIZE_36 = 3;
    private const COLOR_BEIGE = 3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'categories'   => [1],
            'idSizeGroup'  => 1,
            'key'          => 'NEW-001',
            'name'         => 'Producto nuevo',
            'price'        => 100,
            'cost'         => 50,
            'stockControl' => true,
            'typeIva'      => 1,
            'isr'          => 0,
            'impEsp'       => 0,
            'variants'     => [
                ['idSize' => self::SIZE_34, 'idColor' => self::COLOR_BEIGE, 'sku' => 'NEW-001-34-BEI', 'stock' => 1],
                ['idSize' => self::SIZE_36, 'idColor' => self::COLOR_BEIGE, 'sku' => 'NEW-001-36-BEI', 'stock' => 2],
            ],
        ], $overrides);
    }

    public function test_creating_an_inactive_product_creates_its_variants_inactive(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products', $this->basePayload([
            'status' => 'inactive',
        ]));

        $response->assertCreated();

        $product = Product::where('key', 'NEW-001')->firstOrFail();

        $this->assertSame('inactive', $product->status);
        $this->assertSame(
            [],
            ProductVariant::where('id_product', $product->id)->where('status', 'active')->pluck('sku')->all(),
            'Un producto creado inactivo no debe nacer con variantes activas.'
        );
    }

    public function test_creating_an_active_product_respects_an_explicitly_inactive_variant(): void
    {
        $payload = $this->basePayload();
        $payload['variants'][1]['status'] = 'inactive';

        $this->withoutMiddleware()->postJson('/api/products', $payload)->assertCreated();

        $product = Product::where('key', 'NEW-001')->firstOrFail();

        $this->assertSame('active', $product->status);
        $this->assertSame('active', ProductVariant::where('sku', 'NEW-001-34-BEI')->firstOrFail()->status);
        $this->assertSame(
            'inactive',
            ProductVariant::where('sku', 'NEW-001-36-BEI')->firstOrFail()->status,
            'Una variante inactiva en un producto activo es un estado legitimo: no debe forzarse a activa.'
        );
    }

    public function test_adding_variants_to_an_inactive_product_creates_them_inactive(): void
    {
        $this->withoutMiddleware()->deleteJson('/api/products/1')->assertOk();

        $response = $this->withoutMiddleware()->postJson('/api/products/1/variants', [
            'variants' => [
                ['idSize' => self::SIZE_34, 'idColor' => self::COLOR_BEIGE, 'sku' => 'CAM-001-34-BEI', 'stock' => 3],
            ],
        ]);

        $response->assertCreated();

        $this->assertSame(
            'inactive',
            ProductVariant::where('sku', 'CAM-001-34-BEI')->firstOrFail()->status,
            'Una variante agregada a un producto inactivo no debe nacer activa.'
        );
    }

    public function test_activating_a_variant_of_an_inactive_product_is_rejected(): void
    {
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail();

        $this->withoutMiddleware()->deleteJson('/api/products/1')->assertOk();

        $response = $this->withoutMiddleware()->putJson("/api/products/1/variants/{$variant->id}", [
            'status' => 'active',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');

        $this->assertSame('inactive', $variant->fresh()->status, 'La variante no debe haber cambiado.');
    }

    public function test_activating_a_variant_of_an_active_product_still_works(): void
    {
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail();
        $variant->update(['status' => 'inactive']);

        $this->withoutMiddleware()
            ->putJson("/api/products/1/variants/{$variant->id}", ['status' => 'active'])
            ->assertOk();

        $this->assertSame('active', $variant->fresh()->status);
    }

    public function test_deactivating_a_variant_of_an_inactive_product_is_allowed(): void
    {
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail();

        DB::table('products')->where('id', 1)->update(['status' => 'inactive']);

        $this->withoutMiddleware()
            ->putJson("/api/products/1/variants/{$variant->id}", ['status' => 'inactive'])
            ->assertOk();

        $this->assertSame('inactive', $variant->fresh()->status);
    }

    public function test_editing_other_fields_of_a_variant_of_an_inactive_product_is_allowed(): void
    {
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail();

        $this->withoutMiddleware()->deleteJson('/api/products/1')->assertOk();

        $this->withoutMiddleware()
            ->putJson("/api/products/1/variants/{$variant->id}", ['codeBar' => '7500000000011'])
            ->assertOk();

        $this->assertSame('7500000000011', $variant->fresh()->code_bar);
    }

    /**
     * Las variantes de un producto inactivo no deben sumar en los totales del
     * dashboard. Se provoca la desincronizacion por query builder para simular
     * los datos historicos previos a la cascada.
     */
    public function test_dashboard_totals_ignore_variants_of_inactive_products(): void
    {
        $before = $this->withoutMiddleware()->getJson('/api/dashboard')->json('data.summary');

        DB::table('products')->where('id', 1)->update(['status' => 'inactive']);

        $after = $this->withoutMiddleware()->getJson('/api/dashboard')->json('data.summary');

        $this->assertSame(0, $after['totalVariants'], 'Solo el producto 1 tiene variantes.');
        $this->assertSame(0.0, (float) $after['totalStock']);
        $this->assertSame(0.0, (float) $after['inventoryValue']);
        $this->assertSame(0.0, (float) $after['inventorySaleValue']);

        $this->assertGreaterThan(0, $before['totalVariants'], 'Control: antes si contaban.');
    }
}
