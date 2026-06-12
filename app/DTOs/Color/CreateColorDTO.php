<?php

namespace App\DTOs\Color;

readonly class CreateColorDTO
{
    public function __construct(
        public string  $name,
        public ?string $hexColor,
        public string  $status = 'active',
    ) {}
}
