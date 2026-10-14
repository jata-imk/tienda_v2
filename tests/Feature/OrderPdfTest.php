<?php

namespace Tests\Feature;

use App\Models\CompanyInfo;
use App\Services\OrderPdfService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertStringContainsString('PED-1.pdf', $download->headers->get('Content-Disposition'));
    }

    public function test_unknown_order_returns_json_404(): void
    {
        $this->asAdmin()->get('/api/orders/999/pdf')->assertNotFound()->assertJsonPath('ok', false);
    }

    public function test_the_view_shows_the_order_data_and_a_draft_watermark(): void
    {
        CompanyInfo::first()->update([
            'street' => 'Calle 60', 'external_number' => '123', 'city' => 'Merida', 'postal_code' => '97000',
            // PNG de 1x1 sin prefijo data-URI, como lo guarda company-info.
            'logo' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
        ]);

        $id   = $this->draftOrder();
        $html = $this->renderView($id);

        $this->assertStringContainsString('Pedido de Compra', $html);
        $this->assertStringContainsString('PED-1', $html);
        $this->assertStringContainsString('CAM-001-34-BLA', $html);
        $this->assertStringContainsString('6,000.00 MXN', $html);
        $this->assertStringContainsString('TELAS DEL SURESTE', mb_strtoupper($html));
        $this->assertStringContainsString('Col. Centro', $html);
        $this->assertStringContainsString('Calle 60 123', $html, 'Sin shippingAddress se usa la direccion de la empresa.');
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('BORRADOR', $html);
        $this->assertStringNotContainsString('Recibido</th>', $html);
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
