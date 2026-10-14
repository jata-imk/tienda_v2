<?php

namespace App\DTOs\Order;

readonly class OrderTransitionDTO
{
    public function __construct(
        public ?string $notes,
    ) {}
}
