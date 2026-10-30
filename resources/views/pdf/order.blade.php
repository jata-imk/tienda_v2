@php
    $money = fn ($value) => number_format((float) $value, 2).' '.$currencyCode;
    $qty   = fn ($value) => rtrim(rtrim(number_format((float) $value, 3), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pedido de Compra {{ $order->folio_label }}</title>
    <style>
        @page { margin: 32px 36px 48px 36px; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 9px; color: #222; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }
        .muted { color: #555; }
        .label { color: #444; font-weight: bold; }
        .box { border: 1px solid #888; }
        .box td { padding: 6px 8px; }
        .title { font-size: 16px; padding-top: 6px; }
        .company-name { font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .company-lines { text-transform: uppercase; line-height: 1.35; }
        .info td { padding: 1px 0; }
        .info .label { width: 105px; }
        .lines { margin-top: 10px; border: 1px solid #888; }
        .lines thead th { border-bottom: 1px solid #888; padding: 5px 6px; font-size: 9px; text-align: left; }
        .lines tbody td { padding: 6px; }
        .lines .note { font-size: 8px; color: #2b4a8b; }
        .lines .sep td { border-bottom: 1px solid #ddd; padding: 0; }
        .sizes { width: auto; margin-bottom: 3px; }
        .sizes td, .sizes th { border: 1px solid #bbb; padding: 1px 4px; text-align: center; font-size: 8px; min-width: 14px; }
        .sizes th { background: #eee; font-weight: bold; }
        /* Gana sobre `.lines tbody td`, que es mas especifico que `.sizes td`. */
        .lines .sizes td, .lines .sizes th { padding: 1px 4px; }
        .num { text-align: right; white-space: nowrap; }
        .sku { font-weight: bold; }
        .totals { margin-top: 8px; }
        .totals td { padding: 3px 6px; }
        .totals .grand { font-size: 11px; font-weight: bold; border-top: 1px solid #888; }
        .watermark {
            position: fixed; top: 38%; left: 8%; width: 84%;
            text-align: center; font-size: 90px; font-weight: bold;
            color: #cc0000; opacity: 0.12; transform: rotate(-30deg);
        }
        .logo { max-height: 80px; max-width: 200px; }
    </style>
</head>
<body>
@if ($watermark)
    <div class="watermark">{{ $watermark }}</div>
@endif

{{-- Encabezado: emisor (nuestra empresa) a la izquierda, logo a la derecha --}}
<table>
    <tr>
        <td>
            <div class="company-name">{{ $company?->legal_name ?: $company?->name }}</div>
            <div class="company-lines">
                @if ($companyAddress)
                    {{ $companyAddress }}<br>
                @endif
                @if ($company?->rfc)
                    RFC: {{ $company->rfc }}
                @endif
            </div>
            <div class="title">Pedido de Compra</div>
        </td>
        <td style="width: 210px; text-align: right;">
            @if ($logo)
                <img class="logo" src="{{ $logo }}" alt="">
            @endif
        </td>
    </tr>
</table>

{{-- Proveedor + informacion del documento --}}
<table class="box" style="margin-top: 8px;">
    <tr>
        <td style="width: 45%; border-right: 1px solid #888;">
            <div class="label">Proveedor</div>
            @if ($order->supplier_name)
                <div style="text-transform: uppercase;">{{ $order->supplier_name }}</div>
            @endif
            @if ($order->supplier_details)
                <div>{!! nl2br(e($order->supplier_details)) !!}</div>
            @endif
            @if (!$order->supplier_name && !$order->supplier_details)
                <div class="muted">—</div>
            @endif
        </td>
        <td style="width: 55%;">
            <div class="label">Información</div>
            <table class="info">
                <tr><td class="label">Número documento</td><td>{{ $order->folio_label }}</td></tr>
                <tr><td class="label">Fecha emisión</td><td>{{ $order->date_order?->format('d.m.Y') }}</td></tr>
                <tr><td class="label">Fecha entrega</td><td>{{ $order->date_expected?->format('d.m.Y') ?? '—' }}</td></tr>
                <tr><td class="label">Moneda</td><td>{{ $currencyCode }}@if ($exchangeRate) · T.C. {{ number_format($exchangeRate, 4) }}@endif</td></tr>
                <tr><td class="label">Estatus</td><td>{{ $statusLabel }}</td></tr>
            </table>
        </td>
    </tr>
    @if ($order->notes)
        <tr>
            <td colspan="2" style="border-top: 1px solid #888;">
                <table>
                    <tr>
                        <td class="label" style="width: 110px;">Notas</td>
                        <td>{!! nl2br(e($order->notes)) !!}</td>
                    </tr>
                </table>
            </td>
        </tr>
    @endif
</table>

{{-- Lineas: un renglon por producto + color, con sus tallas en matriz --}}
<table class="lines">
    <thead>
        <tr>
            <th>Producto/Descripción</th>
            <th style="width: 160px;">Tallas</th>
            <th class="num" style="width: 48px;">Cantidad</th>
            @if ($showReceived)
                <th class="num" style="width: 48px;">Recibido</th>
            @endif
            <th class="num" style="width: 75px;">Precio unitario</th>
            <th class="num" style="width: 85px;">Monto Neto</th>
        </tr>
    </thead>
    <tbody>
        @php $columns = $showReceived ? 6 : 5; @endphp
        @foreach ($groups as $group)
            <tr>
                <td>
                    <span class="sku">{{ $group['productKey'] }}</span><br>
                    <span style="text-transform: uppercase;">{{ $group['productName'] }} · {{ $group['color'] }}</span>
                    @foreach ($group['notes'] as $note)
                        <div class="note">{{ $note }}</div>
                    @endforeach
                </td>
                <td>
                    @foreach ($group['sizeRows'] as $row)
                        <table class="sizes">
                            <tr>
                                <th>Talla</th>
                                @foreach ($row as $size)
                                    <th>{{ $size['size'] }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                <th>Cant.</th>
                                @foreach ($row as $size)
                                    <td>{{ $qty($size['ordered']) }}</td>
                                @endforeach
                            </tr>
                            @if ($showReceived)
                                <tr>
                                    <th>Recib.</th>
                                    @foreach ($row as $size)
                                        <td>{{ $qty($size['received']) }}</td>
                                    @endforeach
                                </tr>
                            @endif
                        </table>
                    @endforeach
                </td>
                <td class="num">{{ $qty($group['ordered']) }}</td>
                @if ($showReceived)
                    <td class="num">{{ $qty($group['received']) }}</td>
                @endif
                <td class="num">{{ $group['unitCost'] === null ? 'Varios' : $money($group['unitCost']) }}</td>
                <td class="num">{{ $money($group['subtotal']) }}</td>
            </tr>
            <tr class="sep"><td colspan="{{ $columns }}"></td></tr>
        @endforeach
    </tbody>
</table>

{{-- Totales --}}
<table class="totals">
    <tr>
        <td style="width: 60%;"></td>
        <td class="label">Total piezas</td>
        <td class="num">{{ $qty($order->total_items) }}</td>
    </tr>
    <tr>
        <td></td>
        <td class="grand">Total neto</td>
        <td class="num grand">{{ $money($order->total_amount) }}</td>
    </tr>
</table>
</body>
</html>
