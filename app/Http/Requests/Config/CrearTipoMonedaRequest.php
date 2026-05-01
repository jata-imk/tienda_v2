<?php

namespace App\Http\Requests\Config;

use App\DTOs\Config\CrearTipoMonedaDTO;
use Illuminate\Foundation\Http\FormRequest;

class CrearTipoMonedaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'   => 'required|string|max:100',
            'code'   => 'required|string|max:10|unique:tipos_moneda,code',
            'symbol' => 'required|string|max:10',
            'status' => 'sometimes|in:activo,inactivo',
        ];
    }

    public function toDTO(): CrearTipoMonedaDTO
    {
        return new CrearTipoMonedaDTO(
            name:   $this->input('name'),
            code:   strtoupper($this->input('code')),
            symbol: $this->input('symbol'),
            status: $this->input('status', 'activo'),
        );
    }
}
