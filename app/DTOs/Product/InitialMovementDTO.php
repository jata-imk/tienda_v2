<?php

namespace App\DTOs\Product;

readonly class InitialMovementDTO
{
    public function __construct(
        public string  $movementType,
        public string  $referenceType,
        public ?int    $referenceId,
        public ?string $notes,
        public int     $userId,
    ) {}
}
