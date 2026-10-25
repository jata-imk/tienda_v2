<?php

namespace App\DTOs\Product;

/**
 * Renombrado en cascada calculado pero no aplicado: lo que la vista previa
 * (`dryRun`) devuelve y lo que el `PUT` con `cascadeKeys` aplica.
 */
readonly class KeyRenamePlan
{
    /**
     * @param list<array{id: int, from: string, to: string}>                  $products
     * @param list<array{id: int, idProduct: int, from: string, to: string}>  $variants
     * @param list<array{type: 'product'|'variant', key: string}>             $conflicts claves nuevas que ya usa
     *        otro registro (o que se repiten dentro del plan).
     */
    public function __construct(
        public array $products = [],
        public array $variants = [],
        public array $conflicts = [],
    ) {}

    public function hasConflicts(): bool
    {
        return $this->conflicts !== [];
    }
}
