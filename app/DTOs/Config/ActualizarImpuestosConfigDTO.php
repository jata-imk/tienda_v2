<?php

namespace App\DTOs\Config;

readonly class ActualizarImpuestosConfigDTO
{
    public function __construct(
        public ?float $iva,
        public ?float $isr,
        public ?float $impEsp,
    ) {}
}
