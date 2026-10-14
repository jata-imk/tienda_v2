<?php

namespace App\Services;

use App\Models\CompanyInfo;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

/**
 * PDF del pedido de compra. La empresa (`company_info`) aparece como emisor y
 * el proveedor (texto libre del pedido) en el recuadro de destinatario.
 */
class OrderPdfService
{
    private const WATERMARKS = [
        'draft'     => 'BORRADOR',
        'cancelled' => 'CANCELADO',
    ];

    private const STATUS_LABELS = [
        'draft'              => 'Borrador',
        'confirmed'          => 'Confirmado',
        'partially_received' => 'Recibido parcialmente',
        'received'           => 'Recibido',
        'cancelled'          => 'Cancelado',
    ];

    public function __construct(private OrderService $orderService) {}

    /**
     * @param Order $order con `details.variant.{product,size,color}`, `currency` y `user` cargados.
     */
    public function render(Order $order): PdfDocument
    {
        $pdf = Pdf::loadView('pdf.order', $this->viewData($order))
            ->setPaper('letter')
            // Sin subsetting se incrusta DejaVu Sans completa (~800 KB por PDF).
            ->setOption('isFontSubsettingEnabled', true);

        // El contador "Pagina X de Y" se escribe sobre el canvas ya renderizado:
        // asi no hace falta habilitar PHP embebido en la vista (isPhpEnabled).
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $font   = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $label  = $order->folio_label;

        $dompdf->getCanvas()->page_script(function ($pageNumber, $pageCount, $canvas) use ($font, $label) {
            // La marca de agua deja la opacidad del canvas en 0.12; sin
            // restablecerla el pie sale casi invisible en borradores.
            $canvas->set_opacity(1.0);
            $canvas->text(
                $canvas->get_width() - 170,
                $canvas->get_height() - 28,
                "{$label} · Página {$pageNumber} de {$pageCount}",
                $font,
                7,
                [0.35, 0.35, 0.35],
            );
        });

        return $pdf;
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(Order $order): array
    {
        $company      = CompanyInfo::first();
        $currencyCode = $order->currency?->code ?? '';
        $rate         = $this->orderService->effectiveExchangeRate($order);
        $isBase       = $order->id_currency === $this->orderService->baseCurrencyId();

        return [
            'order'           => $order,
            'company'         => $company,
            'logo'            => $this->logoDataUri($company?->logo),
            'companyAddress'  => $this->companyAddress($company),
            'shippingAddress' => $order->shipping_address
                ? preg_split('/\R/', trim($order->shipping_address))
                : array_values(array_filter([$company?->name, ...$this->companyAddress($company)])),
            'currencyCode'    => $currencyCode,
            'exchangeRate'    => $isBase ? null : $rate,
            'statusLabel'     => self::STATUS_LABELS[$order->status->value] ?? $order->status->value,
            'watermark'       => self::WATERMARKS[$order->status->value] ?? null,
            'showReceived'    => $order->details->contains(fn ($d) => (float) $d->quantity_received > 0),
        ];
    }

    /**
     * `company_info.logo` se guarda en base64, con o sin prefijo data-URI.
     */
    private function logoDataUri(?string $logo): ?string
    {
        if ($logo === null || trim($logo) === '') {
            return null;
        }

        if (str_starts_with($logo, 'data:image/')) {
            return $logo;
        }

        $binary = base64_decode(preg_replace('/\s+/', '', $logo), true);

        if ($binary === false) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary) ?: 'image/png';

        return str_starts_with($mime, 'image/') ? "data:{$mime};base64,".base64_encode($binary) : null;
    }

    /**
     * @return string[] lineas de la direccion, sin vacias
     */
    private function companyAddress(?CompanyInfo $company): array
    {
        if (!$company) {
            return [];
        }

        $street = trim(implode(' ', array_filter([$company->street, $company->external_number])));
        $city   = trim(implode(' ', array_filter([$company->postal_code, $company->city])));

        return array_values(array_filter([$street, $company->neighborhood, $city]));
    }
}
