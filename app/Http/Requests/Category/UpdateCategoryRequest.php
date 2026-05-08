<?php

namespace App\Http\Requests\Category;

use App\DTOs\Category\UpdateCategoryDTO;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
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
            'status'      => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): UpdateCategoryDTO
    {
        return new UpdateCategoryDTO(
            name:        $this->input('name'),
            description: $this->input('description'),
            status:      $this->input('status'),
        );
    }
}
