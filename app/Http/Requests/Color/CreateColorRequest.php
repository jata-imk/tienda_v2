<?php

namespace App\Http\Requests\Color;

use App\DTOs\Color\CreateColorDTO;
use Illuminate\Foundation\Http\FormRequest;

class CreateColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:100',
            'hexColor' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'status'   => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): CreateColorDTO
    {
        return new CreateColorDTO(
            name:     $this->input('name'),
            hexColor: $this->input('hexColor'),
            status:   $this->input('status', 'active'),
        );
    }
}
