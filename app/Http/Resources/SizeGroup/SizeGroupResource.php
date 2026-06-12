<?php

namespace App\Http\Resources\SizeGroup;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SizeGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'status'      => $this->status,
            'createdAt'   => $this->created_at?->toDateTimeString(),
            'updatedAt'   => $this->updated_at?->toDateTimeString(),
        ];
    }
}
