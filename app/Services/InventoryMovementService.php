<?php

namespace App\Services;

use App\DTOs\InventoryMovement\RegisterMovementsDTO;
use App\Exceptions\InventoryDomainException;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class InventoryMovementService
{
    /**
     * Tipos de movimiento que incrementan la existencia.
     * Los demas (sale, adjustment) la disminuyen.
     */
    private const INCREASE_TYPES = ['entry', 'return', 'cancel'];

    /**
     * Registra uno o varios movimientos sobre variantes de un mismo producto
     * y actualiza su stock de forma atomica: si cualquier linea falla, no se
     * aplica ninguna.
     *
     * @return array{movements: InventoryMovement[], totalStock: float}
     *
     * @throws InventoryDomainException si el producto no controla existencias,
     *         alguna variante no existe / no pertenece al producto, o el stock
     *         quedaria negativo.
     */
    public function register(RegisterMovementsDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {
            $product = Product::find($dto->productId);

            if (!$product) {
                throw new InventoryDomainException('Producto no encontrado.');
            }

            if (!$product->stock_control) {
                throw new InventoryDomainException('El producto no maneja existencias.');
            }

            $variantIds = array_map(fn ($line) => $line->productVariantId, $dto->lines);

            // Se bloquean todas las variantes en una sola consulta, ordenada
            // por PK, para fijar un orden de bloqueo global: sin esto, dos
            // peticiones concurrentes que toquen las mismas variantes en
            // orden distinto pueden producir deadlock en MariaDB.
            $variants = ProductVariant::whereIn('id', $variantIds)
                ->with(['size', 'color'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($variantIds as $variantId) {
                $variant = $variants->get($variantId);

                if (!$variant) {
                    throw new InventoryDomainException("La variante {$variantId} no existe.");
                }

                if ($variant->id_product !== $dto->productId) {
                    throw new InventoryDomainException("La variante {$variantId} no pertenece al producto.");
                }
            }

            $movements = [];

            foreach ($dto->lines as $line) {
                $variant       = $variants->get($line->productVariantId);
                $previousStock = (float) $variant->stock;
                $signedQty     = in_array($line->movementType, self::INCREASE_TYPES, true)
                    ? $line->quantity
                    : -$line->quantity;
                $newStock = $previousStock + $signedQty;

                if ($newStock < 0) {
                    $variantLabel = trim(($variant->color?->name ?? '').' / '.($variant->size?->name ?? ''), ' /');

                    throw new InventoryDomainException(
                        "La variante {$variantLabel} no tiene existencia suficiente.",
                        [
                            'idProductVariant'  => $variant->id,
                            'currentStock'      => $previousStock,
                            'requestedQuantity' => $line->quantity,
                        ],
                    );
                }

                $variant->update(['stock' => $newStock]);

                $movements[] = InventoryMovement::create([
                    'id_product_variant' => $variant->id,
                    'movement_type'      => $line->movementType,
                    'quantity'           => $line->quantity,
                    'previous_stock'     => $previousStock,
                    'new_stock'          => $newStock,
                    'reference_type'     => $dto->referenceType,
                    'reference_id'       => $dto->referenceId,
                    'notes'              => $dto->notes,
                    'id_user'            => $dto->userId,
                ]);
            }

            $totalStock = (float) ProductVariant::where('id_product', $dto->productId)
                ->where('status', 'active')
                ->sum('stock');

            return [
                'movements'  => $movements,
                'totalStock' => $totalStock,
            ];
        });
    }
}
