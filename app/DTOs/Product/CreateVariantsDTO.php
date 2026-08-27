<?php

namespace App\DTOs\Product;

readonly class CreateVariantsDTO
{
    /**
     * @param VariantInputDTO[] $variants
     */
    public function __construct(
        public array               $variants,
        public ?InitialMovementDTO $initialMovement,
    ) {}
}
