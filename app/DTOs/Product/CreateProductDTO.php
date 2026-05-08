<?php

namespace App\DTOs\Product;

readonly class CreateProductDTO
{
    public function __construct(
        public int     $categoryId,
        public string  $key,
        public string  $name,
        public ?string $description,
        public ?string $codeBar,
        public ?string $size,
        public float   $price,
        public float   $cost,
        public bool    $stockControl,
        public float   $stock,
        public float   $discount,
        public int     $typeIva,
        public ?float  $rateIva,
        public ?float  $quotaIva,
        public float   $isr,
        public float   $impEsp,
        public string  $status = 'active',
    ) {}
}
