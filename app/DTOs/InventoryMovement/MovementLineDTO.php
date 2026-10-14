<?php

namespace App\DTOs\InventoryMovement;

readonly class MovementLineDTO
{
    /**
     * `referenceId` sobreescribe al del lote para esta linea: la recepcion de
     * un pedido apunta cada movimiento a su propia linea (`order_details.id`).
     */
    public function __construct(
        public int    $productVariantId,
        public string $movementType,
        public float  $quantity,
        public ?int   $referenceId = null,
    ) {}
}
