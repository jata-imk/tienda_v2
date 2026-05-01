<?php

namespace App\DTOs\Inventario;

readonly class CrearCategoriaDTO
{
    public function __construct(
        public string  $name,
        public ?string $description,
        public string  $status = 'activo',
    ) {}
}
