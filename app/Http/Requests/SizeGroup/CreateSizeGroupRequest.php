<?php

namespace App\Http\Requests\SizeGroup;

use App\DTOs\SizeGroup\CreateSizeGroupDTO;
use Illuminate\Foundation\Http\FormRequest;

class CreateSizeGroupRequest extends FormRequest
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

    public function toDTO(): CreateSizeGroupDTO
    {
        return new CreateSizeGroupDTO(
            name:        $this->input('name'),
            description: $this->input('description'),
            status:      $this->input('status', 'active'),
        );
    }
}
