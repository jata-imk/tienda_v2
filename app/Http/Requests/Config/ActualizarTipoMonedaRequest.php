<?php

namespace App\Http\Requests\Config;

use App\DTOs\Config\ActualizarTipoMonedaDTO;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarTipoMonedaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('tipos_moneda');

        return [
            'name'   => 'sometimes|string|max:100',
            'code'   => "sometimes|string|max:10|unique:tipos_moneda,code,{$id}",
            'symbol' => 'sometimes|string|max:10',
            'status' => 'sometimes|in:activo,inactivo',
        ];
    }

    public function toDTO(): ActualizarTipoMonedaDTO
    {
        return new ActualizarTipoMonedaDTO(
            name:   $this->input('name'),
            code:   $this->filled('code') ? strtoupper($this->input('code')) : null,
            symbol: $this->input('symbol'),
            status: $this->input('status'),
        );
    }
}
