<?php

namespace App\DTOs\Currency;

readonly class UpdateCurrencyDTO
{
    public function __construct(
        public ?string $name,
        public ?string $code,
        public ?string $symbol,
        public ?string $status,
    ) {}
}
