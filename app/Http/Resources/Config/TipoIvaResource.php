<?php

namespace App\Http\Resources\Config;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipoIvaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'dateCreation' => $this->date_creation?->toDateTimeString(),
        ];
    }
}
