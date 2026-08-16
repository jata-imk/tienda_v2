<?php

namespace App\DTOs\InventoryMovement;

readonly class RegisterMovementsDTO
{
    /**
     * @param MovementLineDTO[] $lines
     */
    public function __construct(
        public int     $productId,
        public int     $userId,
        public ?string $referenceType,
        public ?int    $referenceId,
        public ?string $notes,
        public array   $lines,
    ) {}
}
