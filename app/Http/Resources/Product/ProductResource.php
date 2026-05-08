<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'idCategory'   => $this->id_category,
            'category'     => $this->category?->name,
            'key'          => $this->key,
            'name'         => $this->name,
            'description'  => $this->description,
            'codeBar'      => $this->code_bar,
            'size'         => $this->size,
            'price'        => (float) $this->price,
            'cost'         => (float) $this->cost,
            'stockControl' => $this->stock_control,
            'stock'        => (float) $this->stock,
            'discount'     => (float) $this->discount,
            'typeIVA'      => $this->type_iva,
            'rateIVA'      => $this->rate_iva !== null ? (float) $this->rate_iva : null,
            'quotaIVA'     => $this->quota_iva !== null ? (float) $this->quota_iva : null,
            'isr'          => (float) $this->isr,
            'impEsp'       => (float) $this->imp_esp,
            'status'       => $this->status,
            'createdAt'    => $this->created_at?->toDateTimeString(),
            'updatedAt'    => $this->updated_at?->toDateTimeString(),
        ];
    }
}
