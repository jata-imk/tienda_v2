<?php

namespace App\Http\Requests\InventoryMovement;

use App\DTOs\InventoryMovement\CreateMovementDTO;
use Illuminate\Foundation\Http\FormRequest;

class CreateMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idProductVariant' => 'required|integer|exists:product_variants,id',
            'movementType'     => 'required|in:entry,sale,adjustment,return,cancel',
            'quantity'         => 'required|numeric|gt:0',
            'referenceType'    => 'nullable|string|max:50',
            'referenceId'      => 'nullable|integer',
            'notes'            => 'nullable|string',
            'idUser'           => 'required|integer|exists:users,id',
        ];
    }

    public function toDTO(): CreateMovementDTO
    {
        return new CreateMovementDTO(
            productVariantId: (int) $this->input('idProductVariant'),
            movementType:     $this->input('movementType'),
            quantity:         (float) $this->input('quantity'),
            referenceType:    $this->input('referenceType'),
            referenceId:      $this->filled('referenceId') ? (int) $this->input('referenceId') : null,
            notes:            $this->input('notes'),
            userId:           (int) $this->input('idUser'),
        );
    }
}
