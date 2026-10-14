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
        .lines tbody td { padding: 2px 6px; }
        .lines tbody tr.first td { padding-top: 6px; }
        .lines tbody tr.last td { padding-bottom: 6px; }
        .lines .size { text-align: center; font-weight: bold; }
        .lines .note { font-size: 8px; color: #2b4a8b; }
        .lines .sep td { border-bottom: 1px solid #ddd; padding: 0; }
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
        .logo { max-height: 105px; max-width: 200px; }
    </style>
</head>
<body>
@if ($watermark)
    <div class="watermark">{{ $watermark }}</div>
@endif

{{-- Encabezado: emisor (nuestra empresa) --}}
<table>
    <tr>
        <td style="width: 45%;">
            @if ($logo)
                <img class="logo" src="{{ $logo }}" alt="">
            @else
                <div class="company-name" style="font-size: 14px;">{{ $company?->name }}</div>
            @endif
        </td>
        <td style="width: 55%;">
            <div class="company-name">{{ $company?->legal_name ?: $company?->name }}</div>
            <div class="company-lines">
                @foreach ($companyAddress as $line)
                    {{ $line }}<br>
                @endforeach
                @if ($company?->rfc)
                    RFC: {{ $company->rfc }}
                @endif
            </div>
            <div class="title">Pedido de Compra</div>
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
                <tr><td class="label">Fecha documento</td><td>{{ $order->date_order?->format('d.m.Y') }}</td></tr>
                <tr><td class="label">Comprador</td><td>{{ $order->user?->user_name }}</td></tr>
                <tr><td class="label">Fecha entrega</td><td>{{ $order->date_expected?->format('d.m.Y') ?? '—' }}</td></tr>
                <tr><td class="label">Moneda</td><td>{{ $currencyCode }}@if ($exchangeRate) · T.C. {{ number_format($exchangeRate, 4) }}@endif</td></tr>
                <tr><td class="label">Estatus</td><td>{{ $statusLabel }}</td></tr>
            </table>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="border-top: 1px solid #888;">
            <table>
                <tr>
                    <td class="label" style="width: 110px;">Dirección envío</td>
                    <td class="company-lines">
                        @foreach ($shippingAddress as $line)
                            {{ $line }}<br>
                        @endforeach
                    </td>
                </tr>
                @if ($order->notes)
                    <tr>
                        <td class="label" style="padding-top: 6px;">Notas</td>
                        <td style="padding-top: 6px;">{!! nl2br(e($order->notes)) !!}</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

{{-- Lineas --}}
<table class="lines">
    <thead>
        <tr>
            <th style="width: 38px;">Artículo</th>
            <th>Artículo/Descripción</th>
            <th style="width: 40px; text-align: center;">Talla</th>
            <th class="num" style="width: 48px;">Cantidad</th>
            @if ($showReceived)
                <th class="num" style="width: 48px;">Recibido</th>
            @endif
            <th style="width: 28px;">UM</th>
            <th class="num" style="width: 80px;">Precio unitario</th>
            <th class="num" style="width: 88px;">Monto Neto</th>
        </tr>
    </thead>
    <tbody>
        {{-- Un renglon por talla; el articulo y la descripcion (producto +
             color) solo se escriben en el primer renglon de cada grupo. --}}
        @php $columns = $showReceived ? 8 : 7; @endphp
        @foreach ($groups as $index => $group)
            @foreach ($group['sizes'] as $position => $size)
                @php
                    $isFirst = $position === 0;
                    $isLast  = $position === count($group['sizes']) - 1;
                @endphp
                <tr class="{{ $isFirst ? 'first' : '' }} {{ $isLast ? 'last' : '' }}">
                    <td>{{ $isFirst ? ($index + 1) * 10 : '' }}</td>
                    {{-- La clave va en el renglon de la primera talla y el nombre +
                         color en el de la segunda, para que cada renglon tenga
                         una sola linea y las tallas queden parejas. --}}
                    <td>
                        @if ($isFirst)
                            <span class="sku">{{ $group['productKey'] }}</span>
                        @endif
                        @if ($position === 1 || ($isFirst && count($group['sizes']) === 1))
                            @if ($isFirst)<br>@endif
                            <span style="text-transform: uppercase;">{{ $group['productName'] }} · {{ $group['color'] }}</span>
                        @endif
                        @if ($size['notes'])
                            <div class="note">Talla {{ $size['size'] }}: {{ $size['notes'] }}</div>
                        @endif
                    </td>
                    <td class="size">{{ $size['size'] }}</td>
                    <td class="num">{{ $qty($size['ordered']) }}</td>
                    @if ($showReceived)
                        <td class="num">{{ $qty($size['received']) }}</td>
                    @endif
                    <td>PZA</td>
                    <td class="num">{{ $money($size['unitCost']) }}</td>
                    <td class="num">{{ $money($size['subtotal']) }}</td>
                </tr>
            @endforeach
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
