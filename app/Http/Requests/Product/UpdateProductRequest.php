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
            'idCategory'   => 'sometimes|integer|exists:categories,id',
            'key'          => "sometimes|string|max:50|unique:products,key,{$id}",
            'name'         => 'sometimes|string|max:200',
            'description'  => 'nullable|string',
            'codeBar'      => 'nullable|string|max:50',
            'size'         => 'nullable|string|max:20',
            'price'        => 'sometimes|numeric|min:0',
            'cost'         => 'sometimes|numeric|min:0',
            'stockControl' => 'sometimes|boolean',
            'stock'        => 'sometimes|numeric|min:0',
            'discount'     => 'sometimes|numeric|min:0|max:100',
            'typeIva'      => 'sometimes|integer|in:1,2,3,4',
            'rateIva'      => 'nullable|numeric|min:0|max:100',
            'quotaIva'     => 'nullable|numeric|min:0',
            'isr'          => 'sometimes|numeric|min:0|max:100',
            'impEsp'       => 'sometimes|numeric|min:0|max:100',
            'status'       => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): UpdateProductDTO
    {
        return new UpdateProductDTO(
            categoryId:   $this->filled('idCategory') ? (int) $this->input('idCategory') : null,
            key:          $this->input('key'),
            name:         $this->input('name'),
            description:  $this->input('description'),
            codeBar:      $this->input('codeBar'),
            size:         $this->input('size'),
            price:        $this->filled('price') ? (float) $this->input('price') : null,
            cost:         $this->filled('cost') ? (float) $this->input('cost') : null,
            stockControl: $this->has('stockControl') ? (bool) $this->input('stockControl') : null,
            stock:        $this->filled('stock') ? (float) $this->input('stock') : null,
            discount:     $this->filled('discount') ? (float) $this->input('discount') : null,
            typeIva:      $this->filled('typeIva') ? (int) $this->input('typeIva') : null,
            rateIva:      $this->filled('rateIva') ? (float) $this->input('rateIva') : null,
            quotaIva:     $this->filled('quotaIva') ? (float) $this->input('quotaIva') : null,
            isr:          $this->filled('isr') ? (float) $this->input('isr') : null,
            impEsp:       $this->filled('impEsp') ? (float) $this->input('impEsp') : null,
            status:       $this->input('status'),
        );
    }
}
