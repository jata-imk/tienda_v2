<?php

namespace App\Http\Requests\Product;

use App\DTOs\Product\UpdateVariantDTO;
use App\Http\Requests\Product\Concerns\UppercasesSkuInput;
use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edicion de una variante existente. `idSize` e `idColor` no son editables
 * (romperian el unique del producto y la trazabilidad de los movimientos) y el
 * `stock` se mueve unicamente por `POST /api/inventory/movements`.
 *
 * Activar una variante cuyo producto esta inactivo se rechaza con 422: dejaria
 * una variante activa bajo un producto dado de baja, el mismo desacuerdo que la
 * cascada de ProductService evita por el otro lado.
 */
class UpdateProductVariantRequest extends FormRequest
{
    use UppercasesSkuInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->uppercaseSkus();
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('status') !== 'active') {
                return;
            }

            $product = Product::find((int) $this->route('product'));

            // Producto inexistente: el controller responde 404, aqui no se valida.
            if (!$product || $product->status === 'active') {
                return;
            }

            $validator->errors()->add(
                'status',
                'No se puede activar una variante de un producto inactivo: reactiva primero el producto.'
            );
        });
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
