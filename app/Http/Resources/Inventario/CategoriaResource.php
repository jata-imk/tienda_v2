<?php

namespace App\Http\Resources\Inventario;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoriaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'status'       => $this->status,
            'name'         => $this->name,
            'description'  => $this->description,
            'dateCreation' => $this->date_creation?->toDateTimeString(),
        ];
    }
}
