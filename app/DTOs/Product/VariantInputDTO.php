<?php

namespace App\DTOs\Product;

readonly class VariantInputDTO
{
    public function __construct(
        public int     $sizeId,
        public int     $colorId,
        public string  $sku,
        public ?string $codeBar,
        public float   $stock,
        public string  $status = 'active',
    ) {}
}
