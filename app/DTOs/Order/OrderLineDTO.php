<?php

namespace App\DTOs\Order;

readonly class OrderLineDTO
{
    /**
     * `unitCost` null = tomar el costo del producto (convertido a la moneda
     * del pedido).
     */
    public function __construct(
        public int     $productVariantId,
        public float   $quantityOrdered,
        public ?float  $unitCost,
        public ?string $notes,
    ) {}
}
