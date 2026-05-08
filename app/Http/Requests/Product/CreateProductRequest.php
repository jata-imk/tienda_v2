<?php

namespace App\Http\Requests\Product;

use App\DTOs\Product\CreateProductDTO;
use Illuminate\Foundation\Http\FormRequest;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_category'   => 'required|integer|exists:categories,id',
            'key'           => 'required|string|max:50|unique:products,key',
            'name'          => 'required|string|max:200',
            'description'   => 'nullable|string',
            'code_bar'      => 'nullable|string|max:50',
            'size'          => 'nullable|string|max:20',
            'price'         => 'required|numeric|min:0',
            'cost'          => 'required|numeric|min:0',
            'stock_control' => 'required|boolean',
            'stock'         => 'required|numeric|min:0',
            'discount'      => 'sometimes|numeric|min:0|max:100',
            'type_iva'      => 'required|integer|in:1,2,3,4',
            'rate_iva'      => 'nullable|numeric|min:0|max:100',
            'quota_iva'     => 'nullable|numeric|min:0',
            'isr'           => 'sometimes|numeric|min:0|max:100',
            'imp_esp'       => 'sometimes|numeric|min:0|max:100',
            'status'        => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): CreateProductDTO
    {
        return new CreateProductDTO(
            categoryId:   (int) $this->input('id_category'),
            key:          $this->input('key'),
            name:         $this->input('name'),
            description:  $this->input('description'),
            codeBar:      $this->input('code_bar'),
            size:         $this->input('size'),
            price:        (float) $this->input('price'),
            cost:         (float) $this->input('cost'),
            stockControl: (bool) $this->input('stock_control'),
            stock:        (float) $this->input('stock'),
            discount:     (float) $this->input('discount', 0),
            typeIva:      (int) $this->input('type_iva'),
            rateIva:      $this->filled('rate_iva') ? (float) $this->input('rate_iva') : null,
            quotaIva:     $this->filled('quota_iva') ? (float) $this->input('quota_iva') : null,
            isr:          (float) $this->input('isr', 0),
            impEsp:       (float) $this->input('imp_esp', 0),
            status:       $this->input('status', 'active'),
        );
    }
}
