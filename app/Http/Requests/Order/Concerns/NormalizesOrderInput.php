<?php

namespace App\Http\Requests\Order\Concerns;

use App\DTOs\Order\OrderLineDTO;

/**
 * Reglas del encabezado y de las lineas (`details[]`) compartidas por alta y
 * edicion, y armado de los DTO de linea.
 */
trait NormalizesOrderInput
{
    /**
     * Mismos limites que las columnas: cantidades decimal(10,3) y costo
     * decimal(10,2). `distinct` porque una variante va una sola vez por pedido.
     *
     * @return array<string, string>
     */
    protected function lineRules(string $presence): array
    {
        return [
            'details'                    => "{$presence}|array|min:1|max:200",
            'details.*.idProductVariant' => 'required|integer|distinct|exists:product_variants,id',
            'details.*.quantityOrdered'  => 'required|numeric|gt:0|max:9999999.999',
            'details.*.unitCost'         => 'nullable|numeric|min:0|max:99999999.99',
            'details.*.notes'            => 'nullable|string|max:500',
        ];
    }

    /**
     * @return OrderLineDTO[]
     */
    protected function linesToDTO(): array
    {
        return array_map(
            fn (array $line) => new OrderLineDTO(
                productVariantId: (int) $line['idProductVariant'],
                quantityOrdered:  (float) $line['quantityOrdered'],
                unitCost:         isset($line['unitCost']) ? (float) $line['unitCost'] : null,
                notes:            $line['notes'] ?? null,
            ),
            $this->validated('details'),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function headerRules(bool $partial): array
    {
        $presence = 'sometimes';

        // En el alta `dateOrder` vacio toma la fecha de hoy; en la edicion no
        // se puede limpiar porque la columna no admite null.
        $dateOrder = $partial ? 'sometimes|date' : 'nullable|date';

        // Sin `dateOrder` en la peticion, `after_or_equal:dateOrder` intentaria
        // interpretar el texto "dateOrder" como fecha y fallaria siempre.
        $expected = $this->filled('dateOrder') ? '|after_or_equal:dateOrder' : '';

        return [
            'idCurrency'      => "{$presence}|nullable|integer|exists:currencies,id",
            'exchangeRate'    => "{$presence}|nullable|numeric|gt:0|max:999999999999",
            'dateOrder'       => $dateOrder,
            'dateExpected'    => "{$presence}|nullable|date{$expected}",
            'supplierName'    => "{$presence}|nullable|string|max:255",
            'supplierDetails' => "{$presence}|nullable|string|max:2000",
            'shippingAddress' => "{$presence}|nullable|string|max:2000",
            'notes'           => "{$presence}|nullable|string|max:2000",
        ];
    }
}
