<?php

namespace App\DTOs\Inventario;

readonly class ActualizarCategoriaDTO
{
    public function __construct(
        public ?string $name,
        public ?string $description,
        public ?string $status,
    ) {}
}
