<?php

namespace App\Http\Resources\Config;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImpuestosConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'iva'         => (float) $this->iva,
            'isr'         => (float) $this->isr,
            'impEsp'      => (float) $this->imp_esp,
            'dateCreation' => $this->date_creation?->toDateTimeString(),
            'dateUpdate'   => $this->date_update?->toDateTimeString(),
        ];
    }
}
