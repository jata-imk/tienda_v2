<?php

namespace App\DTOs\Product;

readonly class NextProductKeyDTO
{
    public function __construct(
        public int     $mainCategoryId,
        public ?int    $sizeGroupId,
        public ?string $keySegment,
    ) {}
}
