<?php

namespace App\DTOs\Config;

readonly class ActualizarTipoMonedaDTO
{
    public function __construct(
        public ?string $name,
        public ?string $code,
        public ?string $symbol,
        public ?string $status,
    ) {}
}
