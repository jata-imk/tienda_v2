<?php

namespace App\Http\Requests\Inventario;

use App\DTOs\Inventario\ActualizarInventarioDTO;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('inventario');

        return [
            'category_id'   => 'sometimes|integer|exists:categorias,id',
            'clave'         => "sometimes|string|max:50|unique:inventario,clave,{$id}",
            'name'          => 'sometimes|string|max:200',
            'description'   => 'nullable|string',
            'codebar'       => 'nullable|string|max:50',
            'price'         => 'sometimes|numeric|min:0',
            'cost'          => 'sometimes|numeric|min:0',
            'stock_control' => 'sometimes|boolean',
            'stock'         => 'sometimes|numeric|min:0',
            'discount'      => 'sometimes|numeric|min:0|max:100',
            'type_iva_id'   => 'sometimes|integer|exists:tipos_iva,id',
            'rate_iva'      => 'nullable|numeric|min:0|max:100',
            'quota_iva'     => 'nullable|numeric|min:0',
            'isr'           => 'sometimes|numeric|min:0|max:100',
            'imp_esp'       => 'sometimes|numeric|min:0|max:100',
            'status'        => 'sometimes|in:activo,baja',
        ];
    }

    public function toDTO(): ActualizarInventarioDTO
    {
        return new ActualizarInventarioDTO(
            categoryId:   $this->filled('category_id') ? (int) $this->input('category_id') : null,
            clave:        $this->input('clave'),
            name:         $this->input('name'),
            description:  $this->input('description'),
            codebar:      $this->input('codebar'),
            price:        $this->filled('price') ? (float) $this->input('price') : null,
            cost:         $this->filled('cost') ? (float) $this->input('cost') : null,
            stockControl: $this->has('stock_control') ? (bool) $this->input('stock_control') : null,
            stock:        $this->filled('stock') ? (float) $this->input('stock') : null,
            discount:     $this->filled('discount') ? (float) $this->input('discount') : null,
            typeIvaId:    $this->filled('type_iva_id') ? (int) $this->input('type_iva_id') : null,
            rateIva:      $this->filled('rate_iva') ? (float) $this->input('rate_iva') : null,
            quotaIva:     $this->filled('quota_iva') ? (float) $this->input('quota_iva') : null,
            isr:          $this->filled('isr') ? (float) $this->input('isr') : null,
            impEsp:       $this->filled('imp_esp') ? (float) $this->input('imp_esp') : null,
            status:       $this->input('status'),
        );
    }
}
