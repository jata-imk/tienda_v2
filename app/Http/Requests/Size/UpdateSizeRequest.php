<?php

namespace App\Http\Requests\Size;

use App\DTOs\Size\UpdateSizeDTO;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idSizeGroup' => 'sometimes|integer|exists:size_groups,id',
            'name'        => 'sometimes|string|max:20',
            'sortOrder'   => 'sometimes|integer|min:0',
            'status'      => 'sometimes|in:active,inactive',
        ];
    }

    public function toDTO(): UpdateSizeDTO
    {
        return new UpdateSizeDTO(
            sizeGroupId: $this->filled('idSizeGroup') ? (int) $this->input('idSizeGroup') : null,
            name:        $this->input('name'),
            sortOrder:   $this->filled('sortOrder') ? (int) $this->input('sortOrder') : null,
            status:      $this->input('status'),
        );
    }
}
