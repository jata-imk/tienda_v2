<?php

namespace App\Http\Requests\Color;

use App\DTOs\Color\UpdateColorDTO;
use Illuminate\Foundation\Http\FormRequest;

class UpdateColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => 'sometimes|string|max:100',
            'hexColor' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'status'   => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): UpdateColorDTO
    {
        return new UpdateColorDTO(
            name:     $this->input('name'),
            hexColor: $this->input('hexColor'),
            status:   $this->input('status'),
        );
    }
}
