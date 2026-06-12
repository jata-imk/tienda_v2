<?php

namespace App\Http\Resources\InventoryMovement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'idProductVariant' => $this->id_product_variant,
            'movementType'     => $this->movement_type,
            'quantity'         => (float) $this->quantity,
            'previousStock'    => (float) $this->previous_stock,
            'newStock'         => (float) $this->new_stock,
            'referenceType'    => $this->reference_type,
            'referenceId'      => $this->reference_id,
            'notes'            => $this->notes,
            'idUser'           => $this->id_user,
            'createdAt'        => $this->created_at?->toDateTimeString(),
        ];
    }
}
