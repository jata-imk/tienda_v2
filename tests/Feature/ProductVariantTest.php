<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Alta incremental de variantes sobre un producto existente.
 *
 * Datos del seeder: producto 1 (CAM-001, stockControl, grupo de tallas 1) con
 * seis variantes en los colores 1 (Blanco) y 2 (Azul marino). El color 3
 * (Beige) esta libre. Producto 2 (SERV-001) no maneja existencias.
 */
class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    private const SIZE_34 = 2;
    private const SIZE_36 = 3;
    private const COLOR_BEIGE = 3;
    private const KIDS_SIZE = 15; // Grupo 2 (Ninos): no pertenece al producto 1.

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_store_adds_a_new_color_to_an_existing_product(): void
    {
        $before = Product::with('variants')->find(1)->total_stock;

        $response = $this->withoutMiddleware()->postJson('/api/products/1/variants', [
            'variants' => [
                ['idSize' => self::SIZE_34, 'idColor' => self::COLOR_BEIGE, 'sku' => 'CAM-001-34-BEI', 'stock' => 3],
                ['idSize' => self::SIZE_36, 'idColor' => self::COLOR_BEIGE, 'sku' => 'CAM-001-36-BEI', 'stock' => 2],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonCount(2, 'data.items');
        $response->assertJsonPath('data.totalStock', fn($value) => (float) $value === $before + 5.0);

        $this->assertDatabaseHas('product_variants', [
            'id_product' => 1,
            'id_size'    => self::SIZE_34,
            'id_color'   => self::COLOR_BEIGE,
            'sku'        => 'CAM-001-34-BEI',
            'status'     => 'active',
        ]);

        $this->assertCount(2, ProductVariant::where('id_product', 1)->where('id_color', self::COLOR_BEIGE)->get());
    }

    public function test_store_without_initial_movement_creates_no_movements(): void
    {
        $before = InventoryMovement::count();

        $this->withoutMiddleware()->postJson('/api/products/1/variants', [
            'variants' => [
                ['idSize' => self::SIZE_34, 'idColor' => self::COLOR_BEIGE, 'sku' => 'CAM-001-34-BEI', 'stock' => 3],
            ],
        ])->assertCreated();

        $this->assertSame($before, InventoryMovement::count());
    }

    public function test_store_with_initial_movement_creates_one_movement_per_variant_with_stock(): void
    {
        $this->withoutMiddleware()->postJson('/api/products/1/variants', [
            'variants' => [
                ['idSize' => self::SIZE_34, 'idColor' => self::COLOR_BEIGE, 'sku' => 'CAM-001-34-BEI', 'stock' => 3],
                ['idSize' => self::SIZE_36, 'idColor' => self::COLOR_BEIGE, 'sku' => 'CAM-001-36-BEI', 'stock' => 0],
            ],
            'initialMovement' => [
                'movementType'  => 'entry',
                'referenceType' => 'initial_load',
                'notes'         => 'Alta de color Beige',
                'idUser'        => 1,
            ],
        ])->assertCreated();

        $withStock = ProductVariant::where('sku', 'CAM-001-34-BEI')->first();
        $noStock   = ProductVariant::where('sku', 'CAM-001-36-BEI')->first();

        $this->assertDatabaseHas('inventory_movements', [
            'id_product_variant' => $withStock->id,
            'movement_type'      => 'entry',
            'quantity'           => 3,
            'previous_stock'     => 0,
            'new_stock'          => 3,
            'reference_type'     => 'initial_load',
            'id_user'            => 1,
        ]);

        // La variante con stock 0 no genera movimiento.
        $this->assertSame(0, InventoryMovement::where('id_product_variant', $noStock->id)->count());
    }

    public function test_store_rejects_a_combination_that_already_exists(): void
    {
        // Talla 34 + Blanco ya existe en el seeder (CAM-001-34-BLA).
        $response = $this->withoutMiddleware()->postJson('/api/products/1/variants', [
            'variants' => [
                ['idSize' => self::SIZE_34, 'idColor' => 1, 'sku' => 'CAM-001-34-BLA-BIS', 'stock' => 1],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('variants.0.idColor');
    }

    public function test_store_rejects_a_size_outside_the_product_size_group(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products/1/variants', [
            'variants' => [
                ['idSize' => self::KIDS_SIZE, 'idColor' => self::COLOR_BEIGE, 'sku' => 'CAM-001-KID-BEI', 'stock' => 1],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('variants.0.idSize');
    }

    public function test_store_rejects_a_duplicated_sku(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products/1/variants', [
            'variants' => [
                ['idSize' => self::SIZE_34, 'idColor' => self::COLOR_BEIGE, 'sku' => 'CAM-001-34-BLA', 'stock' => 1],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('variants.0.sku');
    }

    public function test_store_rejects_a_product_without_stock_control(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/products/2/variants', [
            'variants' => [
                ['idSize' => self::SIZE_34, 'idColor' => self::COLOR_BEIGE, 'sku' => 'SERV-001-34-BEI', 'stock' => 1],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('variants');
    }

    public function test_store_returns_404_for_an_unknown_product(): void
    {
        $this->withoutMiddleware()->postJson('/api/products/9999/variants', [
            'variants' => [
                ['idSize' => self::SIZE_34, 'idColor' => self::COLOR_BEIGE, 'sku' => 'X-34-BEI', 'stock' => 1],
            ],
        ])->assertNotFound();
    }

    public function test_update_changes_sku_and_status(): void
    {
        $variant = ProductVariant::where('sku', 'CAM-001-36-AZM')->first();

        $response = $this->withoutMiddleware()->putJson("/api/products/1/variants/{$variant->id}", [
            'sku'    => 'CAM-001-36-AZM-V2',
            'status' => 'inactive',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.sku', 'CAM-001-36-AZM-V2');
        $response->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('product_variants', [
            'id'     => $variant->id,
            'sku'    => 'CAM-001-36-AZM-V2',
            'status' => 'inactive',
        ]);
    }

    public function test_update_returns_404_when_the_variant_belongs_to_another_product(): void
    {
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->first();

        $this->withoutMiddleware()
            ->putJson("/api/products/2/variants/{$variant->id}", ['status' => 'inactive'])
            ->assertNotFound();
    }

    public function test_destroy_deactivates_the_variant_and_drops_it_from_total_stock(): void
    {
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->first(); // stock 3
        $before  = Product::with('variants')->find(1)->total_stock;

        $this->withoutMiddleware()
            ->deleteJson("/api/products/1/variants/{$variant->id}")
            ->assertOk();

        // La fila sigue existiendo, solo cambia el status.
        $this->assertDatabaseHas('product_variants', [
            'id'     => $variant->id,
            'status' => 'inactive',
        ]);

        $this->assertSame($before - 3, Product::with('variants')->find(1)->total_stock);
    }

    public function test_color_images_work_for_a_color_added_after_creation(): void
    {
        Storage::fake('public');

        // Antes de agregar la variante, el color 3 no pertenece al producto.
        $this->withoutMiddleware()
            ->post('/api/products/1/colors/' . self::COLOR_BEIGE . '/images', [
                'images' => [UploadedFile::fake()->image('beige.png', 300, 300)],
            ])
            ->assertNotFound();

        $this->withoutMiddleware()->postJson('/api/products/1/variants', [
            'variants' => [
                ['idSize' => self::SIZE_34, 'idColor' => self::COLOR_BEIGE, 'sku' => 'CAM-001-34-BEI', 'stock' => 1],
            ],
        ])->assertCreated();

        $this->withoutMiddleware()
            ->post('/api/products/1/colors/' . self::COLOR_BEIGE . '/images', [
                'images' => [UploadedFile::fake()->image('beige.png', 300, 300)],
            ])
            ->assertCreated();
    }
}
