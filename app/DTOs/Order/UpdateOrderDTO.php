<?php

namespace App\DTOs\Order;

readonly class UpdateOrderDTO
{
    /**
     * @param array<string, mixed> $attributes Columnas del encabezado (snake_case)
     *        que vinieron en la peticion. Una llave ausente no se toca; una con
     *        null limpia el campo.
     * @param OrderLineDTO[]|null $lines null = las lineas no se tocan; un arreglo
     *        reemplaza el conjunto completo.
     */
    public function __construct(
        public array  $attributes,
        public ?array $lines,
    ) {}
}
