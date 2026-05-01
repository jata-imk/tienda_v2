<?php

namespace App\DTOs\Config;

readonly class CrearTipoMonedaDTO
{
    public function __construct(
        public string $name,
        public string $code,
        public string $symbol,
        public string $status = 'activo',
    ) {}
}
