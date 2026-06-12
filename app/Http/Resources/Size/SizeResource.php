<?php

namespace App\Http\Resources\Size;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SizeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'idSizeGroup' => $this->id_size_group,
            'sizeGroup'   => $this->sizeGroup?->name,
            'name'        => $this->name,
            'sortOrder'   => $this->sort_order,
            'status'      => $this->status,
            'createdAt'   => $this->created_at?->toDateTimeString(),
            'updatedAt'   => $this->updated_at?->toDateTimeString(),
        ];
    }
}
