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
            'idCategory'   => 'required|integer|exists:categories,id',
            'key'          => 'required|string|max:50|unique:products,key',
            'name'         => 'required|string|max:200',
            'description'  => 'nullable|string',
            'codeBar'      => 'nullable|string|max:50',
            'size'         => 'nullable|string|max:20',
            'price'        => 'required|numeric|min:0',
            'cost'         => 'required|numeric|min:0',
            'stockControl' => 'required|boolean',
            'stock'        => 'required|numeric|min:0',
            'discount'     => 'sometimes|numeric|min:0|max:100',
            'typeIva'      => 'required|integer|in:1,2,3,4',
            'rateIva'      => 'nullable|numeric|min:0|max:100',
            'quotaIva'     => 'nullable|numeric|min:0',
            'isr'          => 'sometimes|numeric|min:0|max:100',
            'impEsp'       => 'sometimes|numeric|min:0|max:100',
            'status'       => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): CreateProductDTO
    {
        return new CreateProductDTO(
            categoryId:   (int) $this->input('idCategory'),
            key:          $this->input('key'),
            name:         $this->input('name'),
            description:  $this->input('description'),
            codeBar:      $this->input('codeBar'),
            size:         $this->input('size'),
            price:        (float) $this->input('price'),
            cost:         (float) $this->input('cost'),
            stockControl: (bool) $this->input('stockControl'),
            stock:        (float) $this->input('stock'),
            discount:     (float) $this->input('discount', 0),
            typeIva:      (int) $this->input('typeIva'),
            rateIva:      $this->filled('rateIva') ? (float) $this->input('rateIva') : null,
            quotaIva:     $this->filled('quotaIva') ? (float) $this->input('quotaIva') : null,
            isr:          (float) $this->input('isr', 0),
            impEsp:       (float) $this->input('impEsp', 0),
            status:       $this->input('status', 'active'),
        );
    }
}
