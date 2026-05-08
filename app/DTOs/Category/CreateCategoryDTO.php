<?php

namespace App\DTOs\Category;

readonly class CreateCategoryDTO
{
    public function __construct(
        public string  $name,
        public ?string $description,
        public string  $status = 'active',
    ) {}
}
