<?php

namespace App\DTOs\Product;

readonly class CreateProductDTO
{
    /**
     * @param int[]             $categoryIds
     * @param VariantInputDTO[] $variants
     */
    public function __construct(
        public array                $categoryIds,
        public ?int                 $sizeGroupId,
        public string               $key,
        public string               $name,
        public ?string              $description,
        public ?string              $codeBar,
        public float                $price,
        public float                $cost,
        public bool                 $stockControl,
        public float                $discount,
        public int                  $typeIva,
        public ?float               $rateIva,
        public ?float               $quotaIva,
        public float                $isr,
        public float                $impEsp,
        public array                $variants = [],
        public ?InitialMovementDTO  $initialMovement = null,
        public string               $status = 'active',
    ) {}
}
