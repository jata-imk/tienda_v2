<?php

namespace App\Http\Requests\Size;

use App\DTOs\Size\CreateSizeDTO;
use Illuminate\Foundation\Http\FormRequest;

class CreateSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idSizeGroup' => 'required|integer|exists:size_groups,id',
            'name'        => 'required|string|max:20',
            'sortOrder'   => 'sometimes|integer|min:0',
            'status'      => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): CreateSizeDTO
    {
        return new CreateSizeDTO(
            sizeGroupId: (int) $this->input('idSizeGroup'),
            name:        $this->input('name'),
            sortOrder:   (int) $this->input('sortOrder', 0),
            status:      $this->input('status', 'active'),
        );
    }
}
