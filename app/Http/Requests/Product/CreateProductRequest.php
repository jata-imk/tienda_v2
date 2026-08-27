<?php

namespace App\Http\Requests\Product;

use App\DTOs\Product\CreateProductDTO;
use App\DTOs\Product\InitialMovementDTO;
use App\DTOs\Product\VariantInputDTO;
use App\Http\Requests\Product\Concerns\NormalizesCategoriesInput;
use App\Http\Requests\Product\Concerns\ValidatesVariantInput;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class CreateProductRequest extends FormRequest
{
    use NormalizesCategoriesInput;
    use ValidatesVariantInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categories'   => 'required|array|min:1',
            'categories.*' => 'integer|distinct|exists:categories,id',
            'idSizeGroup'  => 'required_if:stockControl,true|nullable|integer|exists:size_groups,id',
            'key'          => 'required|string|max:50|unique:products,key',
            'name'         => 'required|string|max:200',
            'description'  => 'nullable|string',
            'codeBar'      => 'nullable|string|max:50',
            'price'        => 'required|numeric|min:0',
            'cost'         => 'required|numeric|min:0',
            'stockControl' => 'required|boolean',
            'discount'     => 'sometimes|numeric|min:0|max:100',
            'typeIva'      => 'required|integer|in:1,2,3,4',
            'rateIva'      => 'nullable|numeric|min:0|max:100',
            'quotaIva'     => 'nullable|numeric|min:0',
            'isr'          => 'sometimes|numeric|min:0|max:100',
            'impEsp'       => 'sometimes|numeric|min:0|max:100',
            'status'       => 'sometimes|in:active,inactive',

            // Variantes (matriz talla x color)
            'variants'              => 'required_if:stockControl,true|array',
            'variants.*.idSize'     => 'required|integer|exists:sizes,id',
            'variants.*.idColor'    => 'required|integer|exists:colors,id',
            'variants.*.sku'        => 'required|string|max:80|distinct|unique:product_variants,sku',
            'variants.*.codeBar'    => 'nullable|string|max:50',
            'variants.*.stock'      => 'required|numeric|min:0',
            'variants.*.status'     => 'sometimes|in:active,inactive',

            // Movimiento inicial (opcional)
            'initialMovement'                => 'sometimes|array',
            'initialMovement.movementType'   => 'required_with:initialMovement|in:entry,sale,adjustment,return,cancel',
            'initialMovement.referenceType'  => 'nullable|string|max:50',
            'initialMovement.referenceId'    => 'nullable|integer',
            'initialMovement.notes'          => 'nullable|string',
            'initialMovement.idUser'         => 'required_with:initialMovement|integer|exists:users,id',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $variants  = $this->input('variants', []);
            $groupId   = $this->input('idSizeGroup');

            if (!is_array($variants) || empty($variants)) {
                return;
            }

            $this->validateSizesBelongToGroup($validator, $variants, $groupId ? (int) $groupId : null);
            $this->validateCombosAreUniqueInPayload($validator, $variants);
        });
    }

    public function toDTO(): CreateProductDTO
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

        return new CreateProductDTO(
            categoryIds:     array_map('intval', $this->input('categories', [])),
            sizeGroupId:     $this->filled('idSizeGroup') ? (int) $this->input('idSizeGroup') : null,
            key:             $this->input('key'),
            name:            $this->input('name'),
            description:     $this->input('description'),
            codeBar:         $this->input('codeBar'),
            price:           (float) $this->input('price'),
            cost:            (float) $this->input('cost'),
            stockControl:    (bool) $this->input('stockControl'),
            discount:        (float) $this->input('discount', 0),
            typeIva:         (int) $this->input('typeIva'),
            rateIva:         $this->filled('rateIva') ? (float) $this->input('rateIva') : null,
            quotaIva:        $this->filled('quotaIva') ? (float) $this->input('quotaIva') : null,
            isr:             (float) $this->input('isr', 0),
            impEsp:          (float) $this->input('impEsp', 0),
            variants:        $variants,
            initialMovement: $initialMovement,
            status:          $this->input('status', 'active'),
        );
    }
}
