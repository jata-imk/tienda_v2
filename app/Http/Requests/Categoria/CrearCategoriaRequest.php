<?php

namespace App\Http\Requests\Categoria;

use App\DTOs\Inventario\CrearCategoriaDTO;
use Illuminate\Foundation\Http\FormRequest;

class CrearCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string',
            'status'      => 'sometimes|in:activo,inactivo',
        ];
    }

    public function toDTO(): CrearCategoriaDTO
    {
        return new CrearCategoriaDTO(
            name:        $this->input('name'),
            description: $this->input('description'),
            status:      $this->input('status', 'activo'),
        );
    }
}
