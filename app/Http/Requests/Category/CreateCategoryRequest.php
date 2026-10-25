<?php

namespace App\Http\Requests\Category;

use App\DTOs\Category\CreateCategoryDTO;
use App\Http\Requests\Concerns\UppercasesKeyInput;
use Illuminate\Foundation\Http\FormRequest;

class CreateCategoryRequest extends FormRequest
{
    use UppercasesKeyInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Opcional: sin clave se deriva del nombre (`Camisa` -> `CAM`).
            'key'         => 'nullable|string|alpha_num:ascii|max:10|unique:categories,key',
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string',
            'status'      => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): CreateCategoryDTO
    {
        return new CreateCategoryDTO(
            key:         $this->input('key'),
            name:        $this->input('name'),
            description: $this->input('description'),
            status:      $this->input('status', 'active'),
        );
    }
}
