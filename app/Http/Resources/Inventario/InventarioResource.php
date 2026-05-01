<?php

namespace App\Http\Resources\Inventario;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'idCategory'   => $this->category_id,
            'category'     => $this->categoria?->name,
            'status'       => $this->status,
            'key'          => $this->clave,
            'name'         => $this->name,
            'description'  => $this->description,
            'codebar'      => $this->codebar,
            'price'        => (float) $this->price,
            'cost'         => (float) $this->cost,
            'stockControl' => $this->stock_control,
            'stock'        => (float) $this->stock,
            'discount'     => (float) $this->discount,
            'typeIVA'      => $this->type_iva_id,
            'tipoIva'      => $this->tipoIva?->name,
            'rateIVA'      => $this->rate_iva !== null ? (float) $this->rate_iva : null,
            'quotaIVA'     => $this->quota_iva !== null ? (float) $this->quota_iva : null,
            'ISR'          => (float) $this->isr,
            'impESP'       => (float) $this->imp_esp,
            'dateCreation' => $this->date_creation?->toDateTimeString(),
        ];
    }
}
