<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsPurchaseOrders;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use BuildsPurchaseOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootPurchaseOrders();
    }

    public function test_creates_a_draft_in_the_base_currency_with_the_product_cost_as_default(): void
    {
        $variant = $this->variant('CAM-001-34-BLA');

        $response = $this->createOrder([
            ['idProductVariant' => $variant->id, 'quantityOrdered' => 10],
        ], [
            'supplierName' => 'Telas del Sureste',
            'dateExpected' => now()->addWeek()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.key', 'PED')
            ->assertJsonPath('data.folio', 1)
            ->assertJsonPath('data.folioLabel', 'PED-1')
            ->assertJsonPath('data.currencyCode', 'MXN')
            ->assertJsonPath('data.supplierName', 'Telas del Sureste')
            ->assertJsonPath('data.dateOrder', now()->toDateString())
            ->assertJsonPath('data.details.0.sku', 'CAM-001-34-BLA')
            ->assertJsonPath('data.details.0.quantityPending', 10);

        $this->assertEquals(1, $response->json('data.exchangeRate'));
        $this->assertEquals(600, $response->json('data.details.0.unitCost'));
        $this->assertEquals(6000, $response->json('data.details.0.subtotal'));
        $this->assertEquals(10, $response->json('data.totalItems'));
        $this->assertEquals(6000, $response->json('data.totalAmount'));
        $this->assertSame($this->admin->id, $response->json('data.idUser'));

        $this->asAdmin()->getJson('/api/orders/'.$response->json('data.id').'/history')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.previousStatus', null)
            ->assertJsonPath('data.0.newStatus', 'draft');
    }

    public function test_a_foreign_currency_order_converts_the_default_cost_and_freezes_the_rate_on_confirm(): void
    {
        $usd     = Currency::where('code', 'USD')->firstOrFail(); // 17.25
        $variant = $this->variant('CAM-001-34-BLA');

        $id = $this->createOrder([
            ['idProductVariant' => $variant->id, 'quantityOrdered' => 2],
        ], ['idCurrency' => $usd->id])->assertCreated()->json('data.id');

        $draft = $this->asAdmin()->getJson("/api/orders/{$id}")->assertOk();
        $this->assertEquals(17.25, $draft->json('data.exchangeRate'));
        $this->assertEquals(round(600 / 17.25, 2), $draft->json('data.details.0.unitCost'));
        $this->assertNull(Order::find($id)->exchange_rate, 'En draft la tasa no se congela.');

        // En draft el pedido sigue la tasa vigente de la moneda.
        $usd->update(['exchange_rate' => 18]);
        $this->assertEquals(18, $this->asAdmin()->getJson("/api/orders/{$id}")->json('data.exchangeRate'));

        $this->asAdmin()->postJson("/api/orders/{$id}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->assertEquals(18, Order::find($id)->exchange_rate);

        // Confirmado, ya no la sigue.
        $usd->update(['exchange_rate' => 20]);
        $this->assertEquals(18, $this->asAdmin()->getJson("/api/orders/{$id}")->json('data.exchangeRate'));
    }

    public function test_an_explicit_exchange_rate_and_unit_cost_are_respected(): void
    {
        $usd     = Currency::where('code', 'USD')->firstOrFail();
        $variant = $this->variant('CAM-001-34-BLA');

        $response = $this->createOrder([
            ['idProductVariant' => $variant->id, 'quantityOrdered' => 3, 'unitCost' => 30.5],
        ], ['idCurrency' => $usd->id, 'exchangeRate' => 19.1]);

        $response->assertCreated();
        $this->assertEquals(19.1, $response->json('data.exchangeRate'));
        $this->assertEquals(30.5, $response->json('data.details.0.unitCost'));
        $this->assertEquals(91.5, $response->json('data.totalAmount'));
    }

    public function test_updating_a_draft_replaces_the_lines_and_recalculates_totals(): void
    {
        $blanco34 = $this->variant('CAM-001-34-BLA');
        $blanco36 = $this->variant('CAM-001-36-BLA');
        $azul38   = $this->variant('CAM-001-38-AZM');

        $created = $this->createOrder([
            ['idProductVariant' => $blanco34->id, 'quantityOrdered' => 10],
            ['idProductVariant' => $blanco36->id, 'quantityOrdered' => 5],
        ])->assertCreated();

        $id         = $created->json('data.id');
        $keptLineId = $created->json('data.details.0.id');

        $response = $this->asAdmin()->putJson("/api/orders/{$id}", [
            'notes'   => 'Ajuste de cantidades',
            'details' => [
                ['idProductVariant' => $blanco34->id, 'quantityOrdered' => 4, 'unitCost' => 550],
                ['idProductVariant' => $azul38->id, 'quantityOrdered' => 1.5],
            ],
        ]);

        $response->assertOk()->assertJsonPath('data.notes', 'Ajuste de cantidades')->assertJsonCount(2, 'data.details');

        $this->assertEquals(5.5, $response->json('data.totalItems'));
        $this->assertEquals(4 * 550 + 1.5 * 600, $response->json('data.totalAmount'));

        $lines = collect($response->json('data.details'))->keyBy('idProductVariant');
        $this->assertSame($keptLineId, $lines[$blanco34->id]['id'], 'La linea de la misma variante conserva su id.');
        $this->assertFalse($lines->has($blanco36->id));
    }

    public function test_updating_only_the_header_keeps_the_lines(): void
    {
        $variant = $this->variant('CAM-001-34-BLA');
        $id = $this->createOrder([
            ['idProductVariant' => $variant->id, 'quantityOrdered' => 2],
        ])->json('data.id');

        $this->asAdmin()->putJson("/api/orders/{$id}", ['dateExpected' => '2026-12-01', 'supplierName' => null])
            ->assertOk()
            ->assertJsonPath('data.dateExpected', '2026-12-01')
            ->assertJsonPath('data.supplierName', null)
            ->assertJsonCount(1, 'data.details');
    }

    public function test_a_confirmed_order_can_no_longer_be_edited_or_confirmed_again(): void
    {
        $variant = $this->variant('CAM-001-34-BLA');
        $order   = $this->confirmedOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 2]]);

        $this->asAdmin()->putJson("/api/orders/{$order['id']}", ['notes' => 'x'])
            ->assertStatus(409)
            ->assertJsonPath('data.status', 'confirmed');

        $this->asAdmin()->postJson("/api/orders/{$order['id']}/confirm")->assertStatus(409);
    }

    public function test_confirming_does_not_touch_stock_or_create_movements(): void
    {
        $variant  = $this->variant('CAM-001-34-BLA');
        $before   = (float) $variant->stock;
        $movCount = InventoryMovement::count();

        $this->confirmedOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 10]]);

        $this->assertSame($before, (float) $variant->fresh()->stock);
        $this->assertSame($movCount, InventoryMovement::count());
    }

    public function test_draft_and_confirmed_orders_can_be_cancelled_and_every_transition_is_logged(): void
    {
        $variant = $this->variant('CAM-001-34-BLA');

        $draftId = $this->createOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 1]])->json('data.id');
        $this->asAdmin()->postJson("/api/orders/{$draftId}/cancel", ['notes' => 'Duplicado'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $confirmed = $this->confirmedOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 1]]);
        $this->asAdmin()->postJson("/api/orders/{$confirmed['id']}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');

        $history = $this->asAdmin()->getJson("/api/orders/{$confirmed['id']}/history")->assertOk()->json('data');
        $this->assertSame(
            [[null, 'draft'], ['draft', 'confirmed'], ['confirmed', 'cancelled']],
            array_map(fn ($h) => [$h['previousStatus'], $h['newStatus']], $history),
        );

        $draftHistory = $this->asAdmin()->getJson("/api/orders/{$draftId}/history")->json('data');
        $this->assertSame('Duplicado', end($draftHistory)['notes']);

        // `cancelled` es final.
        $this->asAdmin()->postJson("/api/orders/{$draftId}/confirm")->assertStatus(409);
        $this->asAdmin()->postJson("/api/orders/{$draftId}/cancel")->assertStatus(409);
    }

    public function test_variants_of_products_without_stock_control_are_rejected(): void
    {
        $base    = $this->variant('CAM-001-34-BLA');
        $service = Product::where('key', 'SERV-001')->firstOrFail();

        $variant = ProductVariant::create([
            'id_product' => $service->id,
            'id_size'    => $base->id_size,
            'id_color'   => $base->id_color,
            'sku'        => 'SERV-001-U',
            'stock'      => 0,
            'status'     => 'active',
        ]);

        $this->createOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 1]])
            ->assertStatus(422)
            ->assertJsonPath('data.sku', 'SERV-001-U');

        $this->assertSame(0, Order::count());

        // El folio no se consume si el alta falla: el siguiente sigue siendo 1.
        $this->createOrder([['idProductVariant' => $base->id, 'quantityOrdered' => 1]])
            ->assertCreated()
            ->assertJsonPath('data.folio', 1);
    }

    public function test_inactive_variants_and_products_are_rejected(): void
    {
        $variant = $this->variant('CAM-001-34-BLA');
        $variant->update(['status' => 'inactive']);

        $this->createOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 1]])->assertStatus(422);

        $variant->update(['status' => 'active']);
        Product::whereKey($variant->id_product)->update(['status' => 'inactive']);

        $this->createOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 1]])->assertStatus(422);
    }

    public function test_request_validation(): void
    {
        $variant = $this->variant('CAM-001-34-BLA');

        $this->createOrder([])->assertUnprocessable()->assertJsonValidationErrors('details');

        $this->createOrder([
            ['idProductVariant' => $variant->id, 'quantityOrdered' => 1],
            ['idProductVariant' => $variant->id, 'quantityOrdered' => 2],
        ])->assertUnprocessable()->assertJsonValidationErrors('details.1.idProductVariant');

        $this->createOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 0]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('details.0.quantityOrdered');

        $this->createOrder(
            [['idProductVariant' => $variant->id, 'quantityOrdered' => 1]],
            ['dateOrder' => '2026-10-15', 'dateExpected' => '2026-10-01'],
        )->assertUnprocessable()->assertJsonValidationErrors('dateExpected');
    }

    public function test_folios_are_consecutive_and_unique_per_key(): void
    {
        $variant = $this->variant('CAM-001-34-BLA');

        $folios = [];
        foreach (range(1, 3) as $_) {
            $folios[] = $this->createOrder([['idProductVariant' => $variant->id, 'quantityOrdered' => 1]])->json('data.folio');
        }

        $this->assertSame([1, 2, 3], $folios);

        $this->expectException(QueryException::class);

        Order::create([
            'key'         => 'PED',
            'folio'       => 1,
            'status'      => 'draft',
            'id_currency' => Currency::where('code', 'MXN')->value('id'),
            'date_order'  => now()->toDateString(),
            'id_user'     => $this->admin->id,
        ]);
    }

    public function test_unknown_order_returns_404(): void
    {
        $this->asAdmin()->getJson('/api/orders/999')->assertNotFound();
        $this->asAdmin()->putJson('/api/orders/999', ['notes' => 'x'])->assertNotFound();
        $this->asAdmin()->postJson('/api/orders/999/confirm')->assertNotFound();
        $this->asAdmin()->postJson('/api/orders/999/cancel')->assertNotFound();
        $this->asAdmin()->getJson('/api/orders/999/history')->assertNotFound();
    }
}
