<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsPurchaseOrders;
use Tests\TestCase;

class OrderReceiveTest extends TestCase
{
    use BuildsPurchaseOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootPurchaseOrders();
    }

    public function test_a_partial_receipt_updates_the_line_the_stock_and_logs_a_purchase_movement(): void
    {
        $variant = $this->variant('CAM-001-34-BLA'); // stock 3
        $order   = $this->confirmedOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 10]]);
        $detail  = $order['details'][0];

        $response = $this->receive($order['id'], [['detailId' => $detail['id'], 'quantity' => 4]], 'Remesa 1');

        $response->assertOk()
            ->assertJsonPath('data.order.status', 'partially_received')
            ->assertJsonPath('data.order.dateReceived', null)
            ->assertJsonCount(1, 'data.movements')
            ->assertJsonPath('data.movements.0.movementType', 'purchase')
            ->assertJsonPath('data.movements.0.referenceType', 'order_detail')
            ->assertJsonPath('data.movements.0.referenceId', $detail['id']);

        $this->assertEquals(4, $response->json('data.order.details.0.quantityReceived'));
        $this->assertEquals(6, $response->json('data.order.details.0.quantityPending'));
        $this->assertSame(7.0, (float) $variant->fresh()->stock);

        $movement = InventoryMovement::where('reference_type', 'order_detail')->sole();
        $this->assertSame('purchase', $movement->movement_type);
        $this->assertSame($detail['id'], (int) $movement->reference_id);
        $this->assertSame(3.0, (float) $movement->previous_stock);
        $this->assertSame(7.0, (float) $movement->new_stock);
        $this->assertStringContainsString('PED-0000001', $movement->notes);
        $this->assertStringContainsString('Remesa 1', $movement->notes);

        $history = $this->asAdmin()->getJson("/api/orders/{$order['id']}/history")->json('data');
        $this->assertSame('partially_received', end($history)['newStatus']);
    }

    public function test_receiving_the_rest_completes_the_order(): void
    {
        $variant = $this->variant('CAM-001-34-BLA');
        $order   = $this->confirmedOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 10]]);
        $lineId  = $order['details'][0]['id'];

        $this->receive($order['id'], [['detailId' => $lineId, 'quantity' => 4]])->assertOk();
        $this->receive($order['id'], [['detailId' => $lineId, 'quantity' => 6]])
            ->assertOk()
            ->assertJsonPath('data.order.status', 'received');

        $this->assertNotNull(Order::find($order['id'])->date_received);
        $this->assertSame(13.0, (float) $variant->fresh()->stock);
        $this->assertSame(2, InventoryMovement::where('movement_type', 'purchase')->count());

        $statuses = array_column($this->asAdmin()->getJson("/api/orders/{$order['id']}/history")->json('data'), 'newStatus');
        $this->assertSame(['draft', 'confirmed', 'partially_received', 'received'], $statuses);

        // `received` es final.
        $this->receive($order['id'], [['detailId' => $lineId, 'quantity' => 1]])->assertStatus(409);
        $this->asAdmin()->postJson("/api/orders/{$order['id']}/cancel")->assertStatus(409);
    }

    public function test_a_full_receipt_goes_straight_from_confirmed_to_received(): void
    {
        $variant = $this->variant('CAM-001-34-BLA');
        $order   = $this->confirmedOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 2.5]]);

        $this->receive($order['id'], [['detailId' => $order['details'][0]['id'], 'quantity' => 2.5]])
            ->assertOk()
            ->assertJsonPath('data.order.status', 'received');
    }

    public function test_a_receipt_across_two_products_moves_both_in_one_call(): void
    {
        $camisa  = $this->variant('CAM-001-34-BLA');
        $other   = $this->secondProductVariant();

        $order = $this->confirmedOrder([
            ['idProductVariant' => $other->id, 'quantityOrdered' => 5],
            ['idProductVariant' => $camisa->id, 'quantityOrdered' => 5],
        ]);

        $lines = collect($order['details'])->keyBy('idProductVariant');

        $this->receive($order['id'], [
            ['detailId' => $lines[$other->id]['id'], 'quantity' => 5],
            ['detailId' => $lines[$camisa->id]['id'], 'quantity' => 5],
        ])->assertOk()
            ->assertJsonPath('data.order.status', 'received')
            ->assertJsonCount(2, 'data.movements');

        $this->assertSame(5.0, (float) $other->fresh()->stock);
        $this->assertSame(8.0, (float) $camisa->fresh()->stock);

        foreach ([$other, $camisa] as $variant) {
            $movement = InventoryMovement::where('id_product_variant', $variant->id)->where('movement_type', 'purchase')->sole();
            $this->assertSame($lines[$variant->id]['id'], (int) $movement->reference_id);
        }
    }

    public function test_receiving_more_than_pending_is_rejected_and_rolls_back_the_whole_batch(): void
    {
        $blanco = $this->variant('CAM-001-34-BLA');
        $azul   = $this->variant('CAM-001-34-AZM');
        $order  = $this->confirmedOrder([
            ['idProductVariant' => $blanco->id, 'quantityOrdered' => 10],
            ['idProductVariant' => $azul->id, 'quantityOrdered' => 2],
        ]);

        $lines = collect($order['details'])->keyBy('idProductVariant');

        $this->receive($order['id'], [
            ['detailId' => $lines[$blanco->id]['id'], 'quantity' => 5],
            ['detailId' => $lines[$azul->id]['id'], 'quantity' => 3],
        ])->assertStatus(422)
            ->assertJsonPath('data.detailId', $lines[$azul->id]['id'])
            ->assertJsonPath('data.requestedQuantity', 3);

        $this->assertSame(3.0, (float) $blanco->fresh()->stock);
        $this->assertSame(0, InventoryMovement::where('movement_type', 'purchase')->count());
        $this->assertSame(0.0, (float) OrderDetail::find($lines[$blanco->id]['id'])->quantity_received);
        $this->assertSame('confirmed', Order::find($order['id'])->status->value);
    }

    public function test_a_failure_inside_the_inventory_service_rolls_back_the_earlier_products(): void
    {
        $camisa = $this->variant('CAM-001-34-BLA');
        $other  = $this->secondProductVariant();
        $order  = $this->confirmedOrder([
            ['idProductVariant' => $camisa->id, 'quantityOrdered' => 5],
            ['idProductVariant' => $other->id, 'quantityOrdered' => 5],
        ]);

        // El segundo producto (mayor id, se procesa despues) deja de manejar
        // existencias tras confirmar: register() lo rechaza.
        Product::whereKey($other->id_product)->update(['stock_control' => false]);

        $lines = collect($order['details'])->keyBy('idProductVariant');

        $this->receive($order['id'], [
            ['detailId' => $lines[$camisa->id]['id'], 'quantity' => 5],
            ['detailId' => $lines[$other->id]['id'], 'quantity' => 5],
        ])->assertStatus(422);

        $this->assertSame(3.0, (float) $camisa->fresh()->stock);
        $this->assertSame(0, InventoryMovement::where('movement_type', 'purchase')->count());
    }

    public function test_receiving_a_draft_is_a_conflict(): void
    {
        $variant = $this->variant('CAM-001-34-BLA');
        $created = $this->createOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 1]])->json('data');

        $this->receive($created['id'], [['detailId' => $created['details'][0]['id'], 'quantity' => 1]])->assertStatus(409);
    }

    public function test_a_line_from_another_order_is_rejected(): void
    {
        $variant = $this->variant('CAM-001-34-BLA');
        $first   = $this->confirmedOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 1]]);
        $second  = $this->confirmedOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 1]]);

        $this->receive($first['id'], [['detailId' => $second['details'][0]['id'], 'quantity' => 1]])
            ->assertStatus(422)
            ->assertJsonPath('data.detailId', $second['details'][0]['id']);
    }

    public function test_a_partially_received_order_cannot_be_cancelled(): void
    {
        $variant = $this->variant('CAM-001-34-BLA');
        $order   = $this->confirmedOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 10]]);

        $this->receive($order['id'], [['detailId' => $order['details'][0]['id'], 'quantity' => 1]])->assertOk();

        $this->asAdmin()->postJson("/api/orders/{$order['id']}/cancel")->assertStatus(409);
        $this->assertSame('partially_received', Order::find($order['id'])->status->value);
    }

    /**
     * Sin MariaDB no hay dos conexiones reales compitiendo; se simula el caso
     * con dos recepciones secuenciales: la segunda ve lo que la primera dejo
     * (la fila de la linea se relee bajo bloqueo) y no puede pasarse.
     */
    public function test_a_second_receipt_sees_the_first_and_cannot_exceed_the_pending_quantity(): void
    {
        $variant = $this->variant('CAM-001-34-BLA');
        $order   = $this->confirmedOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 10]]);
        $lineId  = $order['details'][0]['id'];

        $this->receive($order['id'], [['detailId' => $lineId, 'quantity' => 7]])->assertOk();
        $this->receive($order['id'], [['detailId' => $lineId, 'quantity' => 5]])
            ->assertStatus(422)
            ->assertJsonPath('data.quantityReceived', 7)
            ->assertJsonPath('data.requestedQuantity', 5);

        $this->assertSame(10.0, (float) $variant->fresh()->stock);
        $this->assertSame(7.0, (float) OrderDetail::find($lineId)->quantity_received);
    }

    public function test_receive_request_validation(): void
    {
        $variant = $this->variant('CAM-001-34-BLA');
        $order   = $this->confirmedOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 10]]);
        $lineId  = $order['details'][0]['id'];

        $this->receive($order['id'], [])->assertUnprocessable()->assertJsonValidationErrors('lines');
        $this->receive($order['id'], [['detailId' => $lineId, 'quantity' => 0]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines.0.quantity');
        $this->receive($order['id'], [
            ['detailId' => $lineId, 'quantity' => 1],
            ['detailId' => $lineId, 'quantity' => 1],
        ])->assertUnprocessable()->assertJsonValidationErrors('lines.1.detailId');

        $this->receive(999, [['detailId' => $lineId, 'quantity' => 1]])->assertNotFound();
    }

    public function test_the_kardex_links_purchase_movements_back_to_their_order(): void
    {
        $variant = $this->variant('CAM-001-34-BLA');
        $order   = $this->confirmedOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 10]]);
        $other   = $this->confirmedOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 10]]);

        $this->receive($order['id'], [['detailId' => $order['details'][0]['id'], 'quantity' => 2]])->assertOk();
        $this->receive($other['id'], [['detailId' => $other['details'][0]['id'], 'quantity' => 3]])->assertOk();

        $byOrder = $this->asAdmin()->postJson('/api/inventory/movements/query', [
            'w' => [['f' => 'idOrder', 'ao' => '==', 'v' => $order['id']]],
        ])->assertOk();

        $byOrder->assertJsonPath('data.totalCount', 1)
            ->assertJsonPath('data.items.0.movementType', 'purchase')
            ->assertJsonPath('data.items.0.idOrder', $order['id'])
            ->assertJsonPath('data.items.0.orderFolio', 'PED-0000001');

        $bySearch = $this->asAdmin()->postJson('/api/inventory/movements/query', [
            'w' => [['f' => 'search', 'ao' => 'contains', 'v' => 'PED-0000002']],
        ])->assertOk();

        $this->assertSame([$other['id']], array_values(array_unique(array_column($bySearch->json('data.items'), 'idOrder'))));

        $byType = $this->asAdmin()->postJson('/api/inventory/movements/query', [
            'w' => [['f' => 'movementType', 'ao' => '==', 'v' => 'purchase']],
        ])->assertOk();

        $byType->assertJsonPath('data.totalCount', 2);
    }

    private function secondProductVariant(): ProductVariant
    {
        $base = $this->variant('CAM-001-34-BLA');

        $product = Product::create([
            'id_size_group' => Product::find($base->id_product)->id_size_group,
            'key'           => 'PAN-001',
            'name'          => 'Pantalon lino',
            'price'         => 900,
            'cost'          => 700,
            'stock_control' => true,
            'type_iva'      => 1,
            'status'        => 'active',
        ]);
        $product->categories()->sync([Category::firstOrFail()->id]);

        return ProductVariant::create([
            'id_product' => $product->id,
            'id_size'    => $base->id_size,
            'id_color'   => $base->id_color,
            'sku'        => 'PAN-001-34-BLA',
            'stock'      => 0,
            'status'     => 'active',
        ]);
    }
}
