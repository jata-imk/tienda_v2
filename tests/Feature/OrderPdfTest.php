<?php

namespace Tests\Feature;

use App\Models\CompanyInfo;
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
        $this->assertStringContainsString('<td class="label">Clave</td>', $html);
        $this->assertStringContainsString('Costo Unitario</th>', $html);
        $this->assertStringContainsString('Producto/Descripción', $html);
        $this->assertStringNotContainsString('10 · CAM-001', $html, 'Sin numero de posicion.');
        $this->assertStringNotContainsString('Recibido</th>', $html);

        foreach (['Comprador', 'Dirección envío', 'Fecha documento', 'Número documento', 'Precio unitario', 'Artículo', 'UM</th>', 'PZA'] as $removed) {
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
        $this->assertStringContainsString('Recibido parcialmente', $html);
        $this->assertStringNotContainsString('BORRADOR', $html);
    }

    public function test_lines_are_grouped_by_product_and_color_with_one_row_per_size(): void
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
        $this->assertSame(['34', '36', '38'], array_column($azul['sizes'], 'size'), 'Tallas en orden de sort_order.');
        $this->assertSame([3.0, 6.0, 6.0], array_column($azul['sizes'], 'ordered'));
        $this->assertEquals([1800, 3600, 3600], array_column($azul['sizes'], 'subtotal'));
        $this->assertSame(['Talla 36: Bordado'], $azul['notes']);
        $this->assertSame(15.0, $azul['ordered']);
        $this->assertSame(600.0, $azul['unitCost']);
        $this->assertEquals(9000, $azul['subtotal']);

        $this->assertSame('Blanco', $blanco['color']);
        $this->assertSame(550.0, $blanco['unitCost']);
        $this->assertEquals(2200, $blanco['subtotal']);

        $html = $this->renderView($id);
        // Producto, descripcion y precio una vez por grupo; un renglon por talla.
        $this->assertSame(1, substr_count($html, 'Camisa lino caballero · Azul marino'));
        $this->assertSame(1, substr_count($html, 'Camisa lino caballero · Blanco'));
        $this->assertSame(2, substr_count($html, 'rowspan="3"'));
        $this->assertSame(2, substr_count($html, 'rowspan="2"'));
        $this->assertSame(5, substr_count($html, '<td class="size">'), 'Un renglon por talla.');
        $this->assertStringContainsString('Talla 36: Bordado', $html);
        $this->assertSame(1, substr_count($html, '550.00 MXN'), 'El precio unitario sale una vez por grupo.');
        $this->assertSame(2, substr_count($html, '1,100.00 MXN'));
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
        $this->assertStringContainsString('1,100.00 MXN', $html, 'Cada talla conserva su costo total real.');
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
