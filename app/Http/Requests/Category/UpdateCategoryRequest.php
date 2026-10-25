<?php

namespace App\Http\Requests\Category;

use App\DTOs\Category\UpdateCategoryDTO;
use App\Http\Requests\Concerns\UppercasesKeyInput;
use App\Http\Requests\Concerns\ValidatesKeyCascade;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    use UppercasesKeyInput;
    use ValidatesKeyCascade;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('category');

        return [
            // Cambiarla solo renombra las claves de producto con `cascadeKeys: true`.
            'key'         => "sometimes|string|alpha_num:ascii|max:10|unique:categories,key,{$id}",
            'name'        => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'status'      => 'sometimes|in:active,inactive',
            ...$this->keyCascadeRules(),
        ];
    }

    public function toDTO(): UpdateCategoryDTO
    {
        return new UpdateCategoryDTO(
            key:         $this->input('key'),
            name:        $this->input('name'),
            description: $this->input('description'),
            status:      $this->input('status'),
            cascadeKeys: $this->cascadeKeys(),
        );
    }
}
