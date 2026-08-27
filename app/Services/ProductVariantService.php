<?php

namespace App\Services;

use App\DTOs\Product\CreateVariantsDTO;
use App\DTOs\Product\UpdateVariantDTO;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alta, edicion y baja de variantes sobre un producto ya creado. El `stock` de
 * una variante existente no se toca aqui: eso vive en InventoryMovementService.
 */
class ProductVariantService
{
    public function listByProduct(int $productId): Collection
    {
        return ProductVariant::with(['size', 'color'])
            ->where('id_product', $productId)
            ->get();
    }

    /**
     * Crea las variantes y, si se pidio `initialMovement`, un movimiento por
     * cada una con existencia inicial > 0. Todo dentro de una transaccion.
     *
     * @return Collection<int, ProductVariant>
     */
    public function store(Product $product, CreateVariantsDTO $dto): Collection
    {
        return DB::transaction(function () use ($product, $dto) {
            $created = new Collection();

            foreach ($dto->variants as $variantInput) {
                $variant = ProductVariant::create([
                    'id_product' => $product->id,
                    'id_size'    => $variantInput->sizeId,
                    'id_color'   => $variantInput->colorId,
                    'sku'        => $variantInput->sku,
                    'code_bar'   => $variantInput->codeBar,
                    'stock'      => $variantInput->stock,
                    'status'     => $variantInput->status,
                ]);

                // Movimiento inicial por cada variante con existencia > 0.
                if ($dto->initialMovement !== null && $variantInput->stock > 0) {
                    InventoryMovement::create([
                        'id_product_variant' => $variant->id,
                        'movement_type'      => $dto->initialMovement->movementType,
                        'quantity'           => $variantInput->stock,
                        'previous_stock'     => 0,
                        'new_stock'          => $variantInput->stock,
                        'reference_type'     => $dto->initialMovement->referenceType,
                        'reference_id'       => $dto->initialMovement->referenceId,
                        'notes'              => $dto->initialMovement->notes,
                        'id_user'            => $dto->initialMovement->userId,
                    ]);
                }

                $created->push($variant->load(['size', 'color']));
            }

            return $created;
        });
    }

    public function update(ProductVariant $variant, UpdateVariantDTO $dto): ProductVariant
    {
        $fields = array_filter([
            'sku'      => $dto->sku,
            'code_bar' => $dto->codeBar,
            'status'   => $dto->status,
        ], fn($v) => $v !== null);

        if ($fields !== []) {
            $variant->update($fields);
        }

        return $variant->fresh(['size', 'color']);
    }

    /**
     * Baja logica: conserva la fila y sus movimientos historicos, pero deja de
     * sumar en el `totalStock` del producto.
     */
    public function deactivate(ProductVariant $variant): ProductVariant
    {
        $variant->update(['status' => 'inactive']);

        return $variant->fresh(['size', 'color']);
    }

    /** Existencia vigente del producto (solo variantes activas). */
    public function totalStock(Product $product): float
    {
        return (float) $product->fresh('variants')->total_stock;
    }
}
