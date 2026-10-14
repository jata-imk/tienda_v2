<?php

namespace App\DTOs\Order;

readonly class CreateOrderDTO
{
    /**
     * @param OrderLineDTO[] $lines
     */
    public function __construct(
        public ?int    $currencyId,
        public ?float  $exchangeRate,
        public ?string $dateOrder,
        public ?string $dateExpected,
        public ?string $supplierName,
        public ?string $supplierDetails,
        public ?string $shippingAddress,
        public ?string $notes,
        public array   $lines,
    ) {}
}
