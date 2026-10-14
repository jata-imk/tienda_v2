<?php

namespace App\DTOs\Order;

readonly class ReceiveOrderDTO
{
    /**
     * @param ReceiveLineDTO[] $lines
     */
    public function __construct(
        public ?string $notes,
        public array   $lines,
    ) {}
}
