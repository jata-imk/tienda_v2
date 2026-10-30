<?php

namespace Tests\Feature;

use App\Models\CompanyInfo;
use App\Models\ProductVariant;
use App\Services\OrderPdfService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsPurchaseOrders;
use Tests\TestCase;

class OrderPdfTest extends TestCase
{
    use BuildsPurchaseOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootPurchaseOrders();
    }

    public function test_returns_the_pdf_inline_by_default_and_as_attachment_on_download(): void
    {
        $id = $this->draftOrder();

        $inline = $this->asAdmin()->get("/api/orders/{$id}/pdf")->assertOk();
        $this->assertSame('application/pdf', $inline->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', $inline->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $inline->getContent());

        $download = $this->asAdmin()->get("/api/orders/{$id}/pdf?download=1")->assertOk();
        $this->assertStringStartsWith('attachment', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('PED-0000001.pdf', $download->headers->get('Content-Disposition'));
    }

    public function test_unknown_order_returns_json_404(): void
    {
        $this->asAdmin()->get('/api/orders/999/pdf')->assertNotFound()->assertJsonPath('ok', false);
    }

    public function test_the_view_shows_the_order_data_and_a_draft_watermark(): void
    {
        CompanyInfo::first()->update([
            'street' => 'Calle 60', 'external_number' => '123', 'neighborhood' => 'Centro', 'city' => 'Merida', 'postal_code' => '97000',
            // PNG de 1x1 sin prefijo data-URI, como lo guarda company-info.
            'logo' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
        ]);

        $id   = $this->draftOrder();
        $html = $this->renderView($id);

        $this->assertStringContainsString('Pedido de Compra', $html);
        $this->assertStringContainsString('PED-0000001', $html);
        $this->assertStringContainsString('CAM-001', $html);
        $this->assertStringContainsString('Camisa lino caballero · Blanco', $html);
        $this->assertStringContainsString('6,000.00 MXN', $html);
        $this->assertStringContainsString('TELAS DEL SURESTE', mb_strtoupper($html));
        $this->assertStringContainsString('Col. Centro', $html);
        $this->assertStringContainsString('Calle 60 123, Centro, 97000 Merida', $html, 'La direccion de la empresa va en una linea.');
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('BORRADOR', $html);
        $this->assertStringContainsString('Fecha emisión', $html);
        $this->assertStringContainsString('Producto/Descripción', $html);
        $this->assertStringNotContainsString('Recibido</th>', $html);

        foreach (['Comprador', 'Dirección envío', 'Fecha documento', 'Artículo', 'UM</th>', 'PZA'] as $removed) {
            $this->assertStringNotContainsString($removed, $html);
        }
    }

    public function test_received_orders_show_the_received_column_and_no_watermark(): void
    {
        $id     = $this->draftOrder();
        $order  = $this->asAdmin()->postJson("/api/orders/{$id}/confirm")->json('data');
        $this->receive($id, [['detailId' => $order['details'][0]['id'], 'quantity' => 4]])->assertOk();

        $html = $this->renderView($id);

        $this->assertStringContainsString('Recibido</th>', $html);
        $this->assertStringContainsString('<th>Recib.</th>', $html);
        $this->assertStringContainsString('Recibido parcialmente', $html);
        $this->assertStringNotContainsString('BORRADOR', $html);
    }

    public function test_lines_are_grouped_by_product_and_color_with_a_size_matrix(): void
    {
        $id = $this->createOrder([
            ['idProductVariant' => $this->variant('CAM-001-38-AZM')->id, 'quantityOrdered' => 6],
            ['idProductVariant' => $this->variant('CAM-001-34-AZM')->id, 'quantityOrdered' => 3],
            ['idProductVariant' => $this->variant('CAM-001-36-AZM')->id, 'quantityOrdered' => 6, 'notes' => 'Bordado'],
            ['idProductVariant' => $this->variant('CAM-001-34-BLA')->id, 'quantityOrdered' => 2, 'unitCost' => 550],
            ['idProductVariant' => $this->variant('CAM-001-36-BLA')->id, 'quantityOrdered' => 2, 'unitCost' => 550],
        ])->assertCreated()->json('data.id');

        $groups = app(OrderPdfService::class)->viewData(app(OrderService::class)->show($id))['groups'];

        $this->assertCount(2, $groups, 'Un renglon por producto + color.');

        [$azul, $blanco] = $groups;
        $this->assertSame('Azul marino', $azul['color']);
        $this->assertCount(1, $azul['sizeRows'], 'Tres tallas caben en una sola matriz.');
        $this->assertSame(['34', '36', '38'], array_column($azul['sizeRows'][0], 'size'), 'Tallas en orden de sort_order.');
        $this->assertSame([3.0, 6.0, 6.0], array_column($azul['sizeRows'][0], 'ordered'));
        $this->assertSame(['Talla 36: Bordado'], $azul['notes']);
        $this->assertSame(15.0, $azul['ordered']);
        $this->assertSame(600.0, $azul['unitCost']);
        $this->assertEquals(9000, $azul['subtotal']);

        $this->assertSame('Blanco', $blanco['color']);
        $this->assertSame(550.0, $blanco['unitCost']);
        $this->assertEquals(2200, $blanco['subtotal']);

        $html = $this->renderView($id);
        // El producto + color se escribe una vez por grupo y cada grupo lleva su matriz.
        $this->assertSame(1, substr_count($html, 'Camisa lino caballero · Azul marino'));
        $this->assertSame(1, substr_count($html, 'Camisa lino caballero · Blanco'));
        $this->assertSame(2, substr_count($html, '<table class="sizes">'));
        $this->assertStringContainsString('Talla 36: Bordado', $html);
        $this->assertStringContainsString('550.00 MXN', $html);
        $this->assertStringContainsString('2,200.00 MXN', $html);
    }

    public function test_the_size_matrix_wraps_after_the_configured_number_of_sizes(): void
    {
        $sizes = $this->variant('CAM-001-34-BLA')->product->sizeGroup->sizes()->orderBy('sort_order')->get();
        $this->assertGreaterThan(OrderPdfService::SIZES_PER_ROW, $sizes->count(), 'El grupo de tallas del seeder alcanza para partir la matriz.');

        $base    = $this->variant('CAM-001-34-BLA');
        $details = $sizes->map(fn ($size) => [
            'idProductVariant' => ProductVariant::firstOrCreate(
                ['id_product' => $base->id_product, 'id_size' => $size->id, 'id_color' => $base->id_color],
                ['sku' => "CAM-001-T{$size->id}-BLA", 'stock' => 0, 'status' => 'active'],
            )->id,
            'quantityOrdered' => 1,
        ])->all();

        $id     = $this->createOrder($details)->assertCreated()->json('data.id');
        $groups = app(OrderPdfService::class)->viewData(app(OrderService::class)->show($id))['groups'];
        $rows   = $groups[0]['sizeRows'];

        $this->assertCount((int) ceil($sizes->count() / OrderPdfService::SIZES_PER_ROW), $rows);
        $this->assertCount(OrderPdfService::SIZES_PER_ROW, $rows[0]);
        $this->assertSame($sizes->pluck('name')->all(), array_column(array_merge(...$rows), 'size'));
    }

    public function test_orders_with_mixed_costs_from_before_the_rule_show_varios(): void
    {
        $id = $this->createOrder([
            ['idProductVariant' => $this->variant('CAM-001-34-BLA')->id, 'quantityOrdered' => 2],
            ['idProductVariant' => $this->variant('CAM-001-36-BLA')->id, 'quantityOrdered' => 2],
        ])->assertCreated()->json('data.id');

        DB::table('order_details')
            ->where('id_order', $id)
            ->where('id_product_variant', $this->variant('CAM-001-34-BLA')->id)
            ->update(['unit_cost' => 550, 'subtotal' => 1100]);

        $html = $this->renderView($id);

        $this->assertStringContainsString('Varios', $html);
        $this->assertStringContainsString('2,300.00 MXN', $html, 'El monto es la suma real de las tallas.');
    }

    private function draftOrder(): int
    {
        return $this->createOrder(
            [['idProductVariant' => $this->variant('CAM-001-34-BLA')->id, 'quantityOrdered' => 10]],
            ['supplierName' => 'Telas del Sureste', 'supplierDetails' => "Col. Centro\nRFC TSU010101AAA"],
        )->assertCreated()->json('data.id');
    }

    private function renderView(int $id): string
    {
        $order = app(OrderService::class)->show($id);

        return view('pdf.order', app(OrderPdfService::class)->viewData($order))->render();
    }
}
