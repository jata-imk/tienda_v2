<?php

namespace App\DTOs\SizeGroup;

readonly class CreateSizeGroupDTO
{
    public function __construct(
        public string  $name,
        public ?string $description,
        public string  $status = 'active',
    ) {}
}
