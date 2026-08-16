<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMovementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->user = User::findOrFail(1);
    }

    private function asUser(): static
    {
        $this->actingAs($this->user, 'api');

        return $this;
    }

    public function test_registers_a_batch_of_movements_and_returns_new_and_total_stock(): void
    {
        $product = Product::where('key', 'CAM-001')->firstOrFail();
        $blanco34 = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail(); // stock 3
        $azul34   = ProductVariant::where('sku', 'CAM-001-34-AZM')->firstOrFail(); // stock 1

        $response = $this->asUser()->withoutMiddleware()->postJson('/api/inventory/movements', [
            'idProduct'      => $product->id,
            'idUser'         => $this->user->id,
            'referenceType'  => 'manual_adjustment',
            'notes'          => 'Ajuste por conteo físico',
            'movements'      => [
                ['idProductVariant' => $blanco34->id, 'movementType' => 'adjustment', 'quantity' => 2],
                ['idProductVariant' => $azul34->id,   'movementType' => 'entry',      'quantity' => 3],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonCount(2, 'data.movements')
            ->assertJsonPath('data.movements.0.idProductVariant', $blanco34->id)
            ->assertJsonPath('data.movements.0.previousStock', 3)
            ->assertJsonPath('data.movements.0.newStock', 1)
            ->assertJsonPath('data.movements.1.idProductVariant', $azul34->id)
            ->assertJsonPath('data.movements.1.previousStock', 1)
            ->assertJsonPath('data.movements.1.newStock', 4);

        $this->assertSame(1.0, (float) $blanco34->fresh()->stock);
        $this->assertSame(4.0, (float) $azul34->fresh()->stock);

        // totalStock = suma de variantes activas del producto tras el ajuste.
        $expectedTotal = ProductVariant::where('id_product', $product->id)->where('status', 'active')->sum('stock');
        $this->assertSame((float) $expectedTotal, (float) $response->json('data.totalStock'));

        $this->assertSame(2, InventoryMovement::where('reference_type', 'manual_adjustment')->count());
    }

    public function test_a_single_movement_still_returns_an_array_in_data_movements(): void
    {
        $product = Product::where('key', 'CAM-001')->firstOrFail();
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail();

        $response = $this->asUser()->withoutMiddleware()->postJson('/api/inventory/movements', [
            'idProduct' => $product->id,
            'idUser'    => $this->user->id,
            'movements' => [
                ['idProductVariant' => $variant->id, 'movementType' => 'entry', 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()->assertJsonCount(1, 'data.movements');
        $this->assertIsArray($response->json('data.movements'));
    }

    public function test_insufficient_stock_rejects_the_whole_batch(): void
    {
        $product  = Product::where('key', 'CAM-001')->firstOrFail();
        $blanco34 = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail(); // stock 3
        $azul34   = ProductVariant::where('sku', 'CAM-001-34-AZM')->firstOrFail(); // stock 1

        $response = $this->asUser()->withoutMiddleware()->postJson('/api/inventory/movements', [
            'idProduct' => $product->id,
            'idUser'    => $this->user->id,
            'movements' => [
                ['idProductVariant' => $blanco34->id, 'movementType' => 'adjustment', 'quantity' => 1],
                ['idProductVariant' => $azul34->id,   'movementType' => 'adjustment', 'quantity' => 5],
            ],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('data.idProductVariant', $azul34->id)
            ->assertJsonPath('data.currentStock', 1)
            ->assertJsonPath('data.requestedQuantity', 5);

        // Nada se aplico: ni la primera linea ni movimientos nuevos.
        $this->assertSame(3.0, (float) $blanco34->fresh()->stock);
        $this->assertSame(1.0, (float) $azul34->fresh()->stock);
        $this->assertSame(0, InventoryMovement::where('id_product_variant', $blanco34->id)
            ->where('quantity', 1)->count());
    }

    public function test_rejects_an_empty_movements_array(): void
    {
        $product = Product::where('key', 'CAM-001')->firstOrFail();

        $this->asUser()->withoutMiddleware()->postJson('/api/inventory/movements', [
            'idProduct' => $product->id,
            'idUser'    => $this->user->id,
            'movements' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors(['movements']);
    }

    public function test_rejects_a_non_positive_quantity(): void
    {
        $product = Product::where('key', 'CAM-001')->firstOrFail();
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail();

        $this->asUser()->withoutMiddleware()->postJson('/api/inventory/movements', [
            'idProduct' => $product->id,
            'idUser'    => $this->user->id,
            'movements' => [
                ['idProductVariant' => $variant->id, 'movementType' => 'entry', 'quantity' => 0],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['movements.0.quantity']);
    }

    public function test_rejects_an_invalid_movement_type(): void
    {
        $product = Product::where('key', 'CAM-001')->firstOrFail();
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail();

        $this->asUser()->withoutMiddleware()->postJson('/api/inventory/movements', [
            'idProduct' => $product->id,
            'idUser'    => $this->user->id,
            'movements' => [
                ['idProductVariant' => $variant->id, 'movementType' => 'bogus', 'quantity' => 1],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['movements.0.movementType']);
    }

    public function test_rejects_a_repeated_variant_in_the_same_batch(): void
    {
        $product = Product::where('key', 'CAM-001')->firstOrFail();
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail();

        $this->asUser()->withoutMiddleware()->postJson('/api/inventory/movements', [
            'idProduct' => $product->id,
            'idUser'    => $this->user->id,
            'movements' => [
                ['idProductVariant' => $variant->id, 'movementType' => 'entry', 'quantity' => 1],
                ['idProductVariant' => $variant->id, 'movementType' => 'adjustment', 'quantity' => 1],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['movements.0.idProductVariant']);
    }

    public function test_rejects_a_variant_that_belongs_to_another_product(): void
    {
        $product        = Product::where('key', 'CAM-001')->firstOrFail();
        $otherVariant   = ProductVariant::where('sku', 'CAM-001-36-BLA')->firstOrFail();

        // Producto distinto sin control de existencias tambien sirve para
        // probar la pertenencia, pero usamos otro con stockControl=true
        // creando una variante suelta para aislar exactamente esta regla.
        $otherProduct = Product::create([
            'id_size_group' => 1,
            'key'           => 'OTRO-001',
            'name'          => 'Otro producto',
            'price'         => 100,
            'cost'          => 50,
            'stock_control' => true,
            'type_iva'      => 1,
            'rate_iva'      => 16,
            'status'        => 'active',
        ]);

        $response = $this->asUser()->withoutMiddleware()->postJson('/api/inventory/movements', [
            'idProduct' => $otherProduct->id,
            'idUser'    => $this->user->id,
            'movements' => [
                ['idProductVariant' => $otherVariant->id, 'movementType' => 'entry', 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('no pertenece al producto', $response->json('message'));
    }

    public function test_rejects_a_product_without_stock_control(): void
    {
        $service = Product::where('key', 'SERV-001')->firstOrFail();
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail();

        $response = $this->asUser()->withoutMiddleware()->postJson('/api/inventory/movements', [
            'idProduct' => $service->id,
            'idUser'    => $this->user->id,
            'movements' => [
                ['idProductVariant' => $variant->id, 'movementType' => 'entry', 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('no maneja existencias', $response->json('message'));
    }

    public function test_rejects_when_id_user_does_not_match_the_session(): void
    {
        $product = Product::where('key', 'CAM-001')->firstOrFail();
        $variant = ProductVariant::where('sku', 'CAM-001-34-BLA')->firstOrFail();

        $otherUser = User::create([
            'id_user_type' => 1,
            'first_name'   => 'Otro',
            'last_name'    => 'Usuario',
            'user_name'    => 'otro.usuario',
            'email'        => 'otro@example.com',
            'password'     => bcrypt('secret'),
            'status'       => 'active',
        ]);

        $this->asUser()->withoutMiddleware()->postJson('/api/inventory/movements', [
            'idProduct' => $product->id,
            'idUser'    => $otherUser->id,
            'movements' => [
                ['idProductVariant' => $variant->id, 'movementType' => 'entry', 'quantity' => 1],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['idUser']);
    }
}
