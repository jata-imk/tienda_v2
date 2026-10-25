<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SizeGroup;
use App\Support\VariantSku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renombrado opcional en cascada (`cascadeKeys`) y su vista previa (`dryRun`).
 *
 * Datos del seeder: categoria 1 `CAM` con el producto CAM-001, cuyos SKU
 * (`CAM-001-34-BLA`...) son manuales: no siguen la regla y nunca se renombran.
 * Grupos de tallas 1 Adultos (sin clave) y 2 Niños (`NIN`). Talla 2 = 34,
 * talla 15 = 1 (Niños).
 */
class KeyRenameCascadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_renamed_sku_keeps_the_collision_suffix_and_ignores_manual_skus(): void
    {
        $this->assertSame('CAM101-S2-C1-2', VariantSku::renamed('CAM001-S2-C1-2', 'CAM-001', 'CAM-101', 2, 1));
        $this->assertSame('CAM101-S2-C1', VariantSku::renamed('cam001-s2-c1', 'CAM-001', 'CAM-101', 2, 1));
        $this->assertNull(VariantSku::renamed('CAM-001-34-BLA', 'CAM-001', 'CAM-101', 2, 1));
        $this->assertNull(VariantSku::renamed('CAM001-S2-C1', 'CAM-001', 'CAM-101', 3, 1));
    }

    public function test_product_key_change_without_cascade_keeps_the_skus(): void
    {
        $id = $this->makeProduct();

        $this->withoutMiddleware()
            ->putJson("/api/products/{$id}", ['key' => 'CAM-102'])
            ->assertOk()
            ->assertJsonPath('data.key', 'CAM-102')
            ->assertJsonPath('data.variants.0.sku', 'CAM002-S2-C1');
    }

    public function test_product_cascade_renames_rule_skus_only(): void
    {
        $id = $this->makeProduct(manualSku: 'MI-SKU');

        $this->withoutMiddleware()
            ->putJson("/api/products/{$id}", ['key' => 'cam-102', 'cascadeKeys' => true])
            ->assertOk()
            ->assertJsonPath('data.key', 'CAM-102');

        $this->assertEqualsCanonicalizing(
            ['CAM102-S2-C1', 'MI-SKU'],
            ProductVariant::where('id_product', $id)->pluck('sku')->all(),
        );
    }

    public function test_dry_run_previews_without_saving(): void
    {
        $id = $this->makeProduct(manualSku: 'MI-SKU');

        $this->withoutMiddleware()
            ->putJson("/api/products/{$id}", ['key' => 'CAM-102', 'dryRun' => true, 'name' => 'No se guarda'])
            ->assertOk()
            ->assertJsonPath('message', 'Key rename preview.')
            ->assertJsonPath('data.totals', ['products' => 1, 'variants' => 1, 'conflicts' => 0])
            ->assertJsonPath('data.products.0', ['id' => $id, 'from' => 'CAM-002', 'to' => 'CAM-102'])
            ->assertJsonPath('data.variants.0.from', 'CAM002-S2-C1')
            ->assertJsonPath('data.variants.0.to', 'CAM102-S2-C1');

        $product = Product::findOrFail($id);

        $this->assertSame('CAM-002', $product->key);
        $this->assertSame('Producto de prueba', $product->name);

        $this->withoutMiddleware()
            ->putJson('/api/products/9999', ['key' => 'X-1', 'dryRun' => true])
            ->assertNotFound();
    }

    public function test_category_cascade_renames_its_series_and_skus(): void
    {
        $adult = $this->makeProduct();
        $kids  = $this->makeProduct(sizeGroup: 2, sizeId: 15);
        $other = $this->makeProduct(key: 'ESPECIAL-1');

        $this->withoutMiddleware()
            ->putJson('/api/categories/1', ['key' => 'cms', 'cascadeKeys' => true])
            ->assertOk()
            ->assertJsonPath('data.key', 'CMS');

        $this->assertSame('CMS-001', Product::where('id', 1)->value('key'));
        $this->assertSame('CMS-002', Product::find($adult)->key);
        $this->assertSame('CMS-NIN-001', Product::find($kids)->key);
        $this->assertSame('ESPECIAL-1', Product::find($other)->key);

        $this->assertSame(['CMS002-S2-C1'], ProductVariant::where('id_product', $adult)->pluck('sku')->all());
        $this->assertSame(['CMSNIN001-S15-C1'], ProductVariant::where('id_product', $kids)->pluck('sku')->all());

        // CAM-001 conserva sus SKU manuales del seeder.
        $this->assertTrue(ProductVariant::where('id_product', 1)->where('sku', 'CAM-001-34-BLA')->exists());
    }

    public function test_category_cascade_leaves_manual_keys_alone(): void
    {
        $manual = $this->makeProduct(key: 'CAM-ESPECIAL-VERANO');

        $this->withoutMiddleware()
            ->putJson('/api/categories/1', ['key' => 'CMS', 'cascadeKeys' => true])
            ->assertOk();

        $this->assertSame('CAM-ESPECIAL-VERANO', Product::find($manual)->key);
        $this->assertSame('CMS-001', Product::where('id', 1)->value('key'));
    }

    public function test_values_swapped_inside_the_plan_do_not_collide(): void
    {
        // `CAM`->`CA`: el SKU nuevo de CAM-M-01 (`CAM01-S2-C1`) es el actual de
        // CAM-01. Se crea primero CAM-M-01 para que su variante vaya antes.
        $first  = $this->makeProduct(key: 'CAM-M-01');
        $second = $this->makeProduct(key: 'CAM-01');

        $this->assertSame(['CAMM01-S2-C1'], ProductVariant::where('id_product', $first)->pluck('sku')->all());
        $this->assertSame(['CAM01-S2-C1'], ProductVariant::where('id_product', $second)->pluck('sku')->all());

        $this->withoutMiddleware()
            ->putJson('/api/categories/1', ['key' => 'CA', 'cascadeKeys' => true])
            ->assertOk();

        $this->assertSame('CA-M-01', Product::find($first)->key);
        $this->assertSame('CA-01', Product::find($second)->key);
        $this->assertSame(['CAM01-S2-C1'], ProductVariant::where('id_product', $first)->pluck('sku')->all());
        $this->assertSame(['CA01-S2-C1'], ProductVariant::where('id_product', $second)->pluck('sku')->all());
    }

    public function test_size_group_cascade_replaces_the_segment(): void
    {
        $kids = $this->makeProduct(sizeGroup: 2, sizeId: 15);

        $this->withoutMiddleware()
            ->putJson('/api/size-groups/2', ['key' => 'INF', 'dryRun' => true])
            ->assertOk()
            ->assertJsonPath('data.products.0.to', 'CAM-INF-001')
            ->assertJsonPath('data.variants.0.to', 'CAMINF001-S15-C1');

        $this->withoutMiddleware()
            ->putJson('/api/size-groups/2', ['key' => 'inf', 'cascadeKeys' => true])
            ->assertOk()
            ->assertJsonPath('data.key', 'INF');

        $this->assertSame('CAM-INF-001', Product::find($kids)->key);
        $this->assertSame(['CAMINF001-S15-C1'], ProductVariant::where('id_product', $kids)->pluck('sku')->all());
    }

    public function test_conflicts_abort_the_whole_update(): void
    {
        // Quitar `NIN` dejaria CAM-NIN-001 como CAM-001, que ya existe.
        $kids = $this->makeProduct(sizeGroup: 2, sizeId: 15);

        $this->withoutMiddleware()
            ->putJson('/api/size-groups/2', ['key' => null, 'dryRun' => true])
            ->assertOk()
            ->assertJsonPath('data.totals.conflicts', 1)
            ->assertJsonPath('data.conflicts.0', ['type' => 'product', 'key' => 'CAM-001']);

        $this->withoutMiddleware()
            ->putJson('/api/size-groups/2', ['key' => null, 'name' => 'Infantil', 'cascadeKeys' => true])
            ->assertUnprocessable()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('data.conflicts.0.key', 'CAM-001');

        $group = SizeGroup::findOrFail(2);

        $this->assertSame('NIN', $group->key);
        $this->assertSame('Niños', $group->name);
        $this->assertSame('CAM-NIN-001', Product::find($kids)->key);
    }

    public function test_sku_conflicts_are_reported(): void
    {
        $id = $this->makeProduct();

        // Otra variante ya usa el SKU que le tocaria tras renombrar.
        ProductVariant::where('id_product', 1)->where('sku', 'CAM-001-34-BLA')->update(['sku' => 'CAM102-S2-C1']);

        $this->withoutMiddleware()
            ->putJson("/api/products/{$id}", ['key' => 'CAM-102', 'cascadeKeys' => true])
            ->assertUnprocessable()
            ->assertJsonPath('data.conflicts.0', ['type' => 'variant', 'key' => 'CAM102-S2-C1']);

        $this->assertSame('CAM-002', Product::find($id)->key);
    }

    public function test_group_without_previous_key_renames_nothing(): void
    {
        $this->makeProduct();

        $this->withoutMiddleware()
            ->putJson('/api/size-groups/1', ['key' => 'ADU', 'dryRun' => true])
            ->assertOk()
            ->assertJsonPath('data.totals', ['products' => 0, 'variants' => 0, 'conflicts' => 0]);
    }

    private function makeProduct(?string $key = null, int $sizeGroup = 1, int $sizeId = 2, ?string $manualSku = null): int
    {
        $variants = [['idSize' => $sizeId, 'idColor' => 1, 'stock' => 1]];

        if ($manualSku !== null) {
            $variants[] = ['idSize' => $sizeId, 'idColor' => 2, 'sku' => $manualSku, 'stock' => 1];
        }

        return $this->withoutMiddleware()
            ->postJson('/api/products', array_filter([
                'categories'   => [1],
                'idSizeGroup'  => $sizeGroup,
                'key'          => $key,
                'name'         => 'Producto de prueba',
                'price'        => 100,
                'cost'         => 50,
                'stockControl' => true,
                'typeIva'      => 1,
                'variants'     => $variants,
            ], fn($v) => $v !== null))
            ->assertCreated()
            ->json('data.id');
    }
}
