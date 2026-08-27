<?php

namespace App\Http\Requests\Product;

use App\DTOs\Product\UpdateVariantDTO;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edicion de una variante existente. `idSize` e `idColor` no son editables
 * (romperian el unique del producto y la trazabilidad de los movimientos) y el
 * `stock` se mueve unicamente por `POST /api/inventory/movements`.
 */
class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variantId = (int) $this->route('variant');

        return [
            'sku'     => 'sometimes|string|max:80|unique:product_variants,sku,' . $variantId,
            'codeBar' => 'sometimes|nullable|string|max:50',
            'status'  => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): UpdateVariantDTO
    {
        return new UpdateVariantDTO(
            sku:     $this->input('sku'),
            codeBar: $this->input('codeBar'),
            status:  $this->input('status'),
        );
    }
}
