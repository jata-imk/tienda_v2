<?php

namespace App\DTOs\Order;

readonly class ReceiveLineDTO
{
    public function __construct(
        public int   $detailId,
        public float $quantity,
    ) {}
}
