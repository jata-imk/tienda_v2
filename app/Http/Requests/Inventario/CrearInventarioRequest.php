<?php

namespace App\Http\Requests\Inventario;

use App\DTOs\Inventario\CrearInventarioDTO;
use Illuminate\Foundation\Http\FormRequest;

class CrearInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id'   => 'required|integer|exists:categorias,id',
            'clave'         => 'required|string|max:50|unique:inventario,clave',
            'name'          => 'required|string|max:200',
            'description'   => 'nullable|string',
            'codebar'       => 'nullable|string|max:50',
            'price'         => 'required|numeric|min:0',
            'cost'          => 'required|numeric|min:0',
            'stock_control' => 'required|boolean',
            'stock'         => 'required|numeric|min:0',
            'discount'      => 'sometimes|numeric|min:0|max:100',
            'type_iva_id'   => 'required|integer|exists:tipos_iva,id',
            'rate_iva'      => 'nullable|numeric|min:0|max:100',
            'quota_iva'     => 'nullable|numeric|min:0',
            'isr'           => 'sometimes|numeric|min:0|max:100',
            'imp_esp'       => 'sometimes|numeric|min:0|max:100',
            'status'        => 'sometimes|in:activo,baja',
        ];
    }

    public function toDTO(): CrearInventarioDTO
    {
        return new CrearInventarioDTO(
            categoryId:   (int) $this->input('category_id'),
            clave:        $this->input('clave'),
            name:         $this->input('name'),
            description:  $this->input('description'),
            codebar:      $this->input('codebar'),
            price:        (float) $this->input('price'),
            cost:         (float) $this->input('cost'),
            stockControl: (bool) $this->input('stock_control'),
            stock:        (float) $this->input('stock'),
            discount:     (float) $this->input('discount', 0),
            typeIvaId:    (int) $this->input('type_iva_id'),
            rateIva:      $this->filled('rate_iva') ? (float) $this->input('rate_iva') : null,
            quotaIva:     $this->filled('quota_iva') ? (float) $this->input('quota_iva') : null,
            isr:          (float) $this->input('isr', 0),
            impEsp:       (float) $this->input('imp_esp', 0),
            status:       $this->input('status', 'activo'),
        );
    }
}
