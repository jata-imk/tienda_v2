<?php

namespace App\Http\Requests\Product\Concerns;

use App\Models\ProductVariant;
use App\Models\Size;
use Illuminate\Contracts\Validation\Validator;

/**
 * Reglas de coherencia de las variantes (talla x color) compartidas entre el
 * alta del producto completo y el alta incremental sobre un producto existente.
 */
trait ValidatesVariantInput
{
    /**
     * Las tallas de las variantes deben pertenecer al grupo de tallas del producto.
     */
    protected function validateSizesBelongToGroup(Validator $validator, array $variants, ?int $sizeGroupId): void
    {
        if (!$sizeGroupId) {
            return;
        }

        $validSizeIds = Size::where('id_size_group', $sizeGroupId)->pluck('id')->all();

        foreach ($variants as $i => $variant) {
            $sizeId = $variant['idSize'] ?? null;

            if ($sizeId !== null && !in_array((int) $sizeId, $validSizeIds, true)) {
                $validator->errors()->add(
                    "variants.{$i}.idSize",
                    'La talla seleccionada no pertenece al grupo de tallas del producto.'
                );
            }
        }
    }

    /**
     * No se permite repetir la combinacion talla + color dentro del payload.
     */
    protected function validateCombosAreUniqueInPayload(Validator $validator, array $variants): void
    {
        $combos = [];

        foreach ($variants as $i => $variant) {
            $combo = ($variant['idSize'] ?? '') . '-' . ($variant['idColor'] ?? '');

            if (isset($combos[$combo])) {
                $validator->errors()->add(
                    "variants.{$i}.idColor",
                    'La combinacion de talla y color esta repetida.'
                );
            }

            $combos[$combo] = true;
        }
    }

    /**
     * La combinacion no debe existir ya en el producto: el unique
     * `id_product + id_size + id_color` reventaria como error 500.
     */
    protected function validateCombosAreNewForProduct(Validator $validator, array $variants, int $productId): void
    {
        $existing = ProductVariant::where('id_product', $productId)
            ->get(['id_size', 'id_color'])
            ->map(fn(ProductVariant $variant) => $variant->id_size . '-' . $variant->id_color)
            ->all();

        foreach ($variants as $i => $variant) {
            $combo = ($variant['idSize'] ?? '') . '-' . ($variant['idColor'] ?? '');

            if (in_array($combo, $existing, true)) {
                $validator->errors()->add(
                    "variants.{$i}.idColor",
                    'El producto ya tiene una variante con esa combinacion de talla y color.'
                );
            }
        }
    }
}
