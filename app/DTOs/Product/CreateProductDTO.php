<?php

namespace App\DTOs\Product;

readonly class CreateProductDTO
{
    /**
     * @param int[]             $categoryIds
     * @param VariantInputDTO[] $variants
     *
     * `$key` null = se genera con la categoria principal, el grupo de tallas y
     * `$keySegment`.
     */
    public function __construct(
        public array                $categoryIds,
        public int                  $mainCategoryId,
        public ?int                 $sizeGroupId,
        public ?string              $key,
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
        public ?string              $keySegment = null,
    ) {}
}
