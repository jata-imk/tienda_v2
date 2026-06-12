<?php

namespace App\DTOs\SizeGroup;

readonly class UpdateSizeGroupDTO
{
    public function __construct(
        public ?string $name,
        public ?string $description,
        public ?string $status,
    ) {}
}
