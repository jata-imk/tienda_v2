<?php

namespace App\DTOs\Size;

readonly class UpdateSizeDTO
{
    public function __construct(
        public ?int    $sizeGroupId,
        public ?string $name,
        public ?int    $sortOrder,
        public ?string $status,
    ) {}
}
