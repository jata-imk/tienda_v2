<?php

namespace App\Http\Requests\Categoria;

use App\DTOs\Inventario\ActualizarCategoriaDTO;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'status'      => 'sometimes|in:activo,inactivo',
        ];
    }

    public function toDTO(): ActualizarCategoriaDTO
    {
        return new ActualizarCategoriaDTO(
            name:        $this->input('name'),
            description: $this->input('description'),
            status:      $this->input('status'),
        );
    }
}
