<?php

namespace App\DTOs\InventoryMovement;

readonly class CreateMovementDTO
{
    public function __construct(
        public int     $productVariantId,
        public string  $movementType,
        public float   $quantity,
        public ?string $referenceType,
        public ?int    $referenceId,
        public ?string $notes,
        public int     $userId,
    ) {}
}
