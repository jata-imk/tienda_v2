<?php

namespace App\Http\Requests\SizeGroup;

use App\DTOs\SizeGroup\UpdateSizeGroupDTO;
use App\Http\Requests\Concerns\UppercasesKeyInput;
use App\Http\Requests\Concerns\ValidatesKeyCascade;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSizeGroupRequest extends FormRequest
{
    use UppercasesKeyInput;
    use ValidatesKeyCascade;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('size_group');

        return [
            // `key: null` (o "") quita el segmento: el grupo deja de agregarlo a la clave.
            'key'         => "sometimes|nullable|string|alpha_num:ascii|max:10|unique:size_groups,key,{$id}",
            'name'        => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'status'      => 'sometimes|in:active,inactive',
            ...$this->keyCascadeRules(),
        ];
    }

    public function toDTO(): UpdateSizeGroupDTO
    {
        return new UpdateSizeGroupDTO(
            key:         $this->input('key'),
            clearKey:    $this->has('key') && $this->input('key') === null,
            name:        $this->input('name'),
            description: $this->input('description'),
            status:      $this->input('status'),
            cascadeKeys: $this->cascadeKeys(),
        );
    }
}
