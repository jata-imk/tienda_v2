<?php

namespace App\Http\Resources\Config;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipoMonedaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'status'       => $this->status,
            'name'         => $this->name,
            'code'         => $this->code,
            'symbol'       => $this->symbol,
            'dateCreation' => $this->date_creation?->toDateTimeString(),
        ];
    }
}
