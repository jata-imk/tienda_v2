<?php

namespace App\Http\Requests\SizeGroup;

use App\DTOs\SizeGroup\UpdateSizeGroupDTO;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSizeGroupRequest extends FormRequest
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

    public function toDTO(): UpdateSizeGroupDTO
    {
        return new UpdateSizeGroupDTO(
            name:        $this->input('name'),
            description: $this->input('description'),
            status:      $this->input('status'),
        );
    }
}
