<?php

namespace App\Http\Requests\Config;

use App\DTOs\Config\ActualizarImpuestosConfigDTO;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarImpuestosConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'iva'     => 'sometimes|numeric|min:0|max:100',
            'isr'     => 'sometimes|numeric|min:0|max:100',
            'imp_esp' => 'sometimes|numeric|min:0|max:100',
        ];
    }

    public function toDTO(): ActualizarImpuestosConfigDTO
    {
        return new ActualizarImpuestosConfigDTO(
            iva:    $this->filled('iva')     ? (float) $this->input('iva')     : null,
            isr:    $this->filled('isr')     ? (float) $this->input('isr')     : null,
            impEsp: $this->filled('imp_esp') ? (float) $this->input('imp_esp') : null,
        );
    }
}
