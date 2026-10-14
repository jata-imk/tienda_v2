<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cascada de status producto -> variantes.
 *
 * El status de un producto y el de sus variantes nunca deben quedar en
 * desacuerdo: dar de baja el producto desactiva todas sus variantes y
 * reactivarlo las reactiva. La cascada es solo hacia abajo: desactivar una
 * variante suelta no toca al producto.
 *
 * Datos del seeder: producto 1 (CAM-001) con seis variantes activas.
 */
class ProductStatusCascadeTest extends TestCase
{
    use RefreshDatabase;

    private const PRODUCT = 1;
    private const VARIANT_COUNT = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function variantStatuses(): array
    {
        return ProductVariant::where('id_product', self::PRODUCT)
            ->orderBy('id')
            ->pluck('status', 'id')
            ->all();
    }

    public function test_delete_product_deactivates_all_its_variants(): void
    {
        $this->assertCount(self::VARIANT_COUNT, array_filter($this->variantStatuses(), fn($s) => $s === 'active'));

        $this->withoutMiddleware()->deleteJson('/api/products/' . self::PRODUCT)->assertOk();

        $this->assertSame('inactive', Product::find(self::PRODUCT)->status);
        $this->assertSame(
            [],
            array_filter($this->variantStatuses(), fn($s) => $s === 'active'),
            'Ninguna variante debe quedar activa tras dar de baja el producto.'
        );
    }

    public function test_update_to_inactive_deactivates_all_its_variants(): void
    {
        $response = $this->withoutMiddleware()->putJson('/api/products/' . self::PRODUCT, [
            'status' => 'inactive',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'inactive');

        $this->assertSame(
            [],
            array_filter($this->variantStatuses(), fn($s) => $s === 'active'),
            'La baja via PUT debe cascadear igual que la via DELETE.'
        );
    }

    public function test_update_to_active_reactivates_all_its_variants(): void
    {
        $this->withoutMiddleware()->deleteJson('/api/products/' . self::PRODUCT)->assertOk();

        $this->withoutMiddleware()->putJson('/api/products/' . self::PRODUCT, [
            'status' => 'active',
        ])->assertOk();

        $this->assertSame('active', Product::find(self::PRODUCT)->status);
        $this->assertCount(
            self::VARIANT_COUNT,
            array_filter($this->variantStatuses(), fn($s) => $s === 'active'),
            'Reactivar el producto debe reactivar todas sus variantes.'
        );
    }

    public function test_update_without_status_change_leaves_variants_untouched(): void
    {
        $first = ProductVariant::where('id_product', self::PRODUCT)->orderBy('id')->first();
        $first->update(['status' => 'inactive']);

        $this->withoutMiddleware()->putJson('/api/products/' . self::PRODUCT, [
            'name' => 'Camisa lino caballero premium',
        ])->assertOk();

        $statuses = $this->variantStatuses();

        $this->assertSame('inactive', $statuses[$first->id], 'Un PUT sin cambio de status no debe revivir variantes.');
        $this->assertCount(self::VARIANT_COUNT - 1, array_filter($statuses, fn($s) => $s === 'active'));
    }

    public function test_resending_the_same_status_leaves_variants_untouched(): void
    {
        $first = ProductVariant::where('id_product', self::PRODUCT)->orderBy('id')->first();
        $first->update(['status' => 'inactive']);

        // El frontend manda el formulario completo: status viene con el mismo valor.
        $this->withoutMiddleware()->putJson('/api/products/' . self::PRODUCT, [
            'name'   => 'Camisa lino caballero',
            'status' => 'active',
        ])->assertOk();

        $this->assertSame('inactive', $this->variantStatuses()[$first->id]);
    }

    public function test_deactivating_a_single_variant_does_not_touch_the_product(): void
    {
        $first = ProductVariant::where('id_product', self::PRODUCT)->orderBy('id')->first();

        $this->withoutMiddleware()
            ->deleteJson('/api/products/' . self::PRODUCT . '/variants/' . $first->id)
            ->assertOk();

        $this->assertSame('active', Product::find(self::PRODUCT)->status);
        $this->assertCount(self::VARIANT_COUNT - 1, array_filter($this->variantStatuses(), fn($s) => $s === 'active'));
    }
}
