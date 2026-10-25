<?php

namespace App\Http\Requests\Product;

use App\DTOs\Product\NextProductKeyDTO;
use Illuminate\Foundation\Http\FormRequest;

class NextProductKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idMainCategory' => 'required|integer|exists:categories,id',
            'idSizeGroup'    => 'nullable|integer|exists:size_groups,id',
            'keySegment'     => 'nullable|string|alpha:ascii|max:5',
        ];
    }

    public function toDTO(): NextProductKeyDTO
    {
        return new NextProductKeyDTO(
            mainCategoryId: (int) $this->input('idMainCategory'),
            sizeGroupId:    $this->filled('idSizeGroup') ? (int) $this->input('idSizeGroup') : null,
            keySegment:     $this->filled('keySegment') ? $this->input('keySegment') : null,
        );
    }
}
