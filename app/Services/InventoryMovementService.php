<?php

namespace App\Services;

use App\DTOs\InventoryMovement\CreateMovementDTO;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use DomainException;
use Illuminate\Support\Facades\DB;

class InventoryMovementService
{
    /**
     * Tipos de movimiento que incrementan la existencia.
     * Los demas (sale, adjustment) la disminuyen.
     */
    private const INCREASE_TYPES = ['entry', 'return', 'cancel'];

    /**
     * Registra un movimiento y actualiza el stock de la variante de forma atomica.
     *
     * @throws DomainException si la variante no existe o el stock quedaria negativo.
     */
    public function register(CreateMovementDTO $dto): InventoryMovement
    {
        return DB::transaction(function () use ($dto) {
            $variant = ProductVariant::lockForUpdate()->find($dto->productVariantId);

            if (!$variant) {
                throw new DomainException('Product variant not found.');
            }

            $previousStock = (float) $variant->stock;
            $signedQty     = in_array($dto->movementType, self::INCREASE_TYPES, true)
                ? $dto->quantity
                : -$dto->quantity;
            $newStock = $previousStock + $signedQty;

            if ($newStock < 0) {
                throw new DomainException('La existencia no puede quedar negativa.');
            }

            $variant->update(['stock' => $newStock]);

            return InventoryMovement::create([
                'id_product_variant' => $variant->id,
                'movement_type'      => $dto->movementType,
                'quantity'           => $dto->quantity,
                'previous_stock'     => $previousStock,
                'new_stock'          => $newStock,
                'reference_type'     => $dto->referenceType,
                'reference_id'       => $dto->referenceId,
                'notes'              => $dto->notes,
                'id_user'            => $dto->userId,
            ]);
        });
    }
}
