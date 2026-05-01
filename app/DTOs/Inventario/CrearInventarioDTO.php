<?php

namespace App\DTOs\Inventario;

readonly class CrearInventarioDTO
{
    public function __construct(
        public int     $categoryId,
        public string  $clave,
        public string  $name,
        public ?string $description,
        public ?string $codebar,
        public float   $price,
        public float   $cost,
        public bool    $stockControl,
        public float   $stock,
        public float   $discount,
        public int     $typeIvaId,
        public ?float  $rateIva,
        public ?float  $quotaIva,
        public float   $isr,
        public float   $impEsp,
        public string  $status = 'activo',
    ) {}
}
