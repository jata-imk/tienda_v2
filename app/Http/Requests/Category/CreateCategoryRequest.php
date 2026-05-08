<?php

namespace App\Http\Requests\Category;

use App\DTOs\Category\CreateCategoryDTO;
use Illuminate\Foundation\Http\FormRequest;

class CreateCategoryRequest extends FormRequest
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
            'status'      => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): CreateCategoryDTO
    {
        return new CreateCategoryDTO(
            name:        $this->input('name'),
            description: $this->input('description'),
            status:      $this->input('status', 'active'),
        );
    }
}
