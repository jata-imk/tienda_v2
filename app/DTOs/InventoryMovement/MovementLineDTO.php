<?php

namespace App\DTOs\InventoryMovement;

readonly class MovementLineDTO
{
    public function __construct(
        public int    $productVariantId,
        public string $movementType,
        public float  $quantity,
    ) {}
}
