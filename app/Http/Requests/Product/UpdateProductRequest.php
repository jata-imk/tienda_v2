<?php

namespace App\Http\Requests\Product;

use App\DTOs\Product\UpdateProductDTO;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('product');

        return [
            'id_category'   => 'sometimes|integer|exists:categories,id',
            'key'           => "sometimes|string|max:50|unique:products,key,{$id}",
            'name'          => 'sometimes|string|max:200',
            'description'   => 'nullable|string',
            'code_bar'      => 'nullable|string|max:50',
            'size'          => 'nullable|string|max:20',
            'price'         => 'sometimes|numeric|min:0',
            'cost'          => 'sometimes|numeric|min:0',
            'stock_control' => 'sometimes|boolean',
            'stock'         => 'sometimes|numeric|min:0',
            'discount'      => 'sometimes|numeric|min:0|max:100',
            'type_iva'      => 'sometimes|integer|in:1,2,3,4',
            'rate_iva'      => 'nullable|numeric|min:0|max:100',
            'quota_iva'     => 'nullable|numeric|min:0',
            'isr'           => 'sometimes|numeric|min:0|max:100',
            'imp_esp'       => 'sometimes|numeric|min:0|max:100',
            'status'        => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): UpdateProductDTO
    {
        return new UpdateProductDTO(
            categoryId:   $this->filled('id_category') ? (int) $this->input('id_category') : null,
            key:          $this->input('key'),
            name:         $this->input('name'),
            description:  $this->input('description'),
            codeBar:      $this->input('code_bar'),
            size:         $this->input('size'),
            price:        $this->filled('price') ? (float) $this->input('price') : null,
            cost:         $this->filled('cost') ? (float) $this->input('cost') : null,
            stockControl: $this->has('stock_control') ? (bool) $this->input('stock_control') : null,
            stock:        $this->filled('stock') ? (float) $this->input('stock') : null,
            discount:     $this->filled('discount') ? (float) $this->input('discount') : null,
            typeIva:      $this->filled('type_iva') ? (int) $this->input('type_iva') : null,
            rateIva:      $this->filled('rate_iva') ? (float) $this->input('rate_iva') : null,
            quotaIva:     $this->filled('quota_iva') ? (float) $this->input('quota_iva') : null,
            isr:          $this->filled('isr') ? (float) $this->input('isr') : null,
            impEsp:       $this->filled('imp_esp') ? (float) $this->input('imp_esp') : null,
            status:       $this->input('status'),
        );
    }
}
