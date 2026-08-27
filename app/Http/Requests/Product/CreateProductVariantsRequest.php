<?php

namespace App\Http\Requests\Product;

use App\DTOs\Product\CreateVariantsDTO;
use App\DTOs\Product\InitialMovementDTO;
use App\DTOs\Product\VariantInputDTO;
use App\Http\Requests\Product\Concerns\ValidatesVariantInput;
use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta incremental de variantes sobre un producto que ya existe. El grupo de
 * tallas no viene en el payload: se toma del producto.
 */
class CreateProductVariantsRequest extends FormRequest
{
    use ValidatesVariantInput;

    private ?Product $product = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'variants'           => 'required|array|min:1',
            'variants.*.idSize'  => 'required|integer|exists:sizes,id',
            'variants.*.idColor' => 'required|integer|exists:colors,id',
            'variants.*.sku'     => 'required|string|max:80|distinct|unique:product_variants,sku',
            'variants.*.codeBar' => 'nullable|string|max:50',
            'variants.*.stock'   => 'required|numeric|min:0',
            'variants.*.status'  => 'sometimes|in:active,inactive',

            // Movimiento inicial (opcional), mismo contrato que el alta de producto.
            'initialMovement'               => 'sometimes|array',
            'initialMovement.movementType'  => 'required_with:initialMovement|in:entry,sale,adjustment,return,cancel',
            'initialMovement.referenceType' => 'nullable|string|max:50',
            'initialMovement.referenceId'   => 'nullable|integer',
            'initialMovement.notes'         => 'nullable|string',
            'initialMovement.idUser'        => 'required_with:initialMovement|integer|exists:users,id',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $product = $this->product();

            // Producto inexistente: el controller responde 404, aqui no se valida.
            if (!$product) {
                return;
            }

            if (!$product->stock_control) {
                $validator->errors()->add(
                    'variants',
                    'El producto no maneja existencias: activa stockControl y asigna un grupo de tallas antes de agregar variantes.'
                );

                return;
            }

            $variants = $this->input('variants', []);

            if (!is_array($variants) || empty($variants)) {
                return;
            }

            $this->validateSizesBelongToGroup($validator, $variants, $product->id_size_group);
            $this->validateCombosAreUniqueInPayload($validator, $variants);
            $this->validateCombosAreNewForProduct($validator, $variants, $product->id);
        });
    }

    public function product(): ?Product
    {
        return $this->product ??= Product::find((int) $this->route('product'));
    }

    public function toDTO(): CreateVariantsDTO
    {
        $variants = array_map(
            fn(array $v) => new VariantInputDTO(
                sizeId:  (int) $v['idSize'],
                colorId: (int) $v['idColor'],
                sku:     $v['sku'],
                codeBar: $v['codeBar'] ?? null,
                stock:   (float) $v['stock'],
                status:  $v['status'] ?? 'active',
            ),
            $this->input('variants', []) ?? [],
        );

        $initialMovement = null;

        if ($this->filled('initialMovement')) {
            $im = $this->input('initialMovement');

            $initialMovement = new InitialMovementDTO(
                movementType:  $im['movementType'] ?? 'entry',
                referenceType: $im['referenceType'] ?? 'initial_load',
                referenceId:   isset($im['referenceId']) ? (int) $im['referenceId'] : null,
                notes:         $im['notes'] ?? null,
                userId:        (int) $im['idUser'],
            );
        }

        return new CreateVariantsDTO(
            variants:        $variants,
            initialMovement: $initialMovement,
        );
    }
}
