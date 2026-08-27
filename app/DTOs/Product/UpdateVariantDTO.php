<?php

namespace App\DTOs\Product;

readonly class UpdateVariantDTO
{
    /**
     * null = el campo no se envio y no se modifica. `codeBar` se envia como
     * cadena vacia para limpiarlo.
     */
    public function __construct(
        public ?string $sku,
        public ?string $codeBar,
        public ?string $status,
    ) {}
}
