<?php

namespace App\Http\Requests\SizeGroup;

use App\DTOs\SizeGroup\CreateSizeGroupDTO;
use App\Http\Requests\Concerns\UppercasesKeyInput;
use Illuminate\Foundation\Http\FormRequest;

class CreateSizeGroupRequest extends FormRequest
{
    use UppercasesKeyInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Segmento opcional de la clave de producto (`NIN` -> `GUA-NIN-001`).
            'key'         => 'nullable|string|alpha_num:ascii|max:10|unique:size_groups,key',
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string',
            'status'      => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): CreateSizeGroupDTO
    {
        return new CreateSizeGroupDTO(
            key:         $this->input('key'),
            name:        $this->input('name'),
            description: $this->input('description'),
            status:      $this->input('status', 'active'),
        );
    }
}
